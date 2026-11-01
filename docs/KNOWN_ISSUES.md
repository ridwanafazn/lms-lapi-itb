# KNOWN ISSUES

Hanya berisi temuan yang **sudah diverifikasi** di kode/database.
Severity: **P0** uang/keamanan/data hilang · **P1** kebenaran data · **P2** performa · **P3** kebersihan

---

## ✅ Selesai di Phase 0 (1 Nov 2026)

| # | Temuan | Perbaikan |
|---|--------|-----------|
| F1 | Upload tugas tidak pernah disimpan (`Player::submitAssignment()` hanya memberi notifikasi) | `AssignmentSubmissionService` + Player & komponen Volt memakainya; test `PlayerAssignmentTest` |
| F2 | Kolom `programs.required_membership_plan_id` tidak ada | migration `2026_11_01_000001` |
| F3 | Kolom `programs.min_membership_level` tidak ada | migration `2026_11_01_000001` |
| F4 | Kolom `batches.price` dan `batches.min_membership_level` tidak ada (harga batch selalu jatuh ke program, membership tidak pernah membuka batch) | migration `2026_11_01_000007` |
| F5 | `user_profiles.gender` ENUM menolak string kosong | migration `2026_11_01_000002` → `varchar(20) nullable` |
| F6 | `submissions.batch_id` NOT NULL → tugas kelas mandiri gagal disimpan | migration `2026_11_01_000003` |
| F7 | Index hilang pada `quiz_answers`, `quiz_attempts`, `submissions` | migration `2026_11_01_000004` |
| F8 | `Quiz::max_attempts` & `is_randomized` tidak pernah dibaca | `QuizService` + test `QuizGradingTest` |
| F9 | Penilaian kuis memakai state komponen, bukan DB | `QuizGrader` membaca `quiz_answers` |
| F10 | `quiz_attempts.is_timeout` tidak ada (nilai dibuang mass assignment) | migration `2026_11_01_000006` |
| F11 | `Course`/`Program`/`Batch`/`User` tanpa soft delete | migration `2026_11_01_000005` + trait |
| F12 | 7 test Breeze gagal karena auth sudah pindah ke Livewire/Volt | test disesuaikan dengan komponen nyata |
| F13 | Batch membership tidak mengecek masa aktif (`isFreeForUser` tanpa `hasActiveMembership`) | trait `HasMembershipRequirement` |

---

## Perbaikan Phase 2 — validasi lokal lulus

| Temuan lama | Implementasi perbaikan |
|-------------|-----------------------|
| #1 Soal esai tidak dinilai | `EssayGradingService` + halaman grading; seluruh esai wajib nilai 0..bobot dan feedback, pending sampai semua dinilai; Player/QuizPlayer serta `CertificateService` memeriksa kelulusan server-side |
| #3 `access_expires_at` tidak ditegakkan | `User::canAccessProgram()` / `canAccessBatch()` menolak enrollment aktif yang sudah kedaluwarsa; akses membership yang memenuhi syarat tetap terpisah |

Kedua temuan ditutup pada scope Phase 2. Seluruh suite lokal lulus: 234 test,
826 assertion; smoke lintas fitur juga lulus. Hasil ini bukan audit keamanan menyeluruh
atau bukti pengiriman dana/email nyata; rincian ada di `TESTING.md`.

### Batasan operasional Phase 2

- Renewal berupa invoice manual saat expiry, **bukan auto-debit** atau invoice
  sebelum jatuh tempo. Scheduler dan queue worker harus dioperasikan terpisah.
- Subscription, membership grant, dan sumber enrollment legacy **tidak dibackfill
  otomatis**. Refund legacy tanpa ledger atau membership dengan grant lama/state
  tidak cocok ditolak untuk rekonsiliasi, bukan dipaksakan lewat reversal langsung.
- Refund hanya penuh/manual dengan bukti privat. Kuota kupon yang sudah dipakai
  tidak dikembalikan; kupon korporat memang tidak didukung.
- Alokasi seat korporat permanen, hanya owner/HR; pembeli tidak otomatis mendapat
  akses. Refund melepaskan reservasi batch dan mencabut sumber akses invoice itu saja.
- Bukti billing/payout/refund membutuhkan disk `local` privat yang writable dan
  ikut backup. Konfigurasi media Google Drive tidak menggantikan storage bukti.
