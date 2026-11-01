<?php

namespace App\Models;

use App\Traits\HasUuids;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    use HasFactory, HasRoles, HasUuids, InteractsWithMedia, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'is_active', 'last_login',
        'membership_plan_id', 'membership_expires_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login' => 'datetime',
            'is_active' => 'boolean',
            'membership_expires_at' => 'datetime',
        ];
    }

    public function getDisplayRoleAttribute(): string
    {
        $role = $this->roles->first()?->name ?? 'student';

        return match ($role) {
            'admin' => 'Administrator',
            'instructor' => 'Instructor',
            'student' => 'Student',
            default => ucfirst($role),
        };
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function coursesCreated()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function batchesMentored()
    {
        return $this->hasMany(Batch::class, 'main_instructor_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->latest('created_at');
    }

    public function hasActiveMembership(): bool
    {
        if (! $this->membership_plan_id) {
            return false;
        }

        return $this->membership_expires_at === null || $this->membership_expires_at->isFuture();
    }

    public function getMembershipDaysLeftAttribute(): int
    {
        if (! $this->hasActiveMembership() || ! $this->membership_expires_at) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->membership_expires_at->startOfDay(), false));
    }

    public function canAccessProgram(Program $program): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if ($this->hasActiveMembership() && $program->isFreeForUser($this)) {
            return true;
        }

        $enrollment = $this->enrollments()
            ->where('enrollable_type', Program::class)
            ->where('enrollable_id', $program->id)
            ->where('status', 'active')
            ->first();
        if ($enrollment && $enrollment->access_expires_at && $enrollment->access_expires_at->lessThanOrEqualTo(now())) {
            return false;
        }

        return $enrollment ? true : false;
    }

    public function canAccessBatch(Batch $batch): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if ($this->hasActiveMembership() && $batch->isFreeForUser($this)) {
            return true;
        }

        $enrollmentBatch = $this->enrollments()
            ->where('enrollable_type', Batch::class)
            ->where('enrollable_id', $batch->id)
            ->where('status', 'active')
            ->first();
        if ($enrollmentBatch && $enrollmentBatch->access_expires_at && $enrollmentBatch->access_expires_at->lessThanOrEqualTo(now())) {
            return false;
        }

        return $enrollmentBatch ? true : false;
    }

    public function hasAccessToCourse(string $courseId): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        if ($this->coursesCreated()->where('id', $courseId)->exists()) {
            return true;
        }

        $programIds = DB::table('program_courses')->where('course_id', $courseId)->pluck('program_id');

        foreach ($programIds as $pId) {
            $program = Program::find($pId);
            if ($program && $this->canAccessProgram($program)) {
                return true;
            }
        }

        $batches = Batch::where(function ($query) use ($courseId, $programIds) {
            $query->where('course_id', $courseId)->orWhereIn('program_id', $programIds);
        })->get();
        foreach ($batches as $batch) {
            if ($this->canAccessBatch($batch)) {
                return true;
            }
        }

        return false;
    }

    // --- FITUR BARU: MENGGABUNGKAN PROGRAM BELI PUTUS & MEMBERSHIP ---
    public function getAccessibleProgramsAttribute()
    {
        // 1. Ambil ID Program dari jalur Beli Putus (Tabel Enrollments)
        $enrolledIds = $this->enrollments()
            ->where('enrollable_type', Program::class)
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now()))
            ->pluck('enrollable_id');

        // 2. Ambil ID Program dari jalur Membership
        $membershipProgramIds = collect();
        if ($this->hasActiveMembership() && $this->membershipPlan) {
            $allowedLevels = Program::levelsIncludedIn($this->membershipPlan->level);

            $membershipProgramIds = Program::query()
                ->where('is_active', true)
                ->where(function ($query) use ($allowedLevels) {
                    $query->where(fn ($levels) => $levels->whereNull('required_membership_plan_id')
                        ->whereIn('min_membership_level', $allowedLevels))
                        ->orWhere('required_membership_plan_id', $this->membership_plan_id);
                })
                ->pluck('id');
        }

        // 3. Gabungkan semua ID agar tidak ada duplikasi, lalu tarik data utuhnya
        $allAccessibleIds = $enrolledIds->merge($membershipProgramIds)->unique();

        return Program::whereIn('id', $allAccessibleIds)->get();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }
}
