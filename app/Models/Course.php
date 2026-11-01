<?php

namespace App\Models;

use App\Enums\CourseStatus;
use App\Traits\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'course_code', // Baru
        'topics', // Baru
        'description',
        'level',
        'delivery_mode',
        'thumbnail_url',
        'status',
        'moderation_note',
        'published_at',
        'instructor_id',
        'tools_used', // Baru
        'target_audience', // Baru
        'prerequisites', // Baru
        'learning_outcomes', // Baru
        'language', // Baru
        'curriculum_summary', // Baru
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'published_at' => 'datetime',
            'tools_used' => 'array', // Konversi JSON ke Array otomatis
            'learning_outcomes' => 'array', // Konversi JSON ke Array otomatis
            'topics' => 'array', // Konversi JSON ke Array otomatis
            'target_audience' => 'array', // Tambahkan ini
            'prerequisites' => 'array',   // Tambahkan ini
        ];
    }

    // --- SCOPES ---

    public function scopePublished(Builder $query): void
    {
        $query->where('status', CourseStatus::PUBLISHED);
    }

    // --- RELATIONSHIPS (MAIN) ---

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function instructors(): MorphMany
    {
        return $this->morphMany(Instructor::class, 'instructor');
    }

    public function getInstructorAttribute()
    {
        $instructorModel = $this->instructors->where('is_primary', true)->first()
            ?? $this->instructors->first();

        if (! $instructorModel && $this->instructor_id) {
            return User::find($this->instructor_id);
        }

        return $instructorModel ? $instructorModel->user : null;
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'program_courses')
            ->withPivot('order_index');
    }

    public function enrollments(): MorphMany
    {
        return $this->morphMany(Enrollment::class, 'enrollable');
    }

    // --- RELATIONSHIPS (CURRICULUM STRUCTURE) ---

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order_index');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class)->orderBy('created_at', 'desc');
    }
}
