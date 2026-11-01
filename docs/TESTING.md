# TESTING

Framework: PHPUnit 10 (Laravel default). Belum memakai Pest.

## 1. Menjalankan

```powershell
cd C:\laragon\www\LMS-V2

php artisan test                              # semua
php artisan test --filter=PaymentFulfillment  # satu kelas
php artisan test tests/Feature/PaymentFulfillmentTest.php --filter=test_paying
```

## 2. Database test

`phpunit.xml` diarahkan ke MySQL **`sbu_test`**, bukan SQLite, karena migration
project ini memakai `->change()`, enum, dan JSON yang tidak kompatibel penuh
dengan SQLite.

```xml
<env name="DB_CONNECTION" value="mysql"/>
<env name="DB_DATABASE" value="sbu_test"/>
```

Test memakai `RefreshDatabase` → **seluruh isi `sbu_test` dihapus setiap run**.
Jangan pernah mengarahkan ini ke `sbu`.

**Perlindungan wajib (Phase 3.2):** `phpunit.xml` menetapkan `APP_CONFIG_CACHE` ke
path terpisah agar test tidak membaca `bootstrap/cache/config.php` milik dev/produksi.
Cache config mengunci nama database secara literal, sehingga tanpa ini
`php artisan test` dapat menjalankan `RefreshDatabase` ke **`sbu`** dan menghapus data
development (pernah terjadi 1 Nov 2026; data dipulihkan dari
`storage/app/phase2-backups/pre-phase2.sql`). Sebagai lapis kedua,
`tests/TestCase::beforeRefreshingDatabase()` menolak database selain `sbu_test`.
Bila muncul pesan `Refusing to run destructive tests against ...`, jalankan
`php artisan optimize:clear` dan periksa `phpunit.xml`.

Buat database sekali saja:

