# DOMAIN — Aturan Bisnis

Semua yang ada di sini adalah **aturan bisnis**. Jangan diubah tanpa persetujuan user.

## 1. Peta entitas

```
Program (paket)
├── courses[]            (pivot: program_courses + order_index)
├── batches[]            (cohort terjadwal)
└── enrollments[]        (polymorphic)

Course (kelas)
├── sections[]           (urut: order_index)
│   └── lessons[]        (urut: sort_order, polymorphic item)
│       ├── Material     (article | video | google_drive_video | google_drive_file | live_session)
│       ├── Quiz         (questions → options)
│       └── Assignment   (submissions)
├── discussions[]
└── testimonials[]

Batch (cohort)
├── program_id / course_id  (salah satu atau keduanya)
├── quota, status: open | ongoing | closed
├── start_date, end_date, registration_deadline  (disimpan JSON array)
└── min_membership_level: none | standard | pro

User
├── profile (1:1)
├── roles: admin | instructor | student   (Spatie)
├── membership_plan_id + membership_expires_at
├── enrollments[]        (polymorphic: Program | Batch | Course)
└── transactions[]

Transaction
├── invoice_code (unik)
├── program_id / batch_id / membership_plan_id
├── payment_status: pending | paid | failed | verified_by_admin
├── refunded_at          (refund penuh dicatat terpisah, bukan status pembayaran baru)
├── subscription_id / coupon_id / corporate_purchase_id
└── gateway: midtrans / manual
```

## 2. Jalur akses belajar

Seorang user boleh mengakses course jika **salah satu** terpenuhi
(`User::hasAccessToCourse`):

1. **Admin** — selalu boleh.
2. **Instruktur pemilik** — `coursesCreated()` berisi course tersebut.
3. **Beli putus program** — punya `enrollment` aktif bertipe `Program` yang memuat course itu.
4. **Beli batch** — punya `enrollment` aktif bertipe `Batch` untuk course tersebut.
5. **Membership** — plan aktif dan memenuhi syarat program/batch
   (`HasMembershipRequirement`).

Urutan pengecekan di `User::hasAccessToCourse`: admin → instruktur →
program → batch. Jalur enrollment pada `canAccessProgram()` dan
`canAccessBatch()` mensyaratkan `status = active` dan `access_expires_at`
belum lewat (atau null). Membership yang memenuhi syarat adalah sumber akses
terpisah; refund satu transaksi tidak boleh menghapus sumber akses lain.

## 3. Aturan membership

Satu sumber kebenaran: `App\Models\Concerns\HasMembershipRequirement`
(dipakai `Program` dan `Batch`).

**Syarat wajib**: `User::hasActiveMembership()` = punya plan dan
(`membership_expires_at` null untuk lifetime **atau** masih di masa depan).

Urutan evaluasi `isFreeForUser()`:

1. `required_membership_plan_id` terisi → **hanya** pemilik plan itu yang gratis.
2. Selain itu, bandingkan level: `min_membership_level`
   (`none|standard|pro`). Peringkat: starter 0 < standard 1 < pro 2.
3. `none` berarti **tidak termasuk membership** (harus beli).

| Level user | Program/batch `standard` | `pro` |
|------------|--------------------------|-------|
| starter | ❌ | ❌ |
| standard | ✅ | ❌ |
| pro | ✅ | ✅ |

- `billing_period` yang dikenal: `monthly`, `quarterly`, `semiannual`, `yearly`, `lifetime`.
- **Perpanjangan menumpuk**: jika user masih aktif lalu membeli lagi,
  masa baru dihitung dari `membership_expires_at` lama, bukan dari `now()`.
- `User::accessible_programs` = program dari enrollment aktif **+** program yang
  levelnya tercakup membership (atau plan eksplisitnya cocok).
- **Cara admin mengatur gating**: buka `admin/programs` → tombol buat/edit program
  → field **Membership Level Diperlukan** (`none|standard|pro`). Nilai disimpan
  di `programs.min_membership_level`. Kolom `required_membership_plan_id`
  belum punya field UI (diisi manual/lewat seeder) — lihat `docs/KNOWN_ISSUES.md`.

## 4. Aturan pembayaran (setelah refactor Phase 0)

`Transaction::markAsPaid()` dan `approveTransaction()` melewati transaksi DB
serta lock invoice yang sama: validasi status pending/belum direfund, tandai lunas,
redeem kupon secara idempoten, lalu dispatch event `TransactionPaid`.

Fulfillment akses, audit pembayaran, dan email berada di listener (`app/Listeners`):

| Listener | Efek | Sinkron? |
|----------|------|----------|
| `FulfillEnrollment` | redeem reservasi kupon; enrollment + ledger sumber pembayaran, atau tandai pembelian korporat lunas tanpa enroll pembeli | ya |
| `ActivateMembership` | delegasi ke `SubscriptionService`: set plan/expiry + snapshot `MembershipGrant` + jadwal renewal | ya |
| `RecordPaymentActivity` | tulis `activity_logs` | ya |
| `SendPaymentConfirmation` | kirim email kuitansi | **queued** |

