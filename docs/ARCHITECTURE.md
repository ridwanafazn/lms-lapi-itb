# ARCHITECTURE

## 1. Stack

| Layer | Teknologi |
|-------|-----------|
| Runtime | PHP 8.3 (Apache `mod_php`) |
| Framework | Laravel 11.48 |
| UI | Livewire 3 + Volt (single-file components) |
| Styling | Tailwind CSS 3 + Alpine.js (TALL stack) |
| Build | Vite 5 |
| DB | MySQL 8.4 (UUID untuk semua primary key) |
| Auth | Laravel Breeze (disesuaikan) + Spatie Permission |
| Pembayaran | Midtrans Snap + webhook; transfer manual diverifikasi admin |
| Media | Spatie Media Library + Google Drive disk |
| PDF | barryvdh/laravel-dompdf |

## 2. Layer

```
HTTP request
   ↓
routes/web.php  (Volt::route / Livewire component / closure)
   ↓
Livewire component / HTTP action (app/Livewire, resources/views/livewire, routes)
   ↓
Domain service (app/Services) + event/listener untuk fulfillment
   ↓
Model & relasi Eloquent (app/Models)
   ↓
MySQL
```

Sebagian fitur lama masih menaruh logika di Livewire/model; Phase 2 memakai
service untuk billing, checkout, refund, korporat, operasional, dan penilaian esai.
Integrasi eksternal memakai `ZoomService` dan controller webhook Midtrans.
Ini bukan klaim seluruh aplikasi/Player sudah selesai dipecah.

### Pengecualian yang sudah layak ditiru

| Kebutuhan | Pola yang dipakai |
|-----------|-------------------|
| Efek samping pembayaran | **Event + Listener** (`app/Events`, `app/Listeners`) |
| Data lintas layer | **DTO readonly** (`app/Data/TransactionPaidData`) |
| Integrasi pihak ketiga | **Service** (`app/Services/ZoomService`) |
| Aturan akses membership | **Trait** (`app/Models/Concerns/HasMembershipRequirement`) |
| Upload & penilaian tugas | **Service** (`app/Services/AssignmentSubmissionService`) |
| Sesi & penilaian kuis | **Service** (`app/Services/QuizService`, `app/Services/QuizGrader`) |
| Otorisasi | **Policy** (`Course`, `Lesson`, `Quiz`, `Assignment`) + cek model (`User::canAccess*`) + otorisasi di service Phase 2 |

## 3. Alur pembayaran (setelah Phase 0)

```
Midtrans webhook  ─┐
                   ├→ Transaction::markAsPaid()
Admin approve     ─┘        │
                            ├─ update payment_status + paid_at
                            └─ TransactionPaid::dispatch(TransactionPaidData)
                                     │
                      ┌──────────────┼───────────────┬────────────────────┐
                      ▼              ▼               ▼                    ▼
              FulfillEnrollment  ActivateMembership  RecordPaymentActivity  SendPaymentConfirmation
              (sync)             (sync)              (sync)                 (queue → email)
```

Semua listener sinkron berjalan **di dalam** `DB::transaction` milik `markAsPaid`,
jadi kegagalan salah satu listener membatalkan perubahan status pembayaran.

### Integrasi Phase 2

- `CheckoutService` menyimpan invoice/total diskon sebelum memanggil Snap;
  `CouponService` memakai reservasi atomik dan redeem idempoten di fulfillment.
- `FulfillEnrollment` memakai `EnrollmentService` + `EnrollmentPaymentSource`.
  Pembayaran batch melewati lock/kapasitas `BatchWaitlistService`.
  Invoice korporat diarahkan ke `CorporatePurchaseService::markPaid()`, tidak
  membuat enrollment pembeli. Alokasi seat owner dilakukan setelah lunas.
- `ActivateMembership` mendelegasikan ke `SubscriptionService` untuk snapshot
  `MembershipGrant`, expiry user, dan jadwal `Subscription` dalam transaksi yang sama.
- `subscriptions:invoice` → `SubscriptionService::invoiceRenewal()` → invoice
  transfer manual saat expiry; tidak memanggil gateway/auto-debit dan tidak memberi akses.
  Schedule hourly `withoutOverlapping()` ada di `routes/console.php`.
- `RefundService` mencatat refund penuh manual + `Refund`/`refunded_at`/audit.
  Reversal enrollment memeriksa sumber pembayaran lain dan akses lama yang dilindungi.
  Membership hanya direversal dari grant terbaru yang cocok state saat ini,
  memulihkan snapshot dan menghentikan renewal. Tidak ada backfill ledger legacy.
- Bukti billing, payout, refund berada pada disk `local` privat, diunduh lewat
  route terotorisasi, bukan URL media publik/Google Drive.

## 4. Alur belajar

```
/learning/course/{course}?program_id=&batch_id=
   ↓  Player::mount()  → cek akses (canAccessProgram / canAccessBatch / hasAccessToCourse)
   ↓  loadCourseData() → sections.lessons.material|quiz|assignment
   ↓  loadUserProgress() → lesson_completions
   ↓
per lesson: markAsComplete() / autoCompleteContent() / quiz → finishQuiz()
   ↓
quiz beresai → submitted/pending → seluruh nilai manual + feedback → skor final
   ↓
quiz lulus + progress 100% → testimoni → cek server → Certificate (+ PDF + QR)
```

