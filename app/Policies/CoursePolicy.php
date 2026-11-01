<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Determine whether the user can view the model.
     * (DIPERBARUI: Sekarang mengizinkan siswa yang punya akses, tidak hanya admin/instruktur)
     */
    public function view(User $user, Course $course): bool
    {
        // 1. Jika user memiliki hak edit (Admin atau Instruktur pembuatnya), pasti boleh lihat
        if ($this->update($user, $course)) {
            return true;
        }

        // 2. Jika user adalah siswa, cek apakah dia punya akses lewat Beli Putus atau Membership
        return $user->hasAccessToCourse($course->id);
    }

    /**
     * Determine whether the user can update the model.
     * (INI FUNGSI VITAL UNTUK AKSES BUILDER)
     */
    public function update(User $user, Course $course): bool
    {
        // 1. Admin SUPER POWER (Selalu Boleh)
        if ($user->hasRole('admin')) {
            return true;
        }

        // 2. Cek ID Pemilik Utama (Creator)
        if ((string) $course->instructor_id === (string) $user->id) {
            return true;
        }

        // 3. Cek Instruktur Pendamping (Co-Instructor)
        // Kita cek ke database: Apakah user ini terdaftar di tabel relasi instructors untuk course ini?
        return $course->instructors()
                      ->where('user_id', $user->id)
                      ->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Course $course): bool
    {
        // Delete biasanya lebih ketat, hanya Admin atau Pemilik Asli
        if ($user->hasRole('admin')) {
            return true;
        }

        return (string) $course->instructor_id === (string) $user->id;
    }
}
