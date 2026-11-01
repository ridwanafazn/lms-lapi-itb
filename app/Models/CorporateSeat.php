<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CorporateSeat extends Model
{
    use HasUuids;

    protected $fillable = ['corporate_purchase_id', 'user_id', 'allocated_by', 'enrollment_id', 'allocated_at'];

    protected $casts = ['allocated_at' => 'datetime'];

    public function purchase()
    {
        return $this->belongsTo(CorporatePurchase::class, 'corporate_purchase_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment()
    {
        return $this->belongsTo(Enrollment::class);
    }
}