Alasan email di-queue: SMTP mati tidak boleh membatalkan pembayaran yang sudah lunas.

**Idempoten**: `markAsPaid()` dan `approveTransaction()` langsung berhenti jika
transaksi sudah lunas, sehingga webhook ganda tidak menggandakan enrollment.

## 5. Alur sertifikat

1. Siswa menyelesaikan **100%** lesson (`lesson_completions`) dan lulus seluruh kuis.
2. Kuis beresai harus selesai dinilai seluruhnya; pending atau gagal tidak membuka completion.
3. Player menampilkan form testimoni. Aksi submit testimoni dan
   `CertificateService::issue()` memeriksa kelayakan ulang di server;
   completion yang dipalsukan tidak cukup jika kuis belum lulus.
4. Setelah testimoni dikirim → `Certificate` dibuat dengan kode unik dan nomor formal.
5. Verifikasi publik: `/certificates/{code}` + PDF ber-QR.

Absensi live session tidak menjadi syarat sertifikat.

Sertifikat **tidak** dibuat saat pembayaran — harus lewat penyelesaian materi.

## 6. Status course (alur instruktur → admin)

```
draft → pending → published
                ↘ rejected (dengan moderation_note)
```

Instruktur mengajukan lewat `CourseBuilder::submitForReview()`, yang menolak jika:
deskripsi kosong, belum ada bab, atau belum ada materi/quiz/assignment.

## 7. Harga

`Batch::getEffectivePriceAttribute()`:

1. jika `batches.price > 0` → pakai harga batch,
2. jika `0` dan punya program → pakai `programs.price`,
3. selain itu → gratis.

## 8. Tugas (assignment)

- Berkas disimpan oleh `AssignmentSubmissionService` di disk `public`,
  folder `assignments/{assignment_id}`.
- Format yang diizinkan: pdf, doc/docx, ppt/pptx, xls/xlsx, zip/rar, txt, csv, png/jpg/jpeg.
- Ukuran maksimum 20 MB.
- Satu siswa hanya punya **satu** submission per assignment per batch
  (`submission` kelas mandiri memakai `batch_id = null`).
- Mengirim ulang mengganti berkas lama dan **me-reset** `grade`/`feedback`.
- Submission yang sudah dinilai tidak bisa dihapus siswa.

## 9. Kuis

- Attempt disimpan di `quiz_attempts`; jawaban di `quiz_answers`.
- `max_attempts = 0` berarti tanpa batas. Nilai `null` untuk `remainingAttempts`
  juga berarti tanpa batas.
- Hanya attempt yang belum disubmit (`submitted_at`/`end_time`/`score` kosong)
  yang dilanjutkan. Attempt pending penilaian sudah memakai jatah percobaan
  dan jawabannya tidak boleh diedit lagi.
- `is_randomized = true` → urutan soal diacak **per attempt** dan stabil saat
  halaman dimuat ulang (seed dari ID attempt).
- Batas waktu dihitung dari `start_time` di server, bukan timer browser.
  Toleransi 15 detik saat submit; melewati itu → `is_timeout = true`.
- Penilaian membaca `quiz_answers` dari database (`QuizGrader`), bukan state komponen.
- Soal dengan lebih dari satu opsi benar dianggap **multi-jawaban**: seluruh
  opsi benar harus dipilih tepat (tanpa opsi salah) untuk mendapat poin.
- Soal `essay` dinilai manual oleh instruktur pemilik course (`instructor_id`)
  atau admin melalui `EssayGradingService`: nilai **0 sampai `score_weight`**
  (maksimum dua desimal) dan feedback wajib. Nol adalah nilai sah, bukan pending.
- Seluruh esai, termasuk jawaban kosong/timeout, harus dinilai sebelum skor akhir
  dihitung dari gabungan bobot otomatis + manual. Pending tetap `score = null`.
- Kelulusan mengikuti `passing_grade` (default 70); completion lesson dan
  penerbitan sertifikat memeriksa kelulusan final di server.

## 10. Billing membership Phase 2

- `SubscriptionService` membuat **invoice renewal transfer manual**, bukan
  Midtrans Subscription atau auto-debit. `subscriptions:invoice` dijadwalkan
  setiap jam; invoice dibuat saat expiry tercapai, bukan sebelum expiry.
- `next_billing_at` mengikuti expiry membership. Satu invoice outstanding per
  subscription; pembuatan invoice/upload bukti tidak memperpanjang akses.
  Harga renewal mengikuti harga plan saat invoice dibuat.
