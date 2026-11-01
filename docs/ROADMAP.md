# ROADMAP

Status: `[ ]` belum · `[~]` berjalan/parsial · `[x]` selesai pada scope yang dicatat.
Phase 2.1–2.8 telah diimplementasikan dan diverifikasi lokal pada 1 November 2026:
234 test lulus, 826 assertion, smoke lintas fitur lulus. Rekap dan batas operasional:
[PHASE2.md](PHASE2.md). Tidak ada klaim deployment produksi atau auto-debit.

## Phase 0 — Remediasi fundamental ✅ SELESAI

| # | Task | Status | Bukti |
|---|------|--------|-------|
| 0.1 | Event-driven payment fulfillment (`TransactionPaid` + DTO + 4 listener) | `[x]` | `app/Events`, `app/Listeners`, `app/Data` |
| 0.2 | Feature test pembayaran | `[x]` | `tests/Feature/PaymentFulfillmentTest.php` (10 test) |
| 0.3 | Kuitansi email via queue + Mailpit | `[x]` | `app/Mail/PaymentReceipt.php`, `resources/views/mail/` |
| 0.4 | Gating membership: kolom `programs` & `batches` + trait `HasMembershipRequirement` | `[x]` | migration `000001`, `000007`, `tests/Feature/MembershipAccessTest.php` |
| 0.5 | Upload tugas tersimpan ke `submissions` | `[x]` | `AssignmentSubmissionService`, `PlayerAssignmentTest`, `AssignmentSubmissionTest` |
| 0.6 | Kolom `user_profiles.gender` | `[x]` | migration `000002`, `ProfileTest` |
| 0.7 | Index `quiz_answers`, `quiz_attempts`, `submissions` | `[x]` | migration `000004` |
| 0.8 | Penilaian kuis server-side + batas percobaan + randomisasi + timeout | `[x]` | `QuizService`, `QuizGrader`, `QuizGradingTest`, `PlayerQuizTest` |
| 0.9 | SoftDeletes Course/Program/Batch/User | `[x]` | migration `000005`, `SoftDeleteTest` |
| 0.10 | Test suite hijau | `[x]` | 68 test, 170+ assertion |

## Phase 1 — Hardening

| # | Task | Status | Bukti/catatan |
|---|------|--------|---------------|
| 1.1 | Policy `Lesson`, `Quiz`, `Assignment` (cek akses per konten) | `[x]` | `app/Policies/`, `AppServiceProvider` |
| 1.2 | Ganti test Breeze usang dengan test komponen nyata | `[x]` | Test komponen auth |
| 1.3 | Rate limiting: verifikasi sertifikat, webhook, submit kuis | `[x]` | `AppServiceProvider::boot()`, `routes/web.php` (throttle middleware), `tests/Feature/Phase1PolicyRateLimitTest.php` |
| 1.4 | Validasi upload lanjutan (MIME sniffing, kuota per user) | `[x]` | `AssignmentSubmissionService::validateFile()`, `tests/Feature/Phase1UploadMIMEQuotaTest.php` |
| 1.5 | `CertificateService`, `EnrollmentService` (selaraskan dengan listener) | `[x]` | `app/Services/CertificateService.php`, `EnrollmentService.php` |
| 1.6 | Pecah `Player` menjadi komponen per tipe konten | `[~]` | Struktur `PlayerBreakdown.php` bukan integrasi penuh; Phase 2 hanya mengubah jalur kuis/completion yang relevan |
| 1.7 | Observability: log terstruktur + Sentry/Telescope | `[~]` | `LogDomainEvent` + channel domain tersedia; bukan klaim integrasi Sentry/Telescope selesai |
| 1.8 | CI: `php artisan test` + `pint --test` + `npm run build` | `[x]` | `.github/workflows/test.yml` |

## Phase 2 — Selesai implementasi dan validasi lokal (8/8)

| # | Task | Implementasi | Bukti/cakupan (bukan hasil test) |
|---|------|--------------|---------------------------------|
| 2.1 | Invoice renewal membership manual, bukan auto-debit | `[x]` | `SubscriptionService`, `Subscription`, `MembershipGrant`, `/billing`, command `subscriptions:invoice` hourly; invoice saat expiry, lifetime dikecualikan, cancel mempertahankan akses |
| 2.2 | Refund penuh manual & reversal berbasis sumber | `[x]` | `RefundService`, `Refund`, `EnrollmentPaymentSource`, `/admin/refunds`; bukti privat/audit; membership hanya grant terbaru cocok state; legacy perlu rekonsiliasi |
| 2.3 | Kupon checkout Program/Batch/MembershipPlan | `[x]` | `CheckoutService`, `CouponService`, `CouponRedemption`, `/admin/coupons`; reservasi atomik, tanpa stacking, refund tidak mengembalikan kuota; korporat dilarang |
| 2.4 | Pembelian korporat multi-seat + dashboard owner/HR | `[x]` | `CorporatePurchaseService`, `CorporateSeat`, `/corporate`; owner alokasi akun student aktif, permanen, tanpa auto-enrollment pembeli; kursi batch lunas belum teralokasi mereservasi kuota |
| 2.5 | Honor tetap instruktur/batch + payout manual | `[x]` | `PayoutService`, `Payout`, `PayoutAudit`, halaman admin/instruktur; bukti privat, referensi, tanggal, hash dan audit |
| 2.6 | Waitlist FIFO, clone konfigurasi batch, absensi manual | `[x]` | `BatchWaitlistService`, `BatchCloneService`, `LiveAttendanceService`; promosi admin hanya reservasi, clone tanpa data operasional, absensi tidak menjadi gating sertifikat |
| 2.7 | Enforcement `access_expires_at` | `[x]` | `User::canAccessProgram()` / `canAccessBatch()` memeriksa expiry enrollment; membership merupakan sumber akses terpisah |
| 2.8 | Penilaian esai manual + gating server | `[x]` | `EssayGradingService`, `QuizGrader`, Player/QuizPlayer, `CertificateService`; seluruh esai wajib nilai 0..bobot + feedback sebelum final/lulus |