- Implementasi Phase 2 dan validasi lokal selesai. Rekening resmi belum dikonfigurasi,
  runner scheduler/worker jangka panjang belum dipasang oleh agent; lihat [PHASE2.md](PHASE2.md).

---

## P1 — Kebenaran data (masih terbuka)

### 2. Testimoni langsung `published`
`Player::submitTestimonial()` menulis `'status' => 'published'` sehingga tidak
ada moderasi, padahal admin punya halaman feedback.


### 4. Tanggal batch disimpan JSON array
`batches.start_date`, `end_date`, `registration_deadline` di-cast `array`
sehingga query berbasis tanggal tidak bisa dilakukan di SQL.

---

## P2 — Performa (masih terbuka)

### 5. N+1 di perhitungan progres — ✅ selesai Phase 3.1
`Player::loadUserProgress()` dulu me-loop `$this->course->sections` dan mengambil
`lessons` per section, ditambah lazy-load `material`/`quiz` per lesson di sidebar.
Kini relasi dimuat ulang sekali per request (`Player::hydrate()`), progres memakai
`PlayerProgressCalculator`, dan jumlah query **datar** (9) baik untuk 3 maupun 12
section — dijaga `tests/Feature/Phase3PerformanceTest.php`.

### 6. Komponen `Player` masih besar
Masih menangani video, teks, kuis, tugas, forum, testimoni, sertifikat.
Service kuis, upload, dan gating sertifikat sudah dipakai pada jalur terkait;
struktur pemecahan bukan bukti refactor penuh Player selesai.

### 7. `Program::getLessonsCountAttribute()` memanggil count per course — ✅ selesai Phase 3.2
Dulu tiga query per course (materials, quizzes, assignments). Kini memakai `withCount`
yang sudah dimuat (0 query tambahan) atau fallback tiga query agregat datar untuk
seluruh course program — dijaga `tests/Feature/Phase3PerformanceTest.php`.

---

## Hardening yang sudah tersedia

### 8. Rate limiting (Phase 1.3)
- `GET /certificates/{code}` → `throttle:certificate` (60/min/IP)
- `POST /webhook/midtrans` → `throttle:webhook` (100/min/IP)
- `GET /learning/{course}/quiz/{quiz}` → `throttle:quiz_submit` (30/min/user)
- Didefinisikan di `AppServiceProvider::boot()` via `RateLimiter::for()`.

## P3 — Kebersihan & hardening (masih terbuka)

### 9. Validasi upload belum memindai malware
Validasi format/MIME/ukuran bukan pemindaian antivirus. Jangan menganggap bukti
keuangan atau berkas tugas sudah dipindai malware hanya karena upload diterima.

### 10. Media bergantung pada Google Drive
`FILESYSTEM_DISK=google` dan `MEDIA_DISK=google`. Jika
`storage/app/google-credentials.json` tidak ada, upload & tampilan media gagal.

### 11. `catch` kosong menelan error — ✅ selesai Phase 1
`Player::executeCompletion()` sekarang mencatat kegagalan ke channel `domain`
(`user_id`, `lesson_id`, pesan) alih-alih `catch (\Exception $e) {}`.

### 12. Soft delete + unique index
`users.email` dan `programs.enrollment_code` masih unik global; user/program
yang di-soft-delete tetap "menahan" nilai tersebut. Jika perlu daftar ulang
dengan email sama, pertimbangkan unique index parsial (`WHERE deleted_at IS NULL`).

### 13. Field gating belum lengkap di UI admin
- `admin/programs` sudah punya field `min_membership_level`. `required_membership_plan_id`
  belum punya input UI (hanya manual/seeder).
- `admin/batches` SUDAH DILENGKAPI (commit `8bc7804`): `min_membership_level` dan `price`
  sudah ada di form modal, validasi, edit, dan reset. `Batch::getEffectivePriceAttribute()`
  sekarang bisa dioverride via UI.

---

## Catatan audit awal yang perlu dikoreksi

Audit pertama menyebut "tidak ada index" — **tidak akurat**. Yang sudah ada
sebelum Phase 0: unique `enrollments(user_id, enrollable_type, enrollable_id)`,
unique `lesson_completions(user_id, lesson_id)`, unique
`certificates(certificate_code)`, `course_testimonials(user_id, course_id)`.
