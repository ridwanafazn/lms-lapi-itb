<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id', 'membership_plan_id', 'billing_period', 'status',
        'next_billing_at', 'source_transaction_id', 'pending_transaction_id', 'cancelled_at',
    ];

    protected $casts = [
        'next_billing_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'billing_period' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'source_transaction_id');
    }

    public function pendingTransaction()
    {
        return $this->belongsTo(Transaction::class, 'pending_transaction_id');
    }

    public function invoices()
    {
        return $this->hasMany(Transaction::class);
    }

    public function grants()
    {
        return $this->hasMany(MembershipGrant::class);
    }

    public function isDueForRenewal(): bool
    {
        return $this->status === 'active'
            && ! in_array(strtolower($this->billing_period), ['lifetime', 'selamanya'], true)
            && $this->next_billing_at !== null
            && $this->next_billing_at->lessThanOrEqualTo(now());
    }
}
