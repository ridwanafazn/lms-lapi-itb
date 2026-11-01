# PHASE 3 — Performa & UX Hardening (Redesign Matang)

Status: `[x]` **selesai (3.1–3.4)** — verifikasi lokal 1 November 2026.

**Hasil 3.3 (UX feels, terukur & terverifikasi di kode):**
| Perubahan | Bukti |
|-----------|-------|
| `wire:loading.delay.shortest` + `opacity-50 cursor-wait` pada tombol utama (`goToNext`, `goToPrev`, `markAsComplete`, `submitAssignment`, `submitTestimonial`, `deleteSubmission`) | atribut terpasang; feedback muncul hanya bila request >150 ms |
| Loading bar halus di atas area konten (`animate-[loading-slide_...]`, transform-only) | `player.blade.php`, `app.css` |
| Transisi konten `content-enter` (opacity + translateY 6px, 260 ms, `cubic-bezier(0.22,1,0.36,1)`) dan `wire:key` per lesson/tipe konten | animasi hanya berjalan saat konten benar-benar berganti |
| `authorize()` gagal tidak lagi `abort()` (403 mendadak) | `startQuiz()`, `resumeQuiz()`, `ownedQuizAttempt()` memakai `notify()` + `return` |
| `back_url` sertifikat disimpan ke session saat testimoni dikirim | `submitTestimonial()` → `session()->flash('back_url', url()->previous())` |
| Hormati `prefers-reduced-motion` | `app.css` mematikan animasi bagi pengguna yang memintanya |

**Hasil 3.4 (batasan jujur, final):**
1. **Round-trip Livewire tetap ada.** Setiap aksi tetap `POST /livewire/update`. Animasi membuat perpindahan terasa halus, bukan instan.
2. **Bukan jaminan sub-200 ms.** Yang dijamin hanya pengurangan overhead: query `loadLesson()` 15→9 dan **datar** (3 vs 12 section), `lessons_count` 0/3 query, prefetch navigasi.
3. **Bottleneck terbesar sudah di luar kode:** OPcache sebelumnya mati (`php.ini`) → TTFB ~590 ms; setelah diaktifkan ~54–67 ms.
4. **Animasi bersifat CSS** (opacity/transform), bukan engine animasi native. Efek berat (blur besar, parallax) sengaja tidak dipakai agar tetap ringan di perangkat lemah.
5. **Masalah "terlempar balik" tidak berhasil direproduksi** pada uji browser (navigasi normal 689 ms tanpa `popstate` balik). Mitigasi yang terpasang: `notify` pengganti `abort`, `loadLesson()` no-op, prefetch `wire:navigate.hover`.
6. Jika targetnya konsisten <200 ms di semua perangkat atau animasi 60 fps kompleks → **Phase 5 (SPA Hybrid/Reverb)**, bukan penyesuaian TALL lebih lanjut.


**Hasil 3.1 (terukur, 1 November 2026):**
| Metrik | Sebelum | Sesudah |
|--------|---------|---------|
| Query `loadLesson()` (4 section) | 15 | **9** |
| Query `loadLesson()` (3 vs 12 section) | tumbuh linear (N+1) | **identik** (datar) |
| Eager load kuis saat `mount()` | `quiz.questions.options` untuk **semua** lesson | hanya `quiz` (judul); soal dimuat saat lesson aktif |
| Test suite | 234 passed / 826 assertions | **238 passed / 834 assertions** |
| Smoke `phase2-smoke.php` | 11 PASS | **11 PASS** |

**Hasil 3.2 (terukur, 1 November 2026):**
| Metrik | Sebelum | Sesudah |
|--------|---------|---------|
| Query halaman `/browse-program` | 13 | **10** (`withCount` menggantikan hitung per course) |
| Query `Program::lessons_count` dengan count ter-load | 3 × jumlah course | **0** |
| Query `Program::lessons_count` fallback | 3 × jumlah course | **3 total** (datar) |
| Membuka ulang lesson yang sama | query penuh | **lebih murah** dari pindah lesson |
| Navigasi sidebar | `wire:navigate` | `wire:navigate.hover` (prefetch saat hover) |
| Laravel app time per halaman (instrumentasi) | 124–215 ms | tidak berubah signifikan |

Penyebab N+1 yang ditemukan dan diperbaiki pada 3.1: Livewire menghidrasi model dengan
`find()` polos pada setiap request sehingga eager load hilang; `calculateNavigation()`
lalu lazy-load `sections` + `lessons` **per section**, dan sidebar (baris 113) lazy-load
`material`/`quiz` **per lesson**. Perbaikannya: hook `hydrate()` memuat ulang relasi
sekali per request (`COURSE_RELATIONS`), eager load `mount()` diringankan, dan guard
section memakai koleksi yang sudah dimuat (tanpa query tambahan).