### Penutupan Phase 2

- [x] Seluruh suite pada `sbu_test`: 234 passed, 826 assertions, 0 failed.
- [x] Tujuh migration baru diuji melalui test dan diterapkan pada `sbu` lokal setelah backup privat; tanpa reset DB development.
- [x] Smoke manual lintas fitur dan GET halaman per peran lulus; tidak ada ERROR/CRITICAL/EMERGENCY baru selama smoke.
- [x] Pint dan PHP lint 71 file lulus; Blade cache serta build ke outdir sementara lulus, tanpa menyentuh `public/build/`.
- [x] Route wiring dan definisi scheduler diperiksa; batas runtime dicatat di `PHASE2.md`.
- Operasional oleh operator: isi rekening resmi (saat validasi belum dikonfigurasi), pasang runner scheduler dan queue worker terkelola. Pengiriman SMTP/Midtrans nyata dan produksi tidak diuji. Tidak ada commit otomatis.

## Phase 3 — Performa & UX Hardening

| # | Task | Status |
|---|------|--------|
| 3.1 | Performa: integrasi `PlayerBreakdown` + hilangkan N+1 (`loadLesson` 15→9 query, datar 3–12 section) | `[x]` |
| 3.2 | Efisiensi: prefetch navigasi, lesson re-open no-op, `lessons_count` datar, guard DB test | `[x]` |
| 3.3 | UX Feels: `wire:loading`, `authorize()` `notify` (bukan `abort()`), `backUrl` konsisten saat sertifikat, transisi konten | `[x]` |
| 3.4 | Batasan jujur: `docs/PHASE3.md` mencatat round-trip Livewire, OPcache, dan kapan perlu Phase 5 | `[x]` |

> Catatan: `docs/PHASE3.md` mencatat batasan arsitektur TALL (`ARCHITECTURE.md` 8.1) dan tidak menjamin respons sub-200ms absolut; hanya verifikasi terukur (`DB::listen()` ≤ 3 query, `curl -L` tanpa loop, visual `wire:loading`).

## Phase 4 — Portofolio (digeser dari Phase 3)

| # | Task | Status |
|---|------|--------|
| 4.1 | `README.md` tingkat root dengan ringkasan bisnis dan arsitektur | `[x]` |
| 4.2 | Diagram ERD dan C4 | `[x]` |
| 4.3 | Demo seeder khusus akun admin instruktur dan peserta | `[x]` |
| 4.4 | Video walkthrough per peran | `[ ]` |
| 4.5 | Artikel teknis tentang payment event dan quiz security | `[ ]` |

Catatan hasil fase empat sampai tahap tiga

Berkas README.md ada di akar proyek dan memuat ringkasan bisnis, status fase,
cara menjalankan, akun demo, perintah harian, struktur folder, dan catatan
operasional. Ditulis dengan bahasa lugas tanpa singkatan berlebih.

Berkas docs/ERD.md memuat diagram relasi data dan diagram C4 level satu sampai
tiga. Nama tabel diambil langsung dari skema database.

Berkas database/seeders/PortfolioDemoSeeder.php menyiapkan satu program lengkap
berisi dua materi, satu kuis dengan soal pilihan ganda dan esai, satu tugas, satu
angkatan kuota tiga puluh, dan satu peserta terdaftar. Seeder aman dijalankan
berulang. Pengujiannya ada di tests/Feature/PortfolioDemoSeederTest.php.

## Phase 5 — SPA Hybrid / Reverb (Opsional — jika Phase 3 tidak cukup)

Catatan matang (`docs/PHASE3.md` 3.4): `Livewire` server-rendered (`ARCHITECTURE.md` 8.1) memiliki batasan arsitektur (`POST /livewire/update` per interaksi). Jika `PT LAPI ITB` membutuhkan respons sub-200ms konsisten atau animasi `60fps` (`native-like`), solusi bukan `TALL` redesign, melainkan:
- `Inertia.js` (`Vue`/`React` SPA) + `Laravel API` (`routes/api.php`), atau
- `Laravel Reverb` (`WebSockets`) untuk state `quiz`/`assignment` tanpa `polling`.

Status tetap `[ ]` sampai `Phase 3` divalidasi dan terbukti tidak memenuhi ekspektasi produksi.

## Definisi selesai per task

Lihat `WORKFLOW.md` bagian 2.
