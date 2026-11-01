<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'quiz_id',
        'question_text',
        'question_type',
        'score_weight',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('order_index');
    }

    public function isEssay(): bool
    {
        return $this->question_type === 'essay';
    }

    /**
     * Skema saat ini hanya punya `multiple_choice` dan `essay`, sehingga
     * multi-jawaban dikenali dari jumlah opsi yang benar (> 1).
     */
    public function isMultipleSelect(): bool
    {
        $options = $this->relationLoaded('options') ? $this->options : $this->options()->get();

        return $options->where('is_correct', true)->count() > 1;
    }
}