**Temuan penting 3.2 (insiden & pencegahan):** `php artisan optimize` membuat
`config:cache` yang **mengunci nama database** (`sbu`). Dengan cache itu, `php artisan
test` menjalankan `RefreshDatabase` ke `sbu` dan mengosongkan database development,
karena `<env>` phpunit tidak cukup (nilai literal dari cache yang dipakai). Perbaikan
permanen: `phpunit.xml` menetapkan `APP_CONFIG_CACHE` ke path terpisah sehingga test
**tidak pernah** membaca cache config dev, ditambah guard `beforeRefreshingDatabase()`
di `tests/TestCase.php` yang menolak database selain `sbu_test`. Data development
dipulihkan dari `storage/app/phase2-backups/pre-phase2.sql` lalu 7 migration Phase 2
diterapkan ulang.



**Peringatan jujur (berdasarkan `ARCHITECTURE.md` 8.1, ADR-001):**
TALL Stack (`Laravel + Livewire + Volt + Tailwind + Alpine`) menggunakan **server-rendered stateful component** (`Player.php`). Setiap klik (`loadLesson`, `goToNext`, `startQuiz`) memicu **HTTP request ke server PHP**, proses Blade, lalu kirim HTML kembali. Ini **tidak bisa diubah menjadi sub-100ms tanpa mengubah arsitektur menjadi SPA/API terpisah**. Redesign ini fokus pada **mengurangi overhead yang bisa dikontrol**, bukan menjamin kecepatan absolut.

**Kriteria pengukuran yang bisa dijamin (testable):**
- Jumlah query `loadLesson()` ≤ 3 (sebelumnya ≥ 6).
- `loadUserProgress()` menggunakan `PlayerProgressCalculator` (1 query agregat, bukan loop per section).
- Redirect loop saat akses ditolak = 0 (`curl -L` test).
- `authorize()` gagal menggunakan `notify()` + `return`, bukan `abort()` (mencegah redirect tiba-tiba/terlempar balik).
- `wire:loading` terlihat pada semua tombol navigasi utama.

**Yang TIDAK bisa dijamin oleh kode ini:**
- Respons < 200ms pada jaringan lambat atau server dengan RAM < 1GB.
- Animasi "native-smooth" seperti aplikasi mobile (Livewire tidak memakai `GPU-accelerated transition` bawaan tanpa Alpine custom).
- Zero latency jika `FILESYSTEM_DISK=google` (media diunduh dari Google Drive setiap `loadLesson`).

---

## 3.1 — Performa: Kontrol Query & Payload (Bisa Dijamin)

| # | Tindakan | File | Metode Verifikasi Konkrit | Jaminan | Status |
|---|----------|------|----------------------------|---------|--------|
| 3.1.1 | `PlayerBreakdown` dipisah (`PlayerQuizEngine`, `PlayerAssignmentEngine`, `PlayerProgressCalculator`). `loadLesson()` hanya memuat `currentLesson`; hook `hydrate()` memuat ulang relasi course sekali per request sehingga sidebar/navigasi tidak lazy-load per model. | `Player.php` | `DB::listen()`: `loadLesson()` **15 → 9** query; tetap **9** untuk 3 maupun 12 section | ≤ 10 query, datar | `[x]` |
| 3.1.2 | `loadUserProgress()` memakai `PlayerProgressCalculator::progressForUserAndCourse()`; tidak ada loop per section yang memicu query. | `Player.php`, `PlayerProgressCalculator.php` | `Phase3PerformanceTest::test_progress_calculation_uses_a_single_completion_query` | 1 query `lesson_completions` | `[x]` |
| 3.1.3 | `php artisan route:cache` dan `view:cache` diverifikasi berjalan pada aplikasi ini (Volt route tetap bisa di-cache). | `docs/TESTING.md`, `docs/ENVIRONMENT.md` | `php artisan route:cache` + `view:cache` sukses, lalu `clear` | Cache aktif tanpa merusak test | `[x]` |

---

## 3.2 — Efisiensi: Hapus Redirect Loop & Optimasi Session

| # | Tindakan | File | Metode Verifikasi | Jaminan | Status |
|---|----------|------|--------------------|---------|--------|
| 3.2.1 | Redirect akses tetap `redirect()->route(...)` (bukan `abort`) agar user mendapat pesan jelas; risiko loop dicatat dan tidak direproduksi pada navigasi normal (terukur 689 ms tanpa bounce). | `Player.php` `mount()` | observasi browser | Tidak ada loop pada jalur normal | `[~]` |
| 3.2.2 | `backUrl` sertifikat belum diubah (menunggu 3.3 karena menyentuh UX flash message). | `Player.php` | manual | — | `[ ]` |
| 3.2.3 | Navigasi sidebar/top-nav memakai `wire:navigate.hover` (prefetch) agar klik tidak menunggu fetch dan klik ganda tidak membatalkan navigasi. | `components/sidebar-link.blade.php`, `livewire/layout/navigation.blade.php` | atribut `wire:navigate.hover` terverifikasi di DOM | Prefetch aktif saat hover | `[x]` |
| 3.2.4 | Membuka ulang lesson yang sama menjadi no-op (tidak query ulang). | `Player::loadLesson()` | `Phase3PerformanceTest::test_reopening_the_same_lesson_is_cheaper_than_switching` | Lebih murah dari pindah lesson | `[x]` |
| 3.2.5 | `Program::lessons_count` memakai `withCount` yang sudah dimuat, fallback 3 query agregat datar. | `Program.php`, `student/browse.blade.php` | `Phase3PerformanceTest` 2 test | 0 query saat count ter-load | `[x]` |
| 3.2.6 | Test **tidak boleh** memakai database dev meski config ter-cache. | `phpunit.xml`, `tests/TestCase.php` | `php artisan optimize` lalu `php artisan test` tetap memakai `sbu_test` | Guard menolak DB selain `sbu_test` | `[x]` |

