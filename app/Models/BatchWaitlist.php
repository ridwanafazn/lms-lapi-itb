<?php

namespace App\Models;

use App\Traits\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchWaitlist extends Model
{
    use HasUuids;

    protected $fillable = [
        'batch_id', 'user_id', 'queue_number', 'status', 'joined_at',
        'promoted_at', 'promoted_by', 'cancelled_at',
    ];

    protected $casts = [
        'queue_number' => 'integer', 'joined_at' => 'datetime',
        'promoted_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
