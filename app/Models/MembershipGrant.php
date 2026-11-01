<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MembershipGrant extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id', 'user_id', 'subscription_id', 'membership_plan_id',
        'previous_plan_id', 'previous_expires_at', 'granted_from', 'granted_until',
        'grant_order', 'reversed_at',
    ];

    protected $casts = [
        'previous_expires_at' => 'datetime',
        'granted_from' => 'datetime',
        'granted_until' => 'datetime',
        'grant_order' => 'integer',
        'reversed_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function previousPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'previous_plan_id');
    }

    public function matchesCurrentMembership(User $user): bool
    {
        return $user->membership_plan_id === $this->membership_plan_id
            && ($this->granted_until === null
                ? $user->membership_expires_at === null
                : $user->membership_expires_at !== null && $this->granted_until->equalTo($user->membership_expires_at));
    }
}
