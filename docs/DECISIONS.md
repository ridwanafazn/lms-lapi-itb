# DECISIONS (ADR ringkas)

Format: konteks → keputusan → konsekuensi. Tambahkan entri baru di bawah,
jangan hapus keputusan lama.

---

## ADR-001 — TALL stack untuk LMS

**Konteks**: tim sangat kecil, butuh landing page yang SEO-friendly, UI stateful
(quiz, curriculum builder) tanpa membangun SPA terpisah.

**Keputusan**: Laravel + Livewire 3 + Volt + Tailwind + Alpine.

**Konsekuensi**: satu bahasa untuk FE/BE, server-rendered (baik untuk SEO),
tetapi komponen besar cepat membengkak dan round-trip Livewire perlu dijaga.

---

## ADR-002 — UUID untuk semua primary key

**Konteks**: ID berurutan membocorkan jumlah data dan mudah ditebak.

**Keputusan**: `App\Traits\HasUuids` di seluruh model.

**Konsekuensi**: aman untuk URL publik; index sedikit lebih besar; relasi
mudah dibuat di sisi klien lebih dulu.

---

## ADR-003 — Pembayaran lewat event, bukan di dalam model

**Konteks**: `Transaction::markAsPaid()` awalnya membuat enrollment,
mengaktifkan membership, dan menyiapkan sertifikat sekaligus. Sulit dites dan
mudah lupa menambahkan efek baru.

**Keputusan**: `markAsPaid()` hanya mengubah status lalu
`TransactionPaid::dispatch(TransactionPaidData)`. Efek samping menjadi listener.

**Konsekuensi**:
- Setiap efek baru = satu listener baru, tanpa menyentuh model.
- Listener sinkron tetap di dalam `DB::transaction` → konsisten.
- Email di-queue agar SMTP lambat tidak membatalkan pembayaran.
- Butuh queue worker untuk email.

---

## ADR-004 — DTO readonly tanpa package tambahan

**Konteks**: event butuh payload yang aman di-serialize dan tidak bisa diubah listener.

**Keputusan**: class `App\Data\TransactionPaidData` (immutable, readonly properties,
constructor promotion).

**Konsekuensi**: listener menerima data sama persis, tanpa bisa memodifikasi
payload sebelum listener lain membaca.

---

## ADR-005 — Soft delete sebelum hard delete produksi

**Konteks**: user/admin bisa menghapus course/program/batch, tetapi data mungkin
perlu dipulihkan.

**Keputusan**: `SoftDeletes` trait + `deleted_at` nullable + `restored()` method.

**Konsekuensi**: data tetap ada, akses tersembunyi otomatis (`whereNull('deleted_at')`);
butuh penanganan index unik (`email`, `enrollment_code`) agar soft-delete tidak
memblokir pendaftaran ulang.

---

## ADR-006 — Service layer untuk tugas & kuis

**Konteks**: `Player::execute()` awalnya menyimpan file langsung ke disk tanpa
validasi MIME, tanpa batas percobaan, tanpa resume.

**Keputusan**: `AssignmentSubmissionService`, `QuizService`, `QuizGrader`.

**Konsekuensi**: perilaku bisnis terpusat di service; test bisa fokus pada
service tanpa memanggil komponen UI; `Player` hanya mengatur state.

---

## ADR-007 — Gating membership lewat trait `HasMembershipRequirement`

**Konteks**: `Program::isFreeForUser()` dan akses batch awalnya tidak konsisten.

**Keputusan**: `App\Models\Concerns\HasMembershipRequirement` dengan `LEVEL_RANK`
(`starter`:0, `standard`:1, `pro`:2) dan `isFreeForUser()`.

**Konsekuensi**: semua model (`Program`, `Batch`) menggunakan aturan sama;
`User::canAccessProgram()` dan `canAccessBatch()` menjadi satu sumber kebenaran.

---

## ADR-008 — Migration baru, jangan edit migration lama

**Konteks**: tabel `batches` dan `programs` tidak punya `price`/`min_membership_level`,
`submissions.batch_id` NOT NULL, `quiz_attempts.is_timeout` tidak ada.