```sql
CREATE DATABASE sbu_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 3. Status validasi

**Hasil akhir, 1 November 2026 (PHP 8.3.26, MySQL lokal):**

| Validasi yang dijalankan | Hasil |
|-------------------------|-------|
| `php artisan test` pada `sbu_test` | **241 passed, 841 assertions, 0 failed** |
| `php tests/Manual/phase2-smoke.php` | **11 PASS**, rollback seluruh fixture bisnis |
| Pemeriksaan log selama smoke | **0** ERROR/CRITICAL/EMERGENCY baru |
| Pint `--test` dan `php -l` pada perubahan PHP | **71 file lulus** |
| `php artisan view:cache` dan lint PHP hasil kompilasi Blade | Lulus |
| `npm run build -- --outDir storage/app/phase2-build-check` | Lulus; tanpa menulis `public/build/` |
| Tujuh migration baru di `sbu` lokal sesudah backup privat | Lulus, seluruhnya Ran |
| Route/schedule list, HTTP homepage Laragon | Lulus; homepage 200 |

Batasan: smoke authenticated memakai kernel Laravel dan DB test, bukan klik browser
pada data development. SMTP/email dan Midtrans nyata tidak diuji. Rekening resmi belum
dikonfigurasi; runner scheduler dan worker jangka panjang belum dipasang oleh agent.
Build hanya memberi peringatan Browserslist lama. Tidak ada commit otomatis.

Catatan historis Phase 0 menyebut 68 passed (180 assertions); **bukan** hasil
suite saat ini. Inventaris lama berikut hanya konteks cakupan Phase 0:

| Kelompok | File |
|----------|------|
| Pembayaran | `PaymentFulfillmentTest` (10) |
| Gating membership | `MembershipAccessTest` (9) |
| Upload tugas | `AssignmentSubmissionTest` (5), `PlayerAssignmentTest` (2) |
| Kuis | `QuizGradingTest` (10), `PlayerQuizTest` (3) |
| Soft delete | `SoftDeleteTest` (3) |
| Auth & profil | `Auth/*` (13), `ProfileTest` (5) |
| Contoh bawaan | `ExampleTest` (2), dst. |

## 4. Cakupan yang sudah ada

| File | Cakupan |
|------|---------|
| `PaymentFulfillmentTest` | event `TransactionPaid`, enrollment program/batch, reaktivasi, membership & perpanjangan, lifetime, email + activity log, approval admin, idempotensi |
| `MembershipAccessTest` | level membership vs program/batch, plan eksplisit, membership kedaluwarsa, accessor `accessible_programs` |
| `AssignmentSubmissionTest` | simpan berkas, replace + reset nilai, larangan hapus setelah dinilai |
| `PlayerAssignmentTest` | end-to-end tombol "Kirim Tugas" di komponen Player |
| `QuizGradingTest` | skor dari DB, bobot soal, multi-jawaban, idempotensi, batas percobaan, resume, randomisasi stabil, timeout |
| `PlayerQuizTest` | ganti jawaban single-choice, penandaan lesson selesai, batas percobaan di UI |
| `SoftDeleteTest` | baris tetap ada, tersembunyi dari query, bisa restore |
| `Auth/*`, `ProfileTest` | login/logout, register, reset password, update password, profil |

## 5. Menulis test baru

Aturan:

- Nama method deskriptif berbahasa Inggris: `test_it_xxx_when_yyy`.
- Satu test = satu perilaku.
- Gunakan `RefreshDatabase`.
- Fake layanan luar: `Mail::fake()`, `Event::fake()`, `Queue::fake()`, `Http::fake()`.
- Jangan menguji implementasi internal; uji hasil yang terlihat (DB, respons, email).
- Data uji dibuat lewat helper privat di kelas test, bukan factory baru,
  kecuali dipakai >1 kelas.

Contoh kerangka:

```php
public function test_a_dropped_enrollment_is_reactivated_when_the_student_buys_again(): void
{
    $user = $this->makeUser();
    $program = $this->makeProgram();

    $dropped = Enrollment::create([
        'user_id' => $user->id,
        'enrollable_type' => Program::class,
        'enrollable_id' => $program->id,
        'status' => 'dropped',
        'enrolled_at' => now()->subYear(),
    ]);

    $this->makeTransaction($user, ['program_id' => $program->id])->markAsPaid();

    $this->assertSame(1, Enrollment::where('user_id', $user->id)->count());
    $this->assertSame('active', $dropped->fresh()->status);
}
```

## 6. Nilai enum yang valid (sering bikin test gagal)

| Kolom | Nilai valid |
|-------|-------------|
| `enrollments.status` | `active`, `completed`, `dropped` |
| `transactions.payment_status` | `pending`, `paid`, `failed`, `verified_by_admin` |
| `transactions.payment_method` | `manual_transfer`, `gateway_va`, `gateway_ewallet`, `free_access` |
| `batches.status` | `open`, `ongoing`, `closed` |
| `programs.type` | `self_paced`, `cohort` |

Kolom `enrollments.enrolled_at` **wajib diisi** (tidak ada default).

## 7. Cakupan Phase 2 yang sudah diuji

| File `tests/Feature/` | Skenario dalam definisi test |
|-----------------------|-----------------------------|
| `Phase2BillingTest.php` | Snapshot grant, stacking expiry, invoice manual hanya saat due, satu outstanding, harga plan saat invoice, lifetime/inactive plan, cancel mempertahankan akses, reaktivasi tanpa akses baru, command repeatable, privasi bukti/owner dan bank config |
| `Phase2CommerceTest.php` | Diskon snapshot, reservasi/redeem idempoten, last-quota/no stacking, release invoice gagal, checkout normal dan gateway payload, larangan kupon korporat, invoice multi-seat tanpa enroll pembeli, alokasi owner-only/permanen, akun student aktif, kapasitas termasuk kursi lunas belum teralokasi, blokir alokasi/replay setelah refund |
| `Phase2OperationsTest.php` | Honor tetap + bukti privat/hash/audit, immutable setelah paid, pembatasan instruktur, FIFO/reservasi/kapasitas waitlist, clone konfigurasi tanpa data operasional, absensi dan cakupan instruktur tanpa efek sertifikat |
| `Phase2EssayGradingTest.php` | Seluruh esai wajib dinilai, nilai nol/batas bobot/feedback, skor gabungan, pending/timeout/attempt limits, larangan edit setelah submit, grading owner/admin, kedua player, batch context, pencegahan forged completion/testimoni/sertifikat |
| `Phase2RefundTest.php` | Refund penuh manual, bukti/audit, idempotensi/replay, sumber lain/legacy beserta expiry aslinya, snapshot membership, refund seat korporat, revenue bersih dan label history, otorisasi |
| `Phase2AccessTest.php` | Expiry menutup akses/list program, batch program membuka course terkait saja |
| `Phase3PerformanceTest.php` | Query `loadLesson()` ≤ 10 dan **datar** (3 vs 12 section), progres memakai satu query `lesson_completions`, buka ulang lesson lebih murah, `lessons_count` memakai `withCount` (0 query) atau 3 query agregat datar (3.1–3.2) |
| `Phase2PaymentStateTest.php` | Callback pending/challenge tidak menurunkan paid, pending tidak membuka cancelled, instance stale tidak menggandakan fulfillment |

Regresi terkait: `PaymentFulfillmentTest.php`, `MembershipAccessTest.php`,
`PlayerQuizTest.php`, `QuizGradingTest.php`, `Phase1PolicyRateLimitTest.php`,
`Phase1UploadMIMEQuotaTest.php`. Inventaris ini tidak menggantikan eksekusi.

### Menjalankan ulang validasi

Jalankan **hanya setelah** memastikan DB efektif adalah `sbu_test`, bukan `sbu`,
dan runtime/service tersedia. Seluruh suite serta smoke telah dijalankan pada sesi ini;
filter di bawah dapat dipakai untuk pemeriksaan terarah:

```powershell
php artisan test --filter=Phase2BillingTest
php artisan test --filter=Phase2CommerceTest
php artisan test --filter=Phase2OperationsTest
php artisan test --filter=Phase2EssayGradingTest
php artisan test --filter=Phase2RefundTest
php artisan test --filter=PaymentFulfillmentTest
php artisan test
php tests/Manual/phase2-smoke.php
```

- Lakukan syntax/style check terarah pada PHP yang berubah; jangan menjalankan
  formatter yang menulis ulang seluruh repo dalam pekerjaan docs-only.
- Periksa route dengan `php artisan route:list --except-vendor` dan schedule
  dengan `php artisan schedule:list` setelah aplikasi dapat boot.
- Uji migration additive pada salinan DB dengan backup; jangan `migrate:fresh`
  pada DB development/produksi. Migration esai juga menyesuaikan metadata data lama.
- Untuk pemeriksaan frontend, gunakan outdir sementara **baru/kosong** di luar
  `public/build/`, misalnya `npm run build -- --outDir storage/app/phase2-build-check`.
  Jangan menjalankan build default yang menulis `public/build/`; output sementara
  bukan artefak deployment dan tidak perlu di-commit. Build ini telah dijalankan dan lulus.
- `php artisan route:cache` dan `php artisan view:cache` sudah diverifikasi berjalan
  pada aplikasi ini (Phase 3.1) dan aman dipakai di deployment; keduanya menulis ke
  `bootstrap/cache`/`storage/framework/views`, bukan ke `public/build/`. Setelah
  perubahan route/view, jalankan `php artisan optimize:clear` lalu cache ulang.

### Cakupan smoke dan pemeriksaan runtime

- `/billing`: instruksi bank, upload bukti tanpa aktivasi, approval admin,
  cancel/reaktivasi, satu invoice saat expiry dan tidak ada invoice lifetime.
- `/corporate` + `/admin/coupons`: checkout normal berdiskon vs corporate tanpa
  kupon; alokasi owner-only, kapasitas batch/reservasi, larangan ganti peserta.
- `/admin/refunds`: sumber lain tetap akses, membership hanya latest matching
  grant, legacy ditolak, renewal berhenti, seat reserve terlepas, kuota kupon tetap.
- Payout admin/instruktur: bukti privat dan audit; akses bukti pihak lain ditolak.
- Waitlist/clone/absensi: FIFO, promosi bukan enrollment, clone tanpa data operasional,
  instruktur tidak bisa menilai kehadiran di luar cakupan, tanpa gating sertifikat.
- `/grading/essays`: pending sampai semua dinilai, feedback dan nilai nol valid,
  hasil final/lulus membuka lesson; pending/gagal tidak bisa bypass sertifikat.
- Verifikasi download bukti, queue email, log error, dan scheduler pada runtime tujuan.

Khusus refund, skenario state mismatch/legacy, cancel renewal, reaktivasi sesudah
refund, kuota kupon tidak kembali, serta unduhan bukti perlu masuk pemeriksaan
akhir; jangan menganggap seluruhnya sudah tercakup hanya dari nama kelas refund.

Hasil akhir tercatat pada bagian 3. Smoke dapat diulang dengan
`php tests/Manual/phase2-smoke.php`: script menolak database selain `sbu_test`,
memalsukan layanan luar, dan me-rollback seluruh fixture bisnis. Pemeriksaan
runtime yang belum dilakukan (SMTP/Midtrans nyata dan runner OS) tetap eksplisit.
