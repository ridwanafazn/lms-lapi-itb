<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'enrollable_type',
        'enrollable_id',
        'enrolled_at',
        'access_expires_at',
        'status',
        'progress_percentage',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'access_expires_at' => 'datetime',
        'progress_percentage' => 'float',
    ];

    public function enrollable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getIsProgramAttribute(): bool
    {
        return $this->enrollable_type === Program::class;
    }

    public function getIsCourseAttribute(): bool
    {
        return $this->enrollable_type === Course::class;
    }

    public function getTitleAttribute(): string
    {
        $entity = $this->enrollable;

        if (! $entity) {
            return 'Kelas Tanpa Judul';
        }

        if ($entity instanceof Program) {
            return $entity->title ?? 'Program Tanpa Judul';
        }
        if ($entity instanceof Course) {
            return $entity->title ?? 'Course Tanpa Judul';
        }

        if ($entity instanceof Batch) {
            return $entity->course->title
                ?? $entity->program->title
                ?? $entity->batch_name;
        }

        return 'Kelas Tanpa Judul';
    }

    // PEMBARUAN: Memastikan Program Mandiri tetap punya gambar yang diambil dari course pertamanya
    public function getThumbnailAttribute(): ?string
    {
        $entity = $this->enrollable;

        if ($entity instanceof Course) {
            return $entity->thumbnail_url;
        }

        if ($entity instanceof Program) {
            return $entity->courses->first()?->thumbnail_url;
        }

        if ($entity instanceof Batch) {
            return $entity->course?->thumbnail_url ?? $entity->program?->courses->first()?->thumbnail_url;
        }

        return null;
    }

    public function getInstructorNameAttribute(): string
    {
        $entity = $this->enrollable;

        if (! $entity) {
            return 'Tim Instruktur';
        }

        $course = null;

        if ($entity instanceof Program) {
            $course = $entity->courses->first();
        } elseif ($entity instanceof Course) {
            $course = $entity;
        } elseif ($entity instanceof Batch) {
            $course = $entity->course ?? $entity->program?->courses->first();
        }

        return $course ? ($course->instructor->name ?? 'Tim Instruktur') : 'Tim Instruktur';
    }

    public function getContextTitleAttribute(): string
    {
        $entity = $this->enrollable;

        if (! $entity) {
            return '-';
        }

        if ($entity instanceof Program) {
            return $entity->title ?? '-';
        }
        if ($entity instanceof Course) {
            return $entity->title ?? '-';
        }

        return $entity->course->title ?? $entity->program->title ?? '-';
    }
}