**Keputusan**: 7 migration baru (`2026_11_01_000001` sampai `000007`).

**Konsekuensi**: data produksi tetap aman; test bisa rollback; skema bisa berkembang
bertahap.

---

## ADR-009 — Event-driven fulfillment untuk semua jenis pembayaran

**Konteks**: `FulfillEnrollment` awalnya hanya menangani program, tidak batch atau
membership renewal.

**Keputusan**: `TransactionPaidData` DTO berisi `enrollableType` dan `batchId`;
listener membaca DTO untuk menentukan aksi (program, batch, atau membership).

**Konsekuensi**: satu event untuk semua jenis pembayaran; tidak perlu event terpisah.

---

## ADR-010 — Policy per konten, bukan hanya per course

**Konteks**: sebelumnya hanya ada `CoursePolicy`. Akses langsung ke URL
`/learning/{course}/quiz/{quiz}` tidak memiliki otorisasi eksplisit.

**Keputusan**: `LessonPolicy`, `QuizPolicy`, `AssignmentPolicy` yang mengecek:
- admin/instruktur = akses penuh
- siswa = `hasAccessToCourse()` (enrollment aktif + membership aktif + konten published)

**Konsekuensi**: URL langsung sekarang bisa dilindungi (`authorize('view', $quiz)`);
akses kadaluwarsa (`access_expires_at`) bisa diterapkan di level policy atau model.

---

## ADR-011 — Phase 1 Hardening (1.5, 1.6, 1.7, 2.7)

**Konteks**: Phase 1 memiliki 8 item. 1.1-1.4 sudah selesai di sesi sebelumnya.
1.5-2.7 perlu diselesaikan untuk menutup celah keamanan (`access_expiry`),
observability, dan struktur kode sebelum masuk Phase 2.

**Keputusan**:
- 1.5: Extract `CertificateService` + `EnrollmentService` dari model/controller agar proses bisnis bisa diuji terpisah dan dipanggil dari listener.
- 1.6: `PlayerBreakdown.php` sebagai struktur pemecahan `Player` (640 baris) tanpa merusak komponen utama — integrasi bertahap.
- 1.7: `LogDomainEvent` listener + `domain` log channel (JSON) untuk audit trail transaksi (bukan hanya debug).
- 2.7: `access_expires_at` enforcement di `User::canAccessProgram()` dan `canAccessBatch()` menggunakan `first()` (bukan `exists()`) agar bisa membaca kolom tanggal kedaluwarsa.

