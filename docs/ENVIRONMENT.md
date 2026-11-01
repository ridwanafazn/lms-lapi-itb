# ENVIRONMENT — Cara Menjalankan Project

Stack lokal: **Laragon** (Apache + MySQL), PHP 8.3, Node 24, Vite 5.

## 1. URL & port

| Layanan | URL / Port | Catatan |
|---------|------------|---------|
| Aplikasi | `https://LMS-V2.laragon` | **Selalu pakai ini**, bukan `127.0.0.1:8000` |
| Vite (HMR) | `http://localhost:5173` | Proxy otomatis oleh Laravel plugin |
| MySQL | `127.0.0.1:3306` | user `root`, password `root` |
| Redis | `127.0.0.1:6379` | Terpasang, **belum dipakai** (queue & cache masih `database`) |
| Mailpit SMTP | `127.0.0.1:1025` | Semua email dev masuk ke sini |
| Mailpit UI | `http://127.0.0.1:8025` | Lihat email yang terkirim |

> **Kenapa bukan port 8000?** `APP_URL=https://lms-v2.laragon` dipakai Blade
> untuk membangun URL asset & redirect. Membuka lewat IP/port lain membuat
> aset dimuat dari origin berbeda → halaman tampil tanpa CSS.

## 2. Service yang harus hidup

| Service | Cara menyalakan | Wajib? |
|---------|-----------------|--------|
| Apache | Laragon → Start All | ✅ wajib |
| MySQL | Laragon → Start All | ✅ wajib |
| Vite | `npm run dev` | ✅ wajib saat kerja UI |
| Queue worker | `php artisan queue:work --tries=3` | ✅ wajib saat kerja event/listener/mail |
| Scheduler runner | Task Scheduler/cron memanggil `php artisan schedule:run` tiap menit | ✅ untuk invoice renewal Phase 2 |
| Mailpit | Laragon → Start All | ⬜ untuk test email |
| Redis | Laragon → Start All | ⬜ opsional |

Service **tidak perlu**: `php artisan serve`, `php-cgi` (Apache memakai `mod_php`).

## 3. Database

| Nama | Fungsi |
|------|--------|
| `sbu` | Database development |
| `sbu_test` | Database test (dipakai `phpunit.xml`, di-`migrate:fresh` tiap run) |

Jangan mengarahkan test ke `sbu` — `RefreshDatabase` akan menghapus datanya.

## 4. Setup dari nol

```powershell
cd C:\laragon\www\LMS-V2

composer install
npm install
copy .env.example .env      # lalu sesuaikan (lihat bagian 5)
php artisan key:generate

php artisan migrate
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=MembershipPlanSeeder
php artisan db:seed --class=DatabaseSeeder   # akun demo

npm run dev
php artisan queue:work --tries=3
```

## 5. Variabel `.env` yang krusial

```env
APP_URL=https://LMS-V2.laragon

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=root          # Laragon lokal ini memakai password "root"
DB_DATABASE=sbu

FILESYSTEM_DISK=google    # butuh storage/app/google-credentials.json
MEDIA_DISK=google

QUEUE_CONNECTION=database
SESSION_DRIVER=file

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025            # Mailpit
```

Catatan: `FILESYSTEM_DISK=google` berarti upload media **gagal** jika file
kredensial Google tidak ada. Untuk kerja lokal tanpa Google Drive, ubah ke
`local` dan `MEDIA_DISK=local`.

## 6. Akun demo (setelah `DatabaseSeeder`)

| Peran | Email | Password |
|-------|-------|----------|
| Admin | `admin@lms.com` | `password` |
| Instruktur | `pemateri@lms.com` | `password` |
| Student | `ridwan@lms.com` | `password` |
| Student | `giras@lms.com` | `password` |

## 7. Deployment dan operasional Phase 2

Implementasi tersedia, **validasi akhir masih pending**. Checklist deployment
lengkap dan daftar migration ada di [PHASE2.md](PHASE2.md). Instruksi berikut
bukan laporan command sudah dijalankan.

1. Backup DB dan private storage; review migration additive yang belum diterapkan,
   lalu jalankan `php artisan migrate --force` secara terkontrol saat deployment.
   Jangan ubah migration lama atau memakai `migrate:fresh`. Migration grading esai
   juga menyesuaikan metadata attempt lama; uji pada salinan DB terlebih dahulu.
2. Isi konfigurasi bank di `.env` runtime, dibaca `config/billing.php`:

   | Variabel | Isi |
   |----------|-----|
   | `BILLING_BANK_NAME` | Nama bank tujuan transfer manual |
   | `BILLING_BANK_ACCOUNT_NUMBER` | Nomor rekening tujuan |
   | `BILLING_BANK_ACCOUNT_NAME` | Nama pemilik rekening |
   | `BILLING_BANK_INSTRUCTIONS` | Instruksi tambahan transfer |

   Default semuanya kosong; jangan mengarang rekening atau menaruh kredensial
   dalam docs. Setelah perubahan config/env/route, jalankan `php artisan optimize:clear`
   dan muat ulang cache konfigurasi sesuai prosedur deployment.
3. Pastikan disk `local` privat writable, tidak diekspos lewat web/public symlink,
   dan ikut backup: `billing/proofs/`, `payout-evidence/`, `refund-evidence/`.
   Storage bukti ini tetap `local` meski media memakai Google Drive.
