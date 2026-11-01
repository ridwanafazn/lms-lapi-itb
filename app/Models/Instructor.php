<?php

namespace App\Models;

use App\Traits\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Instructor extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'instructors';

    protected $fillable = [
        'user_id',
        'instructor_type',
        'instructor_id',
        'is_primary',
        'role_label',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Mengambil data User (orangnya)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mengambil parent (Course/Batch/Program)
     */
    public function instructor(): MorphTo
    {
        return $this->morphTo();
    }
}
