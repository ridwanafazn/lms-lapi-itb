# WORKFLOW — Cara Kerja Agent / Developer

## 0. Prasyarat sebelum mulai

1. Service hidup: Apache, MySQL, Vite, queue worker (lihat `ENVIRONMENT.md`).
2. `php artisan optimize:clear` sudah dijalankan.
3. `git status` bersih, atau kamu tahu persis perubahan yang belum di-commit.

## 1. Loop kerja standar

```
1. PILIH satu task dari docs/ROADMAP.md
2. BACA kode terkait (grep dulu, jangan menebak)
3. TULIS test yang gagal  (jika menyentuh perilaku bisnis)
4. IMPLEMENTASI sampai test hijau
5. JALANKAN seluruh test  → pastikan tidak merusak yang lain
6. VERIFIKASI manual di https://LMS-V2.laragon
7. UPDATE docs (ROADMAP / KNOWN_ISSUES / DECISIONS)
8. COMMIT dengan format di CONVENTIONS.md
```

## 2. Definition of Done

Sebuah task selesai hanya jika **semua** terpenuhi:

- [ ] Test baru menutup perilaku yang berubah (`php artisan test` hijau).
- [ ] Tidak ada error di `storage/logs/laravel.log` setelah verifikasi manual.
- [ ] `php -l` bersih untuk setiap file PHP baru/berubah.
- [ ] Tidak ada `dd()`, `dump()`, `var_dump()`, atau kode debug tertinggal.
- [ ] Tidak ada kredensial/`.env` yang berubah ikut ter-commit.
- [ ] `docs/ROADMAP.md` menandai task selesai.
- [ ] Commit message sesuai konvensi.

## 3. Cara memilih task

Prioritas dari atas:

1. **P0 — uang & keamanan**: pembayaran, akses, otorisasi, data hilang.
2. **P1 — kebenaran data**: perhitungan nilai, progres, kedaluwarsa membership.
3. **P2 — performa**: N+1, query lambat, payload Livewire.
4. **P3 — fitur bisnis baru**.

Jangan mengerjakan P3 saat masih ada P0 terbuka.

## 4. Saat menemukan bug di luar scope

1. Jangan langsung perbaiki jika menyentuh aturan bisnis.
2. Tulis di `docs/KNOWN_ISSUES.md`: gejala, lokasi, dugaan penyebab, dampak.
3. Lanjutkan task yang sedang dikerjakan.
4. Baru perbaiki setelah user menyetujui prioritasnya.

## 5. Aturan khusus untuk agent AI

- **Selalu baca file sebelum mengedit.** Jangan mengandalkan ingatan dari ringkasan.
- **Edit sekecil mungkin**; jangan refactor file lain "sekalian".
- **Jangan mengubah migration yang sudah pernah dijalankan.**
- **Jangan menebak aturan bisnis** (harga, masa aktif, siapa boleh akses).
  Jika tidak yakin → tanya user.
- **Jangan menambah package** tanpa persetujuan.
- **Jangan menghapus test** yang gagal karena bug; perbaiki kodenya, atau
  tandai skip dengan alasan jelas dan catat di `KNOWN_ISSUES.md`.
- Setelah mengubah `.env`, config, atau route: jalankan `php artisan optimize:clear`.
- Setelah menambah class baru di `app/`: jalankan `composer dump-autoload` bila
  class tidak terdeteksi.

## 6. Verifikasi manual minimum

| Area yang diubah | Cara verifikasi |
|------------------|-----------------|
| Pembayaran | Buat transaksi, `markAsPaid`, cek tabel `enrollments`, `activity_logs`, Mailpit |
| Akses/otorisasi | Login sebagai student yang belum membeli → harus 403/redirect |
| Player / quiz | Selesaikan lesson berurutan, cek `lesson_completions` |
| Sertifikat | Selesaikan 100% → kirim testimoni → buka `/certificates/{code}` |
| UI admin/instruktur | Buka halaman terkait, pastikan tidak ada 500 |

## 7. Rollback

Jika perubahan merusak: `git stash` / `git checkout -- <file>`, lalu
`php artisan optimize:clear`. Jangan reset database development tanpa izin.
