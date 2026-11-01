<?php

namespace App\Models;

use App\Models\Concerns\HasMembershipRequirement;
use App\Traits\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
    use HasFactory, HasMembershipRequirement, HasUuids, SoftDeletes;

    protected $fillable = [
        'course_id',
        'program_id',
        'batch_name',
        'price',
        'min_membership_level',
        'start_date',
        'end_date',
        'registration_deadline', // Baru
        'quota', // Baru
        'location', // Baru
        'timeline_detail', // Baru
        'whatsapp_group_link',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quota' => 'integer',
        'start_date' => 'array',
        'end_date' => 'array',
        'registration_deadline' => 'array', // Konversi string date DB ke objek Carbon
        'timeline_detail' => 'array', // Konversi JSON ke Array otomatis
    ];

    // --- RELATIONS ---

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function instructors(): MorphMany
    {
        return $this->morphMany(Instructor::class, 'instructor');
    }

    public function enrollments(): MorphMany
    {
        return $this->morphMany(Enrollment::class, 'enrollable');
    }

    // --- CUSTOM ACCESSORS & LOGIC ---

    /**
     * Menerapkan Price Inheritance (Harga Turunan).
     * Jika harga Batch 0 (atau null), maka ambil harga dari Program Induknya.
     * Jika tidak punya Program Induk atau harga Program juga 0, barulah dianggap Gratis.
     */
    public function getEffectivePriceAttribute(): float
    {
        // Jika batch memiliki harganya sendiri (di atas nol), gunakan harga tersebut.
        if ($this->price > 0) {
            return (float) $this->price;
        }

        // Jika harga batch 0, cek apakah ia memiliki program induk.
        if ($this->program_id && $this->program) {
            return (float) $this->program->price;
        }

        // Jika tidak ada program atau harga program juga 0, kembalikan 0 (Gratis)
        return 0.00;
    }

    public function isEnrolledBy($userId)
    {
        return $this->enrollments()->where('user_id', $userId)->exists();
    }
}
