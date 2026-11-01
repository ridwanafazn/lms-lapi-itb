<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'level',
        'billing_period',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'membership_plan_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'membership_plan_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return ucfirst($this->level).' - '.ucfirst($this->billing_period);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
