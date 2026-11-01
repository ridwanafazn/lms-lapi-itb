# PHASE 2 — Rekap Operasional

**Status: Phase 2.1–2.8 selesai diimplementasikan dan divalidasi lokal, 1 November 2026.**
Seluruh suite: **234 passed, 826 assertions, 0 failed**. Smoke manual lintas fitur
lulus dengan data yang di-rollback. Tujuh migration baru sudah diterapkan pada
`sbu` lokal setelah backup. Hasil rinci ada di [TESTING.md](TESTING.md).
Ini bukan klaim auto-debit, pengiriman dana/email nyata, atau deployment produksi.

## 1. Batas perilaku yang disetujui

| Area | Perilaku operasional |
|------|---------------------|
| Renewal | Invoice transfer manual pada expiry, bukan auto-debit atau invoice di muka. Command `subscriptions:invoice` hourly, satu outstanding invoice. Akses baru setelah pembayaran diverifikasi; lifetime tanpa invoice. Cancel mempertahankan akses terbayar; reaktivasi hanya jadwal sesuai syarat. |
| Refund | Admin mencatat refund penuh yang sudah dilakukan manual, alasan + bukti privat/hash/audit. Tidak ada refund parsial/gateway otomatis. Sumber akses lain tetap dilindungi. |
| Membership refund | Hanya grant terbaru yang belum direversal dan cocok state user, menurut `grant_order`; pulihkan snapshot sebelumnya, hentikan renewal dan invalidasi pending invoice. Grant lama/legacy/state mismatch ditolak untuk rekonsiliasi; reaktivasi selanjutnya mengikuti syarat billing/checkout. |
| Kupon | Checkout normal Program/Batch/MembershipPlan, satu kupon tanpa stacking, snapshot harga/diskon/total, reservasi/redeem atomik. Pending tidak dilepas hanya karena waktu berlalu; invoice eksplisit gagal melepas reservasi. Refund tidak mengembalikan kuota terpakai. |
| Korporat | Kupon dilarang; invoice harga per kursi × jumlah kursi. Owner/HR pembeli terverifikasi mengalokasikan akun student aktif yang sudah ada, maksimal kursi lunas, permanen/tidak dapat diganti. Tidak auto-enroll pembeli; admin lain tidak otomatis berhak alokasi. |
| Kapasitas batch | Enrollment dan promosi waitlist dihitung bersama kursi korporat lunas yang belum dialokasikan. Alokasi korporat mengonversi reservasi, bukan menambah kapasitas baru. Refund melepas reservasi dan memblokir alokasi berikutnya. |
| Payout | Admin menetapkan honor tetap per instruktur terkait/batch, transfer manual dengan referensi/tanggal/bukti privat/hash/audit. Bukan revenue sharing/transfer otomatis; honor paid tidak dapat diubah/dibayar dua kali. |
| Waitlist | Siswa join batch open; admin promosi FIFO `queue_number` hanya mereservasi kursi, bukan enrollment atau pembebasan bayar. Cancel melepas reservasi; rejoin ke belakang antrean. |
| Clone | Admin menyalin whitelist konfigurasi + relasi instruktur, tidak menyalin enrollment/transaksi/payout/waitlist/absensi. Tanggal dan status hasil clone harus ditinjau. |
| Absensi | Manual oleh admin/instruktur terkait, `present|absent|excused`, audit perubahan, bukan sinkronisasi Zoom dan bukan syarat sertifikat. |
| Esai | Submit menjadi pending sampai seluruh esai, termasuk kosong/timeout, diberi nilai 0..bobot + feedback oleh pemilik course/admin. Nol sah. Skor gabungan otomatis/manual baru final setelah semua dinilai. Pending/gagal tidak membuka completion/sertifikat; kelayakan diperiksa lagi di server. |

`EnrollmentPaymentSource` melindungi enrollment dari pencabutan menyeluruh saat
refund: pembayaran lain yang belum direfund dan akses aktif pra-ledger bertanda
`preserves_existing_access` tetap dipertahankan sesuai snapshot status/expiry aslinya,
bukan menjadi akses permanen setelah expiry legacy lewat. Refund korporat hanya mencabut
sumber invoice itu; riwayat seat tidak dihapus. `refunded_at` terpisah dari status
pembayaran; `Transaction::isPaid()` menolak transaksi yang sudah direfund dan replay
pembayaran tidak boleh mengaktifkan kembali akses.

