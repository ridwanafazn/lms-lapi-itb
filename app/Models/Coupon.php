<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['code', 'discount_type', 'discount_value', 'max_uses', 'valid_until', 'is_active'];

    protected $casts = [
        'valid_until' => 'datetime', 'is_active' => 'boolean',
        'discount_value' => 'decimal:2', 'used_count' => 'integer', 'max_uses' => 'integer',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }

    public function isValid(): bool
    {
        return $this->is_active
            && in_array($this->discount_type, ['percentage', 'fixed_amount'], true)
            && $this->discount_value > 0
            && ($this->discount_type !== 'percentage' || $this->discount_value <= 100)
            && ($this->valid_until === null || $this->valid_until->isFuture())
            && ($this->max_uses === null || $this->used_count + $this->redemptions()->where('status', 'reserved')
                ->when(\Illuminate\Support\Facades\DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->get(['id'])->count() < $this->max_uses);
    }

    public function applyTo(float $amount): float
    {
        $discount = $this->discount_type === 'percentage'
            ? $amount * min(100, max(0, (float) $this->discount_value)) / 100
            : max(0, (float) $this->discount_value);

        return round(max(0, $amount - $discount), 2);
    }
}