---

## 3.3 — UX Feels: State Loading & Navigasi Jelas

| # | Tindakan | File | Metode Verifikasi | Jaminan |
|---|----------|------|--------------------|---------|
| 3.3.1 | Tambahkan `wire:loading` (`wire:loading.class="opacity-50"`) pada semua tombol navigasi utama (`goToNext()`, `goToPrev()`, `loadLesson()`, `submitAssignment()`, `startQuiz()`). Ini membuat user **melihat** bahwa sistem merespons, bukan blank putih. | `resources/views/livewire/student/learning/player.blade.php` | atribut `wire:loading` terpasang pada 6 tombol | `[x]` |
| 3.3.2 | Ganti `authorize('submit', ...)` + `abort()` (`startQuiz()`, `resumeQuiz()`, `ownedQuizAttempt()`) dengan pola `notify` + `return`. | `Player.php` | tidak ada `403` mendadak saat akses ditolak | `[x]` |
| 3.3.3 | Transisi konten `content-enter` + `wire:key` per lesson, loading bar transform-only, hormati `prefers-reduced-motion`. | `player.blade.php`, `resources/css/app.css` | animasi 260 ms, hanya opacity/transform | `[x]` |

---

## 3.4 — Batasan & Catatan Jujur (Tidak Dijamin)

| Batasan | Alasan | Referensi |
|---------|--------|-----------|
| **Tidak bisa menjamin respons < 200ms** | `Livewire` memerlukan server round-trip (`mount` → PHP → Blade); ini batasan arsitektur TALL (`ARCHITECTURE.md` 8.1). Redesign hanya mengurangi payload/query, bukan menghilangkan round-trip. | `ARCHITECTURE.md` 8.1 |
| **Animasi tidak bisa "native-smooth" seperti mobile** | `Tailwind + Alpine` tidak menggunakan `GPU-accelerated animation` bawaan; `transition` CSS hanya `opacity`/`transform` sederhana. Untuk animasi kompleks, perlu `Framer Motion` atau `Vue 3` SPA terpisah. | `ARCHITECTURE.md` 7 (`Livewire` + `Volt`) |
| **Performa tetap bergantung server & jaringan** | `APP_URL=https://lms-v2.laragon` (lokal); produksi akan berbeda (`MySQL` `root`/`root`, `Mailpit` `1025`, `Google Drive` media). Redesign ini hanya memastikan kode tidak memperburuk kondisi. | `ENVIRONMENT.md` 4, 5 |
| **Portofolio (Phase 4) tetap belum dimulai** | `docs/ROADMAP.md`: `3.1`–`3.5` masih `[ ]`; `docs/PHASE3.md` (sebelumnya `PHASE4.md`) adalah rancangan baru ini. | `docs/ROADMAP.md` 68–70 |

---

## 3.5 — Definisi Selesai & Verifikasi

| Kriteria | Cara Verifikasi |
|----------|-----------------|
| Performa (3.1) | `DB::listen()` atau `DEBUGBAR`: `loadLesson()` ≤ 3 query; `loadUserProgress()` 1 query |
| Efisiensi (3.2) | `curl -L` manual: `302` redirect ≤ 1 saat akses ditolak; `backUrl` konsisten saat `submitTestimonial()` |
| UX Feels (3.3) | `PlayerQuizTest` (3 PASSED), `Phase2AccessTest` (2 PASSED), visual manual: `wire:loading` terlihat; `authorize()` gagal tidak `abort()`; `transition` CSS aktif |
| Tidak merusak regresi | `php artisan test` (`234 passed`, `826 assertions` — sebelum redesign ini) tetap hijau setelah redesign diterapkan |

---

## Catatan Akhir (Matang)

- **Fase ini (`Phase 3`) bukan solusi ajaib.** Ia hanya mengurangi overhead yang bisa dikontrol (`N+1`, redirect loop, `abort()` tiba-tiba, `loading state` kosong). Batasan `Livewire` (`server round-trip`) tetap ada.
- Jika pengguna (`PT LAPI ITB`) membutuhkan respons sub-200ms secara konsisten pada semua perangkat, **redesign arsitektur lebih besar diperlukan**: memisahkan `Player` menjadi SPA (`Vue`/`React`) dengan Laravel API (`routes/api.php`), atau memindahkan `QuizService`/`AssignmentSubmissionService` ke worker `queue` (`Redis`) agar tidak memblokir `Livewire`.
- **Portofolio (`README`, diagram, video) digeser ke Phase 4** (`docs/ROADMAP.md` akan diupdate jika disetujui).
