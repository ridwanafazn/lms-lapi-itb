<?php

namespace App\Models;

use App\Data\TransactionPaidData;
use App\Events\TransactionPaid;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'invoice_code',
        'user_id',
        'program_id',
        'batch_id',
        'membership_plan_id',
        'amount',
        'payment_method',
        'payment_status',
        'proof_file_url',
        'transaction_date',
        'gateway_name',
        'gateway_reference',
        'snap_token',
        'redirect_url',
        'gateway_payload',
        'paid_at',
        'expired_at',
        'settlement_time',
        'subscription_id',
        'coupon_id',
        'original_amount',
        'discount_amount',
        'corporate_purchase_id',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'paid_at' => 'datetime',
        'expired_at' => 'datetime',
        'settlement_time' => 'datetime',
        'gateway_payload' => 'array',
        'original_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    // --- RELASI DATA ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    // --- ACCESSOR UI ---

    public function getPurchasedItemNameAttribute(): string
    {
        if ($this->membership_plan_id && $this->membershipPlan) {
            return 'Langganan Premium - '.ucfirst($this->membershipPlan->level);
        }

        if ($this->batch_id && $this->batch) {
            $programName = $this->batch->program ? $this->batch->program->title.' (' : '';
            $closingBracket = $this->batch->program ? ')' : '';

            return 'Pendaftaran Batch: '.$programName.$this->batch->batch_name.$closingBracket;
        }

        if ($this->program_id && $this->program) {
            return 'Program: '.$this->program->title;
        }

        return 'Item Tidak Diketahui';
    }

    // --- LOGIKA BISNIS (CORE LOGIC) ---

    public function isPaid(): bool
    {
        return $this->refunded_at === null && in_array($this->payment_status, ['paid', 'verified_by_admin'], true);
    }

    /**
     * Marks the transaction as paid and lets the domain react.
     *
     * The actual side effects (enrollment, membership, receipt e-mail, audit
     * log) live in listeners of TransactionPaid. Everything runs inside the
     * same database transaction, so a failure rolls the payment back too.
     */
    public function markAsPaid(array $gatewayData = []): void
    {
        $this->completePayment('paid', $gatewayData);
    }

    private function completePayment(string $status, array $gatewayData = []): void
    {
        DB::transaction(function () use ($status, $gatewayData) {
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $this->setRawAttributes($locked->getAttributes(), true);
            // Duplicate/replayed callbacks must not reactivate refunded access.
            if (in_array($this->payment_status, ['paid', 'verified_by_admin'], true)) {
                return;
            }
            if ($this->payment_status !== 'pending' || $this->refunded_at !== null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment' => 'Invoice tidak berlaku lagi. Rekonsiliasi pembayaran diperlukan sebelum memberikan akses.',
                ]);
            }
            $this->update([
                'payment_status' => $status,
                'paid_at' => now(),
                'settlement_time' => $gatewayData['settlement_time'] ?? now(),
                'gateway_reference' => $gatewayData['transaction_id'] ?? $this->gateway_reference,
                'gateway_payload' => $gatewayData ?: $this->gateway_payload,
            ]);

            app(\App\Services\CouponService::class)->redeem($this);
            TransactionPaid::dispatch(TransactionPaidData::fromTransaction($this->refresh()));
        }, 3);
        $this->refresh();
    }

    public function markAsExpired(): void
    {
        DB::transaction(function () {
            $locked = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if ($locked->payment_status === 'pending') {
                $locked->update(['payment_status' => 'failed', 'expired_at' => now()]);
                app(\App\Services\CouponService::class)->release($locked);
            }
        }, 3);
        $this->refresh();
    }

    public function rejectTransaction(): void
    {
        $this->markAsExpired();
    }

    public function approveTransaction(): void
    {
        $this->completePayment('verified_by_admin');
    }
}
