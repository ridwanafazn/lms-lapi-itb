<?php

namespace App\Models;

use App\Traits\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CourseTestimonial extends Model
{
    use HasUuids;

    protected $table = 'course_testimonials';

    protected $fillable = [
        'id',
        'user_id',
        'course_id',
        'rating_material',
        'rating_instructor',
        'rating_platform',
        'public_testimonial',
        'private_feedback',
        'status',
    ];

    /**
     * Relasi ke Siswa (User)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke Kelas (Course)
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
