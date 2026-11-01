<?php

namespace App\Models;

use App\Services\CorporatePurchaseService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporatePurchase extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_name', 'buyer_user_id', 'program_id', 'batch_id', 'seat_count',
        'unit_amount', 'total_amount', 'status', 'paid_at',
    ];

    protected $casts = [
        'seat_count' => 'integer', 'unit_amount' => 'decimal:2',
        'total_amount' => 'decimal:2', 'paid_at' => 'datetime',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function seats()
    {
        return $this->hasMany(CorporateSeat::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function fulfill(array $userIds = []): int
    {
        return app(CorporatePurchaseService::class)->allocate(auth()->user(), $this, $userIds);
    }
}