`Course::is_sequential` memaksa urutan: lesson berikutnya terkunci sampai
lesson sebelumnya selesai. Untuk kuis, baris completion saja tidak cukup:
`QuizService::hasPassed()` memeriksa skor final/`graded_at`. `EssayGradingService`
memberi nilai 0..bobot + feedback, `QuizGrader` menggabungkan nilai setelah seluruh
esai dinilai. `CertificateService::assertEligible()` dipanggil dari aksi testimoni
dan penerbitan sertifikat untuk memeriksa seluruh lesson dan kelulusan kuis lagi.
Absensi live session tidak masuk syarat ini.

## 5. Otorisasi berlapis

| Tempat | Mekanisme |
|--------|-----------|
| Route | middleware `auth`, `verified`, `role:admin`, `role:instructor` |
| Komponen | `$this->authorize('update', $course)` (CoursePolicy) |
| Konten | `User::hasAccessToCourse()` / `canAccessProgram()` / `canAccessBatch()` |

`LessonPolicy`, `QuizPolicy`, dan `AssignmentPolicy` tersedia. Phase 2 juga
memeriksa owner/role di service dan aksi halaman: admin untuk refund/honor/clone/
promosi; instruktur terkait untuk absensi; pemilik course/admin untuk grading;
owner invoice/pembelian untuk billing dan alokasi korporat. Keberadaan policy
bukan bukti semua jalur aplikasi telah diaudit; validasi lokal Phase 2 lulus
(234 test/826 assertion), dengan batas operasional pada `PHASE2.md`.

## 6. Data & performa

- Semua PK `char(36)` UUID (`App\Traits\HasUuids`), tidak ada ID berurutan yang bisa ditebak.
- Soft delete aktif pada `courses`, `programs`, `batches`, `users` (`deleted_at`).
- Index penting yang sudah ada:
  - unique `enrollments(user_id, enrollable_type, enrollable_id)`
  - unique `lesson_completions(user_id, lesson_id)`
  - unique `certificates(certificate_code)`
  - unique `course_testimonials(user_id, course_id)`
  - unique `quiz_answers(quiz_attempt_id, question_id, question_option_id)`
  - index `quiz_attempts(quiz_id, user_id, score)`
  - unique `submissions(assignment_id, user_id, batch_id)`
- N+1 pada `Player::loadUserProgress()` dan render sidebar sudah dihilangkan pada Phase 3.1:
  relasi dimuat ulang sekali per request lewat hook `hydrate()`, query `loadLesson()`
  turun dari 15 menjadi 9 dan **tidak tumbuh** seiring jumlah section
  (`tests/Feature/Phase3PerformanceTest.php`). Soal kuis tidak lagi di-eager-load
  untuk seluruh course saat `mount()`.

## 7. Frontend

- `resources/views/livewire/**` — komponen per peran (`admin`, `instructor`, `student`, `public`).
- `resources/views/layouts/**` — `app`, `guest`, `player`, `course-workspace`, `print`, `landing`.
- `vite.config.js` memakai HMR host `localhost` dan port 5173; aplikasi tetap dibuka
  lewat Laragon. Jangan menulis `public/build/` saat validasi agent; gunakan outdir
  sementara (lihat `TESTING.md`).

### Route dan halaman Phase 2

`routes/web.php` memuat kelima file berikut (bukan route yang berdiri sendiri):

| File route | Halaman utama | Service |
|------------|---------------|---------|
| `routes/phase2-billing.php` | `/billing`, `/billing/invoices/{invoice}` | `SubscriptionService` |
| `routes/phase2-commerce.php` | `/corporate`, `/admin/coupons` | `CheckoutService`, `CouponService`, `CorporatePurchaseService` |
| `routes/phase2-operations.php` | `/admin/payouts`, `/admin/batch-operations`, `/admin/attendance`, `/instructor/payouts`, `/instructor/attendance`, `/waitlist` | `PayoutService`, `BatchWaitlistService`, `BatchCloneService`, `LiveAttendanceService`, `OperationsAccess` |
| `routes/phase2-essays.php` | `/grading/essays/{attempt?}` | `EssayGradingService`, `QuizGrader`, `QuizService` |
| `routes/phase2-refunds.php` | `/admin/refunds` | `RefundService` |

Halaman memakai `app/Livewire/{Billing,Commerce,Operations,Instructor,Admin}` dan
view terkait. Upload bukti payout/refund memakai POST multipart langsung;
unduhan bukti dilindungi middleware dan pemeriksaan cakupan yang relevan.
Rekap deployment dan migration additive: [PHASE2.md](PHASE2.md).

## 8. Batasan yang disengaja

| Keputusan | Alasan |
|-----------|--------|
| Server-rendered, bukan SPA | SEO untuk landing & verifikasi sertifikat; tim kecil |
| Session `file`, queue `database` | Cukup untuk skala sekarang, tanpa Redis dependency |
| Foto/berkas di Google Drive | Hemat storage server, mudah dibagikan tim konten |
