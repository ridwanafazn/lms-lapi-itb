<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\Program;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class OperationsAccess
{
    public function admin(User $actor): void
    {
        if (! $actor->hasRole('admin')) {
            throw new AuthorizationException('Hanya admin yang dapat melakukan tindakan ini.');
        }
    }

    public function staff(User $actor): void
    {
        if (! $actor->hasAnyRole(['admin', 'instructor'])) {
            throw new AuthorizationException;
        }
    }

    public function courseIds(Batch $batch): array
    {
        return Course::query()->where(function (Builder $query) use ($batch) {
            $query->whereKey($batch->course_id ?? '');
            if ($batch->program_id) {
                $query->orWhereHas('programs', fn (Builder $programs) => $programs->whereKey($batch->program_id));
            }
        })->pluck('id')->all();
    }

    public function relatedInstructor(User $actor, Batch $batch, ?Course $course = null): bool
    {
        if (! $actor->hasRole('instructor')) {
            return false;
        }
        if ($this->assignedToBatchOrProgram($actor, $batch)) {
            return true;
        }

        return Course::query()->whereIn('id', $course ? [$course->id] : $this->courseIds($batch))
            ->where(function (Builder $query) use ($actor) {
                $query->where('instructor_id', $actor->id)
                    ->orWhereHas('instructors', fn (Builder $instructors) => $instructors->where('user_id', $actor->id));
            })->exists();
    }

    public function assignedToBatchOrProgram(User $actor, Batch $batch): bool
    {
        return $actor->hasRole('instructor') && (
            $batch->instructors()->where('user_id', $actor->id)->exists()
            || ($batch->program_id && Instructor::where('user_id', $actor->id)
                ->where('instructor_type', (new Program)->getMorphClass())
                ->where('instructor_id', $batch->program_id)->exists())
        );
    }

    public function batches(User $actor): Builder
    {
        $this->staff($actor);
        $query = Batch::query();
        if ($actor->hasRole('admin')) {
            return $query;
        }
        $assigned = fn (Builder $instructors) => $instructors->where('user_id', $actor->id);
        $courses = fn (Builder $courses) => $courses->where(function (Builder $query) use ($actor, $assigned) {
            $query->where('instructor_id', $actor->id)->orWhereHas('instructors', $assigned);
        });

        $programIds = Instructor::where('user_id', $actor->id)
            ->where('instructor_type', (new Program)->getMorphClass())->select('instructor_id');

        return $query->where(function (Builder $query) use ($assigned, $courses, $programIds) {
            $query->whereHas('instructors', $assigned)
                ->orWhereIn('program_id', $programIds)
                ->orWhereHas('course', $courses)
                ->orWhereHas('program.courses', $courses);
        });
    }
}
