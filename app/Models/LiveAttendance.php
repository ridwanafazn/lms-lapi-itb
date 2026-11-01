<?php

namespace App\Models;

use App\Traits\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveAttendance extends Model
{
    use HasUuids;

    protected $fillable = ['batch_id', 'material_id', 'user_id', 'status', 'notes', 'recorded_by', 'recorded_at'];

    protected $casts = ['recorded_at' => 'datetime'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }
}
