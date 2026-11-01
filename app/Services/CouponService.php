<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function reserve(Transaction $transaction, string $code): void
    {
        DB::transaction(function () use ($transaction, $code) {
            $coupon = Coupon::where('code', strtoupper(trim($code)))->lockForUpdate()->first();
            if (! $coupon) {
                throw ValidationException::withMessages(['couponCode' => 'Kupon tidak ditemukan.']);
            }
            $transaction = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($transaction->corporate_purchase_id) {
                throw ValidationException::withMessages(['couponCode' => 'Kupon belum didukung untuk pembelian korporat.']);
            }
            $existing = CouponRedemption::where('transaction_id', $transaction->id)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->coupon_id === $coupon->id && $existing->status === 'reserved') {
                    return;
                }
                throw ValidationException::withMessages(['couponCode' => 'Satu transaksi hanya boleh memakai satu kupon.']);
            }
            if ($transaction->payment_status !== 'pending' || $transaction->coupon_id) {
                throw ValidationException::withMessages(['couponCode' => 'Kupon tidak dapat ditambahkan ke transaksi ini.']);
            }

            // Reservations do not expire by time: late gateway settlements must not overspend a coupon.
            // Only explicitly failed invoices release their reservation.
            $failedIds = Transaction::where('coupon_id', $coupon->id)->where('payment_status', 'failed')->pluck('id');
            CouponRedemption::where('coupon_id', $coupon->id)->where('status', 'reserved')
                ->whereIn('transaction_id', $failedIds)->update(['status' => 'released']);
            if (! $coupon->isValid()) {
                throw ValidationException::withMessages(['couponCode' => 'Kupon tidak aktif, kedaluwarsa, atau kuotanya habis.']);
            }
            $original = (float) ($transaction->original_amount ?? $transaction->amount);
            $amount = $coupon->applyTo($original);
            $discount = round($original - $amount, 2);
            $transaction->forceFill([
                'coupon_id' => $coupon->id, 'original_amount' => $original,
                'discount_amount' => $discount, 'amount' => $amount,
            ])->save();
            CouponRedemption::create([
                'coupon_id' => $coupon->id, 'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id, 'discount_amount' => $discount, 'status' => 'reserved',
            ]);
        }, 3);
        $transaction->refresh();
    }

    /** Call inside the payment transaction; valid for both gateway and manual approval. */
    public function redeem(Transaction $transaction): void
    {
        if (! $transaction->coupon_id) {
            return;
        }
        DB::transaction(function () use ($transaction) {
            $coupon = Coupon::whereKey($transaction->coupon_id)->lockForUpdate()->firstOrFail();
            $redemption = CouponRedemption::where('transaction_id', $transaction->id)->lockForUpdate()->first();
            if (! $redemption || $redemption->coupon_id !== $coupon->id || $redemption->status === 'released') {
                throw ValidationException::withMessages(['couponCode' => 'Reservasi kupon tidak berlaku untuk pembayaran ini.']);
            }
            if ($redemption->status === 'redeemed') {
                return;
            }
            if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
                throw ValidationException::withMessages(['couponCode' => 'Kuota kupon telah habis.']);
            }
            $redemption->update(['status' => 'redeemed', 'redeemed_at' => now()]);
            $coupon->increment('used_count');
        }, 3);
    }

    public function release(Transaction $transaction): void
    {
        if (! $transaction->coupon_id) {
            return;
        }
        DB::transaction(function () use ($transaction) {
            Coupon::whereKey($transaction->coupon_id)->lockForUpdate()->firstOrFail();
            $fresh = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($fresh->payment_status === 'failed') {
                CouponRedemption::where('transaction_id', $fresh->id)->where('status', 'reserved')
                    ->update(['status' => 'released']);
            }
        }, 3);
    }
}
