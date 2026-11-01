<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'lessons';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'section_id',
        'sort_order',
        'item_type',
        'material_id',
        'quiz_id',
        'assignment_id',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'assignment_id');
    }

    public function getContentAttribute()
    {
        return match ($this->item_type) {
            'material' => $this->material,
            'quiz' => $this->quiz,
            'assignment' => $this->assignment,
            default => null,
        };
    }
}