4. Konfigurasikan Task Scheduler Windows/cron untuk `php artisan schedule:run`
   **tiap menit** dari root aplikasi, dengan PHP/runtime/env yang sama dengan app.
   `routes/console.php` menjadwalkan `subscriptions:invoice` **setiap jam** dengan
   `withoutOverlapping()`. Runner harus tetap dioperasikan di luar sesi agent.
5. Aktifkan **OPcache** di `php.ini` runtime web (saat validasi: seluruh baris
   `zend_extension=opcache` dan `opcache.*` masih dikomentari, sehingga PHP
   mengompilasi ulang seluruh framework setiap request). Pengukuran 1 Nov 2026:
   TTFB halaman sekitar 0,53-0,60 s; dengan OPcache file-cache lintas proses turun ke
   sekitar 0,43-0,45 s (sekitar 25 persen). Shared memory (default `opcache.enable=1`)
   umumnya lebih cepat lagi. Perubahan `php.ini` bersifat tingkat mesin: restart Apache
   setelahnya.
6. Jalankan queue worker sebagai proses terkelola untuk email pembayaran;
   restart worker setelah deployment sesuai prosedur operasional.

`php artisan subscriptions:invoice` boleh dipakai operator untuk pemrosesan due
secara manual setelah review: command ini **menulis invoice ke DB**, bukan dry-run.
Ia tidak menarik dana atau memberi akses; invoice baru dibuat saat expiry, bukan
sebelumnya. Lifetime tidak diinvoiskan. Pastikan admin memverifikasi pembayaran.
Membership/subscription/grant legacy **tidak dibackfill otomatis** oleh migration;
review dan rekonsiliasi data lama sebelum mengandalkan renewal/refund ledger.

Untuk pemeriksaan frontend, gunakan outdir sementara di luar `public/build/`
(lihat `TESTING.md`); jangan menjalankan build default dalam pekerjaan agent ini.
Validasi lokal 1 November 2026: backup privat dibuat di
`storage/app/phase2-backups/pre-phase2.sql`, tujuh migration Phase 2 diterapkan ke `sbu`
tanpa reset DB, seluruh suite pada `sbu_test` lulus (241 test/841 assertion), smoke dan
build outdir sementara lulus. Jadwal command diperiksa; runner OS/worker terkelola
belum dipasang. Rekening resmi belum dikonfigurasi. Tidak ada transaksi gateway atau
SMTP nyata yang dikirim saat validasi. Detail: `PHASE2.md` dan `TESTING.md`.

## 9. Catatan insiden (1 November 2026)

Selama pengukuran performa Phase 3.2, `php artisan optimize` membuat `config:cache`
yang mengunci nama database. `php artisan test` kemudian menjalankan `RefreshDatabase`
ke database development `sbu` dan mengosongkannya. Data dipulihkan dari
`storage/app/phase2-backups/pre-phase2.sql` lalu tujuh migration Phase 2 dijalankan
ulang selesai. Pencegahan permanen: `phpunit.xml` memakai `APP_CONFIG_CACHE` terpisah
dan `tests/TestCase::beforeRefreshingDatabase()` menolak database selain `sbu_test`.
Jangan menjalankan `php artisan optimize` di development tanpa `optimize:clear`
sebelum menjalankan test — atau lebih aman, gunakan `optimize:clear` setiap selesai
pengukuran.

## 8. Troubleshooting

| Gejala | Penyebab & solusi |
|--------|-------------------|
| Halaman tanpa CSS | Dibuka lewat port/IP salah → pakai `https://LMS-V2.laragon`, dan pastikan `npm run dev` hidup |
| `Access denied for user 'root'` | Password MySQL beda → cek `DB_PASSWORD` |
| `Call to undefined function mb_split()` | Apache memuat `php.ini` lain → pastikan `PHPIniDir` menunjuk folder PHP Laragon |
| `500` setelah ubah `.env` | `php artisan optimize:clear` |
| Email tidak terkirim | Queue worker mati, atau Mailpit mati |
| Invoice renewal belum muncul | Periksa expiry/due, status subscription/plan, scheduler runner dan log `Membership renewal invoice failed`; lifetime memang tidak diinvoiskan |
| `Refusing to run destructive tests against ...` | Config di-cache (`php artisan optimize`) sehingga test membaca DB dev → jalankan `php artisan optimize:clear`; `phpunit.xml` sudah memisahkan `APP_CONFIG_CACHE` |
| `php artisan test` menghapus data dev | Pastikan `phpunit.xml` masih memakai `APP_CONFIG_CACHE` terpisah dan `DB_DATABASE=sbu_test`; pulihkan dari backup privat bila perlu |
| Instruksi transfer kosong | Isi empat `BILLING_BANK_*` di runtime lalu refresh cache config |
| Bukti keuangan gagal upload/unduh | Periksa disk `local` privat, izin tulis, backup/file dan otorisasi; jangan pindahkan bukti ke public untuk mengatasi error |
| Refund meminta rekonsiliasi | Periksa ledger sumber enrollment atau latest matching membership grant; jangan bypass dengan reversal langsung |
| `Target class [...] does not exist` | `composer dump-autoload` |
