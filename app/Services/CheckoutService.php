<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\CorporatePurchase;
use App\Models\MembershipPlan;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Midtrans\Config;
use Midtrans\Snap;
use Throwable;

class CheckoutService
{
    public function __construct(private CouponService $coupons) {}

    public function create(
        User $buyer,
        Program|Batch|MembershipPlan $item,
        ?string $couponCode = null,
        string $method = 'gateway_va',
        ?CorporatePurchase $purchase = null,
    ): Transaction {
        if (! $buyer->hasVerifiedEmail()) {
            throw new AuthorizationException;
        }
        if (! in_array($method, ['gateway_va', 'manual_transfer'], true)) {
            throw ValidationException::withMessages(['paymentMethod' => 'Metode pembayaran tidak valid.']);
        }
        if ($purchase && filled($couponCode)) {
            throw ValidationException::withMessages(['couponCode' => 'Kupon belum didukung untuk pembelian korporat.']);
        }
        $transaction = DB::transaction(function () use ($buyer, $item, $couponCode, $method, $purchase) {
            $item = $item->fresh();
            if (! $item) {
                throw ValidationException::withMessages(['checkout' => 'Produk tidak tersedia.']);
            }
            $this->assertAvailable($item);
            $quantity = 1;
            if ($purchase) {
                $purchase = CorporatePurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
                $targetId = $purchase->batch_id ?: $purchase->program_id;
                if ($purchase->buyer_user_id !== $buyer->id || $targetId !== $item->id
                    || $item instanceof MembershipPlan || $purchase->transaction()->exists()) {
                    throw new AuthorizationException;
                }
                $quantity = $purchase->seat_count;
            }
            $price = $purchase ? (float) $purchase->unit_amount : $this->price($item);
            if (! $purchase && ! ($item instanceof MembershipPlan) && $item->isFreeForUser($buyer)) {
                $price = 0;
            }
            $transaction = new Transaction([
                'invoice_code' => ($purchase ? 'INV-CORP-' : 'INV-').Str::upper(Str::random(16)),
                'user_id' => $buyer->id,
                'program_id' => $item instanceof Program ? $item->id : null,
                'batch_id' => $item instanceof Batch ? $item->id : null,
                'membership_plan_id' => $item instanceof MembershipPlan ? $item->id : null,
                'amount' => round($price * $quantity, 2),
                'payment_method' => $method, 'payment_status' => 'pending',
                'gateway_name' => $method === 'gateway_va' ? 'midtrans' : 'manual',
                'transaction_date' => now(),
            ]);
            $transaction->forceFill([
                'original_amount' => round($price * $quantity, 2), 'discount_amount' => 0,
                'corporate_purchase_id' => $purchase?->id,
            ])->save();
            if (filled($couponCode)) {
                if ($price <= 0) {
                    throw ValidationException::withMessages(['couponCode' => 'Kupon hanya berlaku untuk pembelian berbayar.']);
                }
                $this->coupons->reserve($transaction, $couponCode);
            }
            if ((float) $transaction->amount === 0.0) {
                $transaction->forceFill(['payment_method' => 'free_access', 'gateway_name' => 'manual'])->save();
                $transaction->markAsPaid();
            }

            return $transaction;
        }, 3);

        if ($method === 'gateway_va' && ! $transaction->isPaid()) {
            $this->startGateway($transaction);
        }

        return $transaction->refresh();
    }

    public function price(Program|Batch|MembershipPlan $item): float
    {
        return max(0, (float) ($item instanceof Batch ? $item->effective_price : $item->price));
    }

    public function assertAvailable(Program|Batch|MembershipPlan $item): void
    {
        if (($item instanceof Batch && $item->status !== 'open')
            || (! ($item instanceof Batch) && ! $item->is_active)
            || ($item instanceof Batch && $item->program_id && ! $item->program?->is_active)) {
            throw ValidationException::withMessages(['checkout' => 'Produk tidak tersedia untuk dibeli.']);
        }
    }

    public function startGateway(Transaction $transaction): void
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = (bool) config('services.midtrans.is_production');
        Config::$isSanitized = (bool) config('services.midtrans.is_sanitized');
        Config::$is3ds = (bool) config('services.midtrans.is_3ds');
        $params = [
            'transaction_details' => ['order_id' => $transaction->invoice_code, 'gross_amount' => (float) $transaction->amount],
            'customer_details' => ['first_name' => $transaction->user->name, 'email' => $transaction->user->email],
        ];
        try {
            $token = Snap::getSnapToken($params);
            $transaction->update(['snap_token' => $token, 'gateway_payload' => $params]);
        } catch (Throwable $exception) {
            // An ambiguous gateway timeout may still have created an order. Keep the reservation
            // until an explicit cancellation/expiry; releasing here could overspend on late payment.
            report($exception);
            throw ValidationException::withMessages(['checkout' => 'Gateway belum dapat dihubungi. Invoice tersimpan; periksa riwayat transaksi sebelum mencoba lagi.']);
        }
    }
}
