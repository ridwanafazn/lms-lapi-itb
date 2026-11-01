<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CouponRedemption extends Model
{
    use HasUuids;

    protected $fillable = ['coupon_id', 'transaction_id', 'user_id', 'discount_amount', 'status', 'redeemed_at'];

    protected $casts = ['discount_amount' => 'decimal:2', 'redeemed_at' => 'datetime'];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