- Pembayaran terverifikasi membuat `MembershipGrant` berurutan (`grant_order`)
  dengan snapshot plan/expiry sebelumnya. Pembelian saat masih aktif menumpuk expiry.
- Lifetime tidak menghasilkan invoice renewal. Cancel menghentikan renewal dan
  menonaktifkan invoice pending, tetapi mempertahankan akses yang sudah dibayar.
  Reaktivasi yang memenuhi syarat hanya menghidupkan jadwal, bukan memberi akses.
- Tidak ada backfill otomatis subscription/grant membership legacy.

## 11. Refund penuh manual

- Admin mencatat pengembalian dana yang **sudah dilakukan manual** melalui
  `RefundService`, dengan alasan dan bukti PDF/JPG/PNG (maksimum 10 MB),
  disimpan privat pada disk `local` dengan hash dan audit. Tidak ada refund gateway
  otomatis atau refund parsial; satu refund per transaksi lunas bernilai positif.
- `EnrollmentPaymentSource` menghubungkan transaksi dan enrollment. Reversal
  hanya menutup akses sumber tersebut jika tidak ada pembayaran lain yang masih
  lunas/tidak direfund dan tidak ada penanda akses lama tanpa ledger yang dilindungi
  (`preserves_existing_access`). Transaksi legacy tanpa sumber menuntut rekonsiliasi.
- Refund membership **hanya** boleh untuk grant terbaru yang belum direversal dan
  cocok dengan state membership user saat ini. Snapshot plan/expiry sebelumnya
  dipulihkan; grant lama, legacy, atau state hasil perubahan manual ditolak untuk
  rekonsiliasi, bukan ditebak/dikurangi durasinya.
- Refund membership menghentikan renewal dan menonaktifkan invoice pending;
  reaktivasi kemudian tetap mengikuti syarat `SubscriptionService` atau checkout baru.
- Refund korporat melepaskan reservasi kursi, memblokir alokasi berikutnya, dan
  mereversal hanya sumber akses invoice tersebut. Kuota kupon **tidak dikembalikan**.

## 12. Kupon dan pembelian korporat

- Checkout biasa Program/Batch/MembershipPlan menerima satu kupon `percentage`
  atau `fixed_amount` tanpa stacking. Invoice menyimpan harga awal, diskon, dan total.
- `CouponService` mereservasi kuota secara atomik; pembayaran me-redeem sekali.
  Reservasi pending tidak lepas hanya karena waktu berlalu (mengantisipasi settlement
  terlambat); invoice yang eksplisit gagal melepaskan reservasi yang belum dipakai.
- Kupon **tidak diizinkan untuk korporat**, agar total invoice sama dengan harga
  per kursi × jumlah kursi.
- Pembeli terverifikasi bertindak sebagai owner/HR melalui `/corporate` (bukan role
  HR baru). Setelah lunas, owner mengalokasikan akun student aktif yang sudah ada,
  maksimal sejumlah kursi dibayar. Alokasi permanen, tidak bisa dipindah/ganti peserta.
  Admin lain tidak mendapat hak alokasi hanya karena rolenya.
- Pembeli tidak otomatis ter-enroll. Untuk batch, seluruh kursi lunas yang belum
  dialokasikan ikut mereservasi kapasitas; alokasi mengubah reservasi menjadi enrollment.

## 13. Operasional instruktur dan batch

- **Payout**: admin menetapkan honor tetap per instruktur terkait/batch, bukan
  persentase revenue. Transfer dilakukan manual lalu dicatat dengan referensi,
  tanggal, bukti privat `local` (maksimum 10 MB), hash, dan `PayoutAudit`.
  Honor lunas tidak dapat diubah/dibayar dua kali; instruktur hanya melihat miliknya.
- **Waitlist**: siswa bergabung pada batch open; admin mempromosikan FIFO menurut
  `queue_number`. Promosi hanya reservasi kapasitas, **bukan enrollment atau bebas
  bayar**. Fulfillment biasa tetap memeriksa kuota; cancel melepas reservasi,
  bergabung kembali masuk belakang antrean.
- **Clone batch**: admin menyalin whitelist konfigurasi dan relasi instruktur,
  bukan enrollment, transaksi, payout, waitlist, absensi, atau data operasional lain.
  Tanggal/status yang tersalin perlu ditinjau sebelum membuka cohort baru.
- **Absensi**: admin/instruktur terkait mencatat `present|absent|excused` untuk
  peserta enrolled pada live session dalam cakupannya, dengan audit perubahan.
  Tidak otomatis mengambil data Zoom dan tidak memengaruhi gating sertifikat.

Implementasi di atas tersedia; verifikasi akhir Phase 2 masih berjalan.
Lihat [PHASE2.md](PHASE2.md) untuk rekap operasional dan [TESTING.md](TESTING.md)
untuk cakupan test, bukan klaim seluruh skenario sudah lulus.
