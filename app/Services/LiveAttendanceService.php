<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\LiveAttendance;
use App\Models\Material;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LiveAttendanceService
{
    public function __construct(private OperationsAccess $access) {}

    public function sessions(User $actor, Batch $batch): Builder
    {
        $this->authorizeBatch($actor, $batch);
        $query = Material::where('type', 'live_session')->whereIn('course_id', $this->access->courseIds($batch));
        // Batch/program assignments cover all sessions; course assignments cover only that course.
        if (! $actor->hasRole('admin') && ! $this->access->assignedToBatchOrProgram($actor, $batch)) {
            $query->whereHas('course', fn (Builder $courses) => $courses->where(function (Builder $query) use ($actor) {
                $query->where('instructor_id', $actor->id)
                    ->orWhereHas('instructors', fn (Builder $instructors) => $instructors->where('user_id', $actor->id));
            }));
        }

        return $query;
    }

    public function roster(User $actor, Batch $batch): Builder
    {
        $this->authorizeBatch($actor, $batch);

        return User::whereHas('enrollments', fn (Builder $query) => $query
            ->where('enrollable_type', $batch->getMorphClass())->where('enrollable_id', $batch->id)
            ->whereIn('status', ['active', 'completed']));
    }

    public function record(User $actor, Batch $batch, Material $material, User $student, string $status, ?string $notes = null): LiveAttendance
    {
        $this->access->staff($actor);
        Validator::make(compact('status', 'notes'), [
            'status' => ['required', 'in:present,absent,excused'], 'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $batch, $material, $student, $status, $notes) {
            $batch = Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $material = Material::with('course')->findOrFail($material->id);
            if (! $actor->hasRole('admin') && ! $this->access->relatedInstructor($actor, $batch, $material->course)) {
                throw new AuthorizationException;
            }
            if ($material->type !== 'live_session' || ! in_array($material->course_id, $this->access->courseIds($batch), true)) {
                throw ValidationException::withMessages(['materialId' => 'Sesi live harus berasal dari kelas pada batch ini.']);
            }
            if (! $this->roster($actor, $batch)->whereKey($student->id)->exists()) {
                throw ValidationException::withMessages(['studentId' => 'Peserta harus terdaftar aktif atau selesai pada batch ini.']);
            }
            $attendance = LiveAttendance::where('batch_id', $batch->id)->where('material_id', $material->id)
                ->where('user_id', $student->id)->first();
            $previous = $attendance?->only(['status', 'notes']);
            $attendance = LiveAttendance::updateOrCreate([
                'batch_id' => $batch->id, 'material_id' => $material->id, 'user_id' => $student->id,
            ], ['status' => $status, 'notes' => $notes, 'recorded_by' => $actor->id, 'recorded_at' => now()]);
            ActivityLog::create([
                'actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => 'live_attendance_recorded',
                'description' => json_encode([
                    'attendance_id' => $attendance->id, 'batch_id' => $batch->id,
                    'material_id' => $material->id, 'user_id' => $student->id,
                    'previous' => $previous, 'current' => $attendance->only(['status', 'notes']),
                ], JSON_THROW_ON_ERROR),
            ]);

            return $attendance;
        }, 3);
    }

    public function authorizeBatch(User $actor, Batch $batch): void
    {
        $this->access->staff($actor);
        if (! $actor->hasRole('admin') && ! $this->access->relatedInstructor($actor, $batch)) {
            throw new AuthorizationException;
        }
    }
}
