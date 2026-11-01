# CONVENTIONS

Tujuan: kode baru terasa seperti kode lama, dan mudah dites.

## 1. Penamaan

| Hal | Aturan | Contoh |
|-----|--------|--------|
| Class | PascalCase | `FulfillEnrollment` |
| Method | camelCase, kata kerja | `markAsPaid()`, `canAccessProgram()` |
| Kolom DB | snake_case | `membership_expires_at` |
| Event | past tense | `TransactionPaid` |
| Listener | kata kerja + objek | `ActivateMembership` |
| DTO | `{Entity}{Event}Data` | `TransactionPaidData` |
| Boolean | awalan `is/has/can` | `is_active`, `hasTestimonial` |
| Tabel | plural snake_case | `course_testimonials` |

## 2. "Kode ini taruh di mana?"

| Isi kode | Lokasi |
|----------|--------|
| Efek samping dari satu kejadian domain | `app/Listeners` |
| Data yang dikirim antar layer / ke queue | `app/Data` (readonly DTO) |
| Panggilan API pihak ketiga | `app/Services` |
| Aturan akses user | method di `app/Models/User` atau `app/Policies` |
| Query kompleks yang dipakai berulang | scope di model |
| State UI (form, filter, pagination) | komponen Livewire |
| Query ringan khusus satu halaman | langsung di komponen Livewire |

Aturan praktis: **jika logika dipakai lebih dari satu komponen, keluarkan dari komponen.**

## 3. Livewire

- Gunakan `#[Layout(...)]` bila halaman butuh layout berbeda.
- Properti publik hanya untuk data yang benar-benar dipakai view; sisanya `protected`/lokal.
- Validasi di komponen boleh, tetapi jika aturan dipakai >1 tempat → pindah ke FormRequest atau rule class.
- Hindari query di dalam `render()` yang bergantung pada state yang sering berubah.
- Gunakan `wire:model.lazy` / `.debounce` untuk input teks panjang (hemat round-trip).
- Jangan menaruh logika pembayaran/otorisasi di view.

## 4. Eloquent

- Selalu pakai relasi, bukan `DB::table()` untuk data domain
  (`DB::table` hanya untuk agregasi/progress yang butuh kecepatan).
- Eager load relasi yang dipakai loop (`->with([...])`).
- Cast JSON → `array` di `casts()`, jangan decode manual di view.
- UUID: model baru wajib `use HasUuids`.
- Migration: **jangan edit migration lama**, buat migration baru.

## 5. Event & Listener

- Event = fakta yang sudah terjadi (`TransactionPaid`), bukan perintah (`SendEmail`).
- Listener sinkron hanya untuk hal yang **wajib konsisten** dengan transaksi (akses, uang).
- Listener `ShouldQueue` untuk hal yang boleh terlambat (email, notifikasi, laporan).
- Listener wajib **idempoten**: aman dijalankan dua kali.
- Setiap listener yang mengubah data harus `Log::info()` dengan konteks (`invoice`, `user_id`).

## 6. Bahasa

- Identifier, komentar kode, commit message: **Inggris**.
- Teks yang dilihat user, dokumentasi `docs/`: **Indonesia**.

## 7. Gaya kode

- Ikuti PSR-12; jalankan `vendor/bin/pint` sebelum commit.
- Urutan method dalam class: relasi → scope → accessor → aksi publik → helper privat.
- Hindari `try { } catch (\Exception $e) {}` yang menelan error tanpa log
  (contoh yang **harus diperbaiki** ada di `Player::executeCompletion()`).

## 8. Commit message

```
<tipe>(<area>): <ringkasan imperatif>

contoh:
feat(payment): dispatch TransactionPaid event on settlement
fix(membership): extend expiry from current date on renewal
refactor(player): extract progress calculation
docs(agents): add environment and workflow guides
test(payment): cover duplicate webhook idempotency
```

Tipe: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `perf`, `security`.
