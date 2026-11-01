<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Discussion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'course_id',
        'user_id',
        'title',
        'body',
        'is_solved',
        'is_locked',
    ];

    protected $casts = [
        'is_solved' => 'boolean',
        'is_locked' => 'boolean',
    ];

    // --- RELATIONSHIPS ---

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionReply::class)->orderBy('created_at', 'asc');
    }

    public function bestAnswer(): HasOne
    {
        return $this->hasOne(DiscussionReply::class)->where('is_best_answer', true);
    }

    // Relasi Polymorphic ke tabel Media
    public function attachments()
    {
        // Asumsi: Anda mungkin belum punya Model Media, jadi kita pakai string namespace
        // Jika Anda pakai package Spatie, sesuaikan. Jika manual, ini sudah standar Laravel.
        return $this->morphMany(\App\Models\Media::class, 'model');
    }

    // --- SCOPES ---

    public function scopeSolved($query)
    {
        return $query->where('is_solved', true);
    }

    public function scopeUnsolved($query)
    {
        return $query->where('is_solved', false);
    }
}