Enforcement expiry enrollment tersedia di `User::canAccessProgram()` dan
`canAccessBatch()`; membership yang memenuhi syarat tetap jalur akses terpisah.
Phase 2 tidak menyatakan pemecahan penuh Player atau seluruh hardening Phase 1 selesai.

## 2. Peta halaman dan service

Semua route Phase 2 dimuat oleh `routes/web.php`, dengan middleware auth/verified
serta pembatasan role/owner/cakupan di route, komponen, dan service terkait.

| Route file | Halaman/alur | Implementasi utama |
|------------|--------------|--------------------|
| `routes/phase2-billing.php` | `/billing`, `/billing/invoices/{invoice}`, unduh bukti owner/admin | `Billing\Invoices`, `SubscriptionService`, `Subscription`, `MembershipGrant` |
| `routes/phase2-commerce.php` | `/corporate`, `/admin/coupons`; checkout dari program detail/membership | `Commerce\CorporateDashboard`, `Commerce\CouponManager`, `CheckoutService`, `CouponService`, `CorporatePurchaseService`, `CouponRedemption`, `CorporateSeat` |
| `routes/phase2-operations.php` | `/admin/payouts`, `/admin/batch-operations`, `/admin/attendance`, `/instructor/payouts`, `/instructor/attendance`, `/waitlist`, unduh bukti payout | `Operations\*Page`, `PayoutService`, `BatchWaitlistService`, `BatchCloneService`, `LiveAttendanceService`, `OperationsAccess` |
| `routes/phase2-essays.php` | `/grading/essays/{attempt?}` | `Instructor\EssayGrading`, `EssayGradingService`, `QuizService`, `QuizGrader`, Player/QuizPlayer, `CertificateService` |
| `routes/phase2-refunds.php` | `/admin/refunds`, POST refund invoice, unduh bukti admin-only | `Admin\Refunds`, `RefundService`, `Refund`, `EnrollmentPaymentSource`, `EnrollmentService` |

Nama komponen pada tabel relatif terhadap `app/Livewire/`, service terhadap
`app/Services/`, model terhadap `app/Models/`. View berada di subtree
`resources/views/livewire/` yang sesuai. Bukti payout/refund dikirim lewat POST
multipart langsung, termasuk bukti invoice melalui `BillingProofService` (tidak melalui
temporary upload Google Drive). Halaman billing mencakup seluruh invoice milik user.
Tidak ada hak alokasi seat korporat lintas owner hanya karena admin.

## 3. Migration dan data lama

Migration berikut additive terhadap riwayat migration sebelumnya; jangan mengedit
migration lama yang sudah diterapkan di produksi:

| Migration `database/migrations/2026_11_01_*` | Perubahan |
|--------------------------------------------|-----------|
| `000008_create_subscription_billing.php` | `subscriptions`, `membership_grants`, relasi subscription pada transaksi |
| `000010_create_phase2_commerce_tables.php` | `coupons`, `coupon_redemptions`, `corporate_purchases`, `corporate_seats`, snapshot diskon/relasi transaksi |
| `000020_create_payouts_tables.php` | Honor/payout dan audit |
| `000021_create_batch_waitlists_table.php` | Antrean/reservasi batch |
| `000022_create_live_attendances_table.php` | Absensi live session |
| `000030_add_manual_essay_grading.php` | Metadata submitted/graded dan nilai esai nullable; penyesuaian metadata attempt lama |
| `000040_create_refund_ledger.php` | `refunded_at`, `enrollment_payment_sources`, `refunds` |

**Tidak ada backfill otomatis subscription, membership grant, atau sumber
pembayaran enrollment legacy.** Membership lama tetap state pada user, bukan
jaminan sudah memiliki jadwal renewal/ledger. Rekonsiliasi data lama secara eksplisit;
jangan replay pembayaran lama atau memanggil reversal langsung untuk melewati guard.
Migration esai mengisi metadata submitted/graded untuk attempt lama dan menetralkan
nilai default esai pada attempt belum final; bukan regrade massal attempt final.

## 4. Checklist deployment operator

Pada sesi ini backup DB lokal dibuat di `storage/app/phase2-backups/pre-phase2.sql`
(privat, diabaikan Git), kemudian `php artisan migrate --force` menerapkan tujuh migration
baru ke `sbu` lokal. Semua migration berstatus Ran. Untuk runtime lain, ulangi checklist
berikut secara terkontrol; jangan menjalankan test pada database development/produksi.

