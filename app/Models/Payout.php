<?php

namespace App\Models;

use App\Traits\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use HasUuids;

    protected $fillable = [
        'batch_id', 'instructor_user_id', 'amount', 'status', 'notes', 'created_by',
        'paid_by', 'paid_at', 'transfer_reference', 'evidence_path', 'evidence_name', 'evidence_sha256',
    ];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class)->withTrashed();
    }

    public function instructorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_user_id')->withTrashed();
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by')->withTrashed();
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PayoutAudit::class)->orderBy('created_at')->orderBy('id');
    }
}
