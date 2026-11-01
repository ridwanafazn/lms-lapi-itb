<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (! $assignment->course) {
            return false;
        }

        return $user->hasAccessToCourse($assignment->course_id);
    }

    public function update(User $user, Assignment $assignment): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $course = $assignment->course;
        if ($course && (string) $course->instructor_id === (string) $user->id) {
            return true;
        }

        return false;
    }

    public function submit(User $user, Assignment $assignment): bool
    {
        return $this->view($user, $assignment);
    }
}