1. Backup DB **dan** private storage; review migration pending. Terapkan migration
   secara terkontrol (`php artisan migrate --force` pada deployment yang disetujui),
   bukan `migrate:fresh` dan bukan perubahan migration lama.
2. Isi `config/billing.php` melalui variabel runtime berikut; default kosong:
   - `BILLING_BANK_NAME`
   - `BILLING_BANK_ACCOUNT_NUMBER`
   - `BILLING_BANK_ACCOUNT_NAME`
   - `BILLING_BANK_INSTRUCTIONS`
3. Pastikan disk `local` privat writable dan ikut backup. Bukti final disimpan di
   `billing/proofs/` (maksimum 5 MB), `payout-evidence/` dan `refund-evidence/`
   (maksimum 10 MB), PDF/JPG/PNG. Unduh melalui route terotorisasi, jangan public URL.
   Media Google Drive tidak menggantikan penyimpanan bukti keuangan.
4. Sesudah perubahan env/config/route, jalankan `php artisan optimize:clear` dan
   terapkan kembali cache config sesuai prosedur deployment.
5. Task Scheduler Windows/cron memanggil `php artisan schedule:run` **tiap menit**
   dari root aplikasi dengan PHP/env yang benar. Schedule di `routes/console.php`:
   `subscriptions:invoice` **hourly**, `withoutOverlapping()`.
6. Operasikan queue worker terkelola (`php artisan queue:work --tries=3`) untuk
   email pembayaran, dan restart worker setelah deployment sesuai prosedur.
7. Verifikasi route, private evidence, rekening/instruksi, due invoice, manual
   approval, cancel/refund, log, dan queue pada `https://LMS-V2.laragon` untuk lokal.
   Jangan memakai `php artisan serve`.

`php artisan subscriptions:invoice` adalah command **menulis invoice**, bukan
dry-run: operator dapat memanggilnya secara manual setelah review. Ia tidak
mendebit rekening, tidak memperpanjang akses, dan tidak memajukan jadwal expiry
hanya karena invoice dibuat. Pantau log `Membership renewal invoice failed` dan
exit status command; jangan menganggap scheduler berhasil hanya karena terpasang.

## 5. Hasil validasi akhir dan batas operasional

Definisi test tersedia di:
`tests/Feature/Phase2BillingTest.php`, `Phase2CommerceTest.php`,
`Phase2OperationsTest.php`, `Phase2EssayGradingTest.php`, `Phase2RefundTest.php`.
Cakupan, perintah regresi, dan smoke checklist ada di [TESTING.md](TESTING.md).
Keberadaan test **bukan bukti lulus** dan tidak menjamin setiap edge case sudah tercakup.

- [x] `php artisan test`: **234 passed, 826 assertions**, hanya `sbu_test`.
- [x] `php tests/Manual/phase2-smoke.php`: **11 PASS**, termasuk guard DB, workflow,
      GET halaman per peran dan rollback; nol ERROR/CRITICAL/EMERGENCY baru selama smoke.
- [x] PHP lint dan Pint `--test`: **71 file PHP** yang berubah lulus.
- [x] `php artisan view:cache` lulus; lint PHP hasil kompilasi Blade lulus.
- [x] `npm run build -- --outDir storage/app/phase2-build-check` lulus.
      `public/build/` tidak diubah; output sementara bukan artefak deployment.
- [x] Route dan schedule diperiksa; `subscriptions:invoice` terdaftar hourly.
- [x] `curl` homepage Laragon menghasilkan HTTP 200. Smoke authenticated dijalankan
      melalui kernel Laravel pada DB test, bukan klik browser terhadap data development.

**Masih perlu konfigurasi operator, bukan fitur yang belum diimplementasikan:**
rekening resmi `BILLING_BANK_*` belum tersedia (`bank_configured=false`); runner
Task Scheduler/cron dan worker terkelola tidak dipasang/dijalankan oleh agent.
SMTP/email dan transaksi Midtrans nyata tidak diuji (test memakai fake/mock),
dan tidak ada deployment produksi. Data legacy perlu rekonsiliasi terpisah.
Build memberi peringatan database Browserslist lama; build tetap lulus, dependency
lock tidak diubah. Backup DB berisi data privat: jangan dipublikasikan/di-commit.
`SESSION_HANDOFF.md` milik user tidak diubah, dan tidak ada commit otomatis.
