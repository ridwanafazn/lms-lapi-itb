<?php

namespace App\Models;

use App\Models\Concerns\HasMembershipRequirement;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Program extends Model
{
    use HasFactory, HasMembershipRequirement, HasUuids, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'enrollment_code', // Baru
        'description',
        'short_description', // Baru
        'price',
        'type',
        'min_membership_level',
        'required_membership_plan_id',
        'is_active',
        'faq', // Baru
        'what_is_included', // Baru
        'pdf_brochure_url', // Baru
        'status', // Baru
        'rating_avg', // Baru
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'rating_avg' => 'float', // Konversi rating_avg ke float
        'is_active' => 'boolean',
        'faq' => 'array', // Konversi JSON ke Array otomatis
        'what_is_included' => 'array', // Konversi JSON ke Array otomatis
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'program_courses')
            ->withPivot('order_index')
            ->orderByPivot('order_index');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function enrollments()
    {
        return $this->morphMany(Enrollment::class, 'enrollable');
    }

    /**
     * Plan membership yang secara eksplisit disyaratkan (opsional).
     * Bila kosong, gunakan aturan level di HasMembershipRequirement.
     */
    public function requiredMembershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'required_membership_plan_id');
    }

    /**
     * Mengambil topik-topik dari course yang ada di dalam program ini
     * untuk digunakan sebagai 'tags' di frontend.
     */
    public function getTopicsAttribute()
    {
        // Tambahkan ->flatten() untuk melebur array ganda menjadi satu array tunggal
        return $this->courses->pluck('topics')->flatten()->filter()->unique()->values()->toArray();
    }

    /**
     * Menghitung total lesson (materi + kuis + tugas) dari semua course
     * di dalam program ini.
     *
     * Memakai withCount yang sudah dimuat bila tersedia, sehingga tidak
     * menambah query di dalam loop (lihat Phase 3.2). Bila tidak, jatuh ke
     * satu set query agregat untuk seluruh course program, bukan per course.
     */
    public function getLessonsCountAttribute()
    {
        $courses = $this->courses;

        if ($courses->isEmpty()) {
            return 0;
        }

        $hasCounts = $courses->every(fn ($course) => isset($course->materials_count, $course->quizzes_count, $course->assignments_count));

        if ($hasCounts) {
            return (int) $courses->sum(fn ($course) => $course->materials_count + $course->quizzes_count + $course->assignments_count);
        }

        $courseIds = $courses->pluck('id');

        return (int) \App\Models\Material::whereIn('course_id', $courseIds)->count()
            + \App\Models\Quiz::whereIn('course_id', $courseIds)->count()
            + \App\Models\Assignment::whereIn('course_id', $courseIds)->count();
    }
}