**Konsekuensi**:
- Semua akses sekarang diperiksa tanggal kedaluwarsa (menutup P1 #3).
- Log JSON bisa di-query tanpa regex (dasar Sentry/Telescope di 1.7 lanjutan).
- `CertificateService` siap untuk integrasi PDF generation (1.5 lanjutan).
- `EnrollmentService` menyatukan logika re-aktivasi (`dropped` → `active`) yang sebelumnya tersebar.
- Struktur `PlayerBreakdown` memberikan dasar refactor tanpa risiko produksi.
- Catatan historis semula menyebut Phase 1 100% selesai (8/8); klaim tersebut
  dikoreksi oleh ADR-012 di bawah, bukan bukti pemecahan Player selesai.

---

## ADR-012 — Batas klaim implementasi dan verifikasi Phase 2

**Konteks**: model/struktur awal dan checklist lama belum menggambarkan alur
operasional lengkap atau bukti test terbaru.

**Keputusan**: status `[x]` Phase 2 berarti implementasi tersedia, dengan
verifikasi akhir eksplisit pending. Scope Phase 2 tidak menyatakan pemecahan penuh
Player atau integrasi Sentry/Telescope selesai. Rekap operasional di `PHASE2.md`.

**Konsekuensi**: hasil test, jumlah kelulusan, pemeriksaan runtime dan batasannya
harus diisi pelaksana validasi setelah benar-benar dijalankan. Angka Phase 0
bukan hasil terbaru. Tidak ada dependency baru untuk keputusan Phase 2 ini.

---

## ADR-013 — Renewal lewat invoice manual saat expiry

**Konteks**: recurring membership diperlukan tanpa mandat/token auto-debit.

**Keputusan**: `SubscriptionService` + `subscriptions:invoice` hourly membuat
invoice transfer manual **saat expiry**, bukan di muka. Satu outstanding invoice,
harga plan saat invoice dibuat; akses diperpanjang hanya lewat fulfillment lunas.
Lifetime tidak diinvoiskan. Cancel membatalkan renewal/pending invoice tanpa
mencabut akses terbayar; reaktivasi hanya mengaktifkan jadwal yang memenuhi syarat.

**Konsekuensi**: perlu scheduler, bank config, verifikasi admin, dan queue worker
untuk email. `MembershipGrant` menyimpan snapshot dan urutan grant per user.
Tidak ada backfill otomatis subscription/grant legacy; tidak ada auto-debit Midtrans.

---

## ADR-014 — Refund penuh manual dengan ledger sumber akses

**Konteks**: reversal enrollment langsung dapat mencabut akses dari pembayaran
lain; pengurangan durasi membership tidak aman saat plan/state berubah.

**Keputusan**: admin mencatat refund penuh yang sudah ditransfer manual melalui
`RefundService`, bukti privat disk `local`, hash, alasan, dan audit. Satu `Refund`
per transaksi; `refunded_at` terpisah dari enum payment status.
`EnrollmentPaymentSource` melindungi pembayaran lain yang belum direfund serta
akses aktif pra-ledger melalui `preserves_existing_access`.

Refund membership hanya untuk `MembershipGrant` terbaru yang belum direversal
(`grant_order`) dan cocok dengan state user; pulihkan snapshot sebelumnya,
hentikan renewal dan invalidasi invoice pending. Grant lama/legacy atau perubahan
manual yang tidak cocok ditolak untuk rekonsiliasi. Reaktivasi kemudian tetap
mengikuti syarat billing/checkout, bukan otomatis saat refund.

**Konsekuensi**: tidak ada refund parsial/API refund gateway; transaksi legacy
memerlukan rekonsiliasi. Refund korporat melepaskan reservasi dan mencabut hanya
sumber invoice itu. Riwayat/alokasi seat tetap tersimpan; kuota kupon tidak kembali.

---

## ADR-015 — Reservasi kupon atomik dan seat korporat milik owner

**Konteks**: checkout pending/settlement terlambat bisa menghabiskan kupon ganda;
seat lunas belum dialokasikan tetap harus dihitung dalam kuota batch.

**Keputusan**: checkout Program/Batch/MembershipPlan memakai satu kupon tanpa
stacking, snapshot harga awal/diskon/total, reservasi atomik dan redeem idempoten.
Reservasi pending hanya dilepas saat invoice eksplisit gagal, bukan lewat timeout
lokal. Kupon korporat dilarang agar total invoice tetap harga × seat count.

Pembeli terverifikasi menjadi owner/HR, tanpa role HR baru atau auto-enrollment.
Hanya owner dapat mengalokasikan akun student aktif yang sudah ada hingga jumlah
kursi lunas; alokasi permanen. Kursi batch lunas belum teralokasi mereservasi kuota;
alokasi mengonversi reservasi ke enrollment melalui guard kapasitas yang sama.

**Konsekuensi**: admin lain tidak otomatis berhak mengalokasikan seat. Refund
memblokir alokasi lanjutan; audit seat dan sumber akses tidak dihapus.

---

## ADR-016 — Honor tetap, waitlist reservasi, clone konfigurasi, absensi manual

**Konteks**: operasional batch diperlukan tanpa menetapkan revenue sharing atau
aturan sertifikat berbasis absensi yang belum disetujui.

**Keputusan**: admin menetapkan honor tetap instruktur terkait/batch dan mencatat
payout manual dengan referensi, tanggal, bukti privat `local`, hash dan audit.
Honor lunas immutable; instruktur hanya melihat payout miliknya.

Promosi waitlist oleh admin mengikuti FIFO `queue_number` dan hanya mereservasi
kapasitas, bukan enrollment/bebas bayar. Clone menyalin whitelist konfigurasi dan
relasi instruktur, tanpa data operasional. Absensi dicatat admin/instruktur terkait
(`present|absent|excused`) dengan audit; tidak menjadi syarat sertifikat.

**Konsekuensi**: tidak ada transfer payout otomatis, revenue share, sinkronisasi
absensi Zoom, atau pemindahan peserta saat clone. Tanggal/status clone perlu ditinjau.

---

## ADR-017 — Esai pending hingga seluruh nilai manual final

**Konteks**: esai yang dilewati grader otomatis atau completion dari klien dapat
menerbitkan progres/sertifikat sebelum hasil belajar sah.

**Keputusan**: submission esai tetap pending sampai **semua** esai, termasuk
kosong/timeout, mendapat nilai 0..`score_weight` dan feedback dari instruktur pemilik
course/admin. Nol adalah nilai sah. `QuizGrader` menggabungkan bobot otomatis/manual;
attempt submitted tidak dapat diedit/resume dan tetap memakai jatah percobaan.
Player/QuizPlayer, `QuizService::hasPassed()` dan `CertificateService::assertEligible()`
menjaga completion/sertifikat di server berdasarkan kelulusan final.

**Konsekuensi**: pending/gagal tidak meluluskan lesson atau sertifikat, bahkan
jika baris completion dipalsukan. Absensi tetap terpisah. Migration esai menyesuaikan
metadata attempt lama; bukan penilaian ulang otomatis seluruh esai legacy.

---

## ADR-018 — Penutupan lokal Phase 2 dan pengamanan integrasi

**Konteks**: setelah implementasi, callback terlambat, replay event, snapshot
REPEATABLE READ saat refund, serta expiry hak legacy perlu dijaga lintas fitur.
Default disk Google juga tidak layak untuk bukti keuangan privat.

**Keputusan**: transisi lunas mengunci invoice dan tidak menghidupkan invoice gagal;
callback pending/challenge tidak menurunkan status terminal. Enrollment menyimpan
ledger sumber, snapshot akses legacy, dan reversal; evaluasi refund memakai locking
reads. Replay sumber yang sudah diproses tidak mengaktifkan enrollment lagi.
Bukti invoice memakai POST multipart langsung ke `BillingProofService`, disk `local`
privat; halaman billing menampilkan seluruh invoice milik user. Revenue/histori
memperhitungkan `refunded_at`, tanpa mengubah enum historis pembayaran.

**Bukti penutupan (1 November 2026)**: 234 test/826 assertion lulus pada `sbu_test`,
smoke manual 11 PASS tanpa error log baru, Pint/lint 71 PHP lulus, Blade cache dan
build outdir sementara lulus. Tujuh migration baru diterapkan ke `sbu` lokal setelah
backup privat, tanpa reset data. Ini melengkapi status verifikasi yang masih pending
saat ADR-012 ditulis; bukan menghapus riwayat keputusan tersebut.

**Konsekuensi**: rekening resmi, runner scheduler dan worker terkelola tetap menjadi
konfigurasi operator. Tidak ada auto-debit, transfer dana, SMTP/Midtrans nyata,
deployment produksi, dependency baru, atau commit yang dijalankan dalam validasi.

---

## ADR-019 — Integrasi Sentry dan Telescope untuk observabilitas Phase 1.7

**Konteks**: Phase 1.7 (`Observability`) hanya memiliki `LogDomainEvent` dan channel `domain`. Untuk audit lengkap dan tracking error produksi diperlukan integrasi pihak ketiga.

**Keputusan**: Menambahkan dependency `sentry/sentry` dan `laravel/telescope` (atau setara) ke `composer.json`. Konfigurasi `LogDomainEvent` tetap dipertahankan sebagai layer domain, sementara Sentry menangani tracking exception dan Telescope menyediakan dashboard query/debug.

**Konsekuensi**: Dependency baru ditambahkan (`docs/DECISIONS.md` mencatatnya); konfigurasi `.env` perlu `SENTRY_DSN`; `LogDomainEvent` tidak dihapus agar tetap tersedia tanpa Sentry aktif; `domain` log channel tetap sebagai sumber audit utama.
