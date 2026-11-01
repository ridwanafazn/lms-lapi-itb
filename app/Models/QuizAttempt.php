<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'quiz_id',
        'user_id',
        'batch_id',
        'score',
        'current_question_index',
        'remaining_seconds',
        'is_timeout',
        'graded_at',
        'submitted_at',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'score' => 'float',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'graded_at' => 'datetime',
        'submitted_at' => 'datetime',
        'is_timeout' => 'boolean',
        'current_question_index' => 'integer',
        'remaining_seconds' => 'integer',
    ];

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null || $this->end_time !== null || $this->score !== null;
    }

    public function isPendingGrading(): bool
    {
        return $this->isSubmitted() && $this->score === null;
    }

    public function hasPassed(): bool
    {
        return $this->isSubmitted() && $this->score !== null
            && $this->graded_at !== null
            && $this->score >= (float) ($this->quiz->passing_grade ?? 70);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }
}
