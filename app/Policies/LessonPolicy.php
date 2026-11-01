<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    /**
     * Admin & instructor pembuat bisa akses semua.
     */
    public function view(User $user, Lesson $lesson): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Cek akses lewat Course
        if (! $lesson->section) {
            return false;
        }

        $course = $lesson->section?->course ?? null;
        if (! $course) {
            return false;
        }

        return $user->hasAccessToCourse($course->id);
    }

    public function update(User $user, Lesson $lesson): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $course = $lesson->section?->course ?? null;
        if ($course && (string) $course->instructor_id === (string) $user->id) {
            return true;
        }

        return false;
    }
}
