<?php

namespace App\Models;

use App\Traits\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutAudit extends Model
{
    use HasUuids;

    protected $fillable = ['payout_id', 'actor_id', 'action', 'details'];

    protected $casts = ['details' => 'array'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
