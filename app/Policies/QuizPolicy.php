<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

class QuizPolicy
{
    public function view(User $user, Quiz $quiz): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (! $quiz->course) {
            return false;
        }

        if ($quiz->status !== 'active' && $quiz->status !== 'draft') {
            return false;
        }

        return $user->hasAccessToCourse($quiz->course_id);
    }

    public function update(User $user, Quiz $quiz): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        $course = $quiz->course;
        if ($course && (string) $course->instructor_id === (string) $user->id) {
            return true;
        }

        return false;
    }

    public function submit(User $user, Quiz $quiz): bool
    {
        return $this->view($user, $quiz);
    }
}
