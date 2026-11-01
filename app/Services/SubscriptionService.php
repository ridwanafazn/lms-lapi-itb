<?php

namespace App\Services;

use App\Models\MembershipGrant;
use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    /** Called synchronously inside the payment transaction; never initiates a payment. */
    public function activateFromPaidTransaction(Transaction $transaction): MembershipGrant
    {
        return DB::transaction(function () use ($transaction) {
            $transaction = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($transaction->user_id)->lockForUpdate()->firstOrFail();
            $existing = MembershipGrant::where('transaction_id', $transaction->id)->first();
            if ($existing) {
                // Replayed events, including events for reversed grants, must not grant access again.
                return $existing;
            }
            if (! $transaction->isPaid() || ! $transaction->membership_plan_id) {
                $this->invalid('Transaksi membership belum lunas.');
            }

            $plan = MembershipPlan::findOrFail($transaction->membership_plan_id);
            $subscription = Subscription::where('user_id', $user->id)->lockForUpdate()->first();
            $isRenewal = $transaction->subscription_id !== null;
            if ($isRenewal) {
                if (! $subscription || $subscription->id !== $transaction->subscription_id
                    || $subscription->status !== 'active'
                    || $subscription->pending_transaction_id !== $transaction->id
                    || $subscription->membership_plan_id !== $plan->id
                    || $user->membership_plan_id !== $plan->id
                    || ! $plan->is_active
                    || $this->isLifetime($subscription->billing_period)
                    || $user->membership_expires_at === null) {
                    $this->invalid('Invoice perpanjangan tidak lagi berlaku. Hubungi admin jika sudah melakukan transfer.');
                }
            } elseif ($subscription) {
                // A separate checkout supersedes the old renewal, even when purchasing the same plan.
                $this->invalidatePendingInvoice($subscription);
            }

            $period = $isRenewal ? $subscription->billing_period : $plan->billing_period;
            $from = $user->hasActiveMembership() && $user->membership_expires_at
                ? $user->membership_expires_at->copy()
                : now()->startOfSecond();
            $until = $this->expiryFrom($from, $period);
            $previousPlan = $user->membership_plan_id;
            $previousExpiry = $user->membership_expires_at?->copy();

            $subscription ??= new Subscription(['user_id' => $user->id]);
            $subscription->fill([
                'membership_plan_id' => $plan->id,
                'billing_period' => $period,
                'status' => 'active',
                'cancelled_at' => null,
                'next_billing_at' => $until,
                'source_transaction_id' => $transaction->id,
                'pending_transaction_id' => null,
            ])->save();

            $grant = MembershipGrant::create([
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'membership_plan_id' => $plan->id,
                'previous_plan_id' => $previousPlan,
                'previous_expires_at' => $previousExpiry,
                'granted_from' => $from,
                'granted_until' => $until,
                // Per-user order, serialized by the user lock; UUID/timestamps are not ordering keys.
                'grant_order' => ((int) MembershipGrant::where('user_id', $user->id)->max('grant_order')) + 1,
            ]);
            $user->update(['membership_plan_id' => $plan->id, 'membership_expires_at' => $until]);

            Log::info('Membership activated from paid invoice', [
                'invoice' => $transaction->invoice_code, 'user_id' => $user->id,
                'subscription_id' => $subscription->id, 'grant_id' => $grant->id,
                'expires_at' => $until?->toDateTimeString(),
            ]);

            return $grant;
        }, 3);
    }

    /** Return the existing outstanding invoice, or create one when due. No access is granted. */
    public function invoiceRenewal(Subscription $subscription): ?Transaction
    {
        return DB::transaction(function () use ($subscription) {
            $user = User::whereKey($subscription->user_id)->lockForUpdate()->first();
            if (! $user) {
                return null;
            }
            $subscription = Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if ($subscription->status !== 'active') {
                return null;
            }
            $plan = MembershipPlan::findOrFail($subscription->membership_plan_id);
            if ($user->membership_plan_id !== $plan->id) {
                $this->invalidatePendingInvoice($subscription);
                $subscription->update(['status' => 'cancelled', 'cancelled_at' => now(), 'next_billing_at' => $user->membership_expires_at]);

                return null;
            }
            if (! $plan->is_active || $this->isLifetime($subscription->billing_period) || $user->membership_expires_at === null) {
                $this->invalidatePendingInvoice($subscription);
                $subscription->update(['next_billing_at' => $user->membership_expires_at]);

                return null;
            }

            // User membership expiry is authoritative, not an independently advanced billing clock.
            if (! $subscription->next_billing_at?->equalTo($user->membership_expires_at)) {
                $this->invalidatePendingInvoice($subscription);
                $subscription->update(['next_billing_at' => $user->membership_expires_at]);
            }
            if (! $subscription->isDueForRenewal()) {
                return null;
            }
            if ($subscription->pending_transaction_id) {
                $pending = Transaction::whereKey($subscription->pending_transaction_id)->lockForUpdate()->firstOrFail();
                if ($pending->payment_status === 'pending') {
                    return $pending;
                }
                if ($pending->isPaid()) {
                    $this->invalid('Invoice lunas belum disinkronkan. Hubungi admin.');
                }
                $subscription->update(['pending_transaction_id' => null]);
            }

            $invoice = Transaction::create([
                'invoice_code' => 'REN-'.Str::upper((string) Str::uuid()),
                'user_id' => $user->id,
                'membership_plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
                'amount' => $plan->price,
                'payment_method' => 'manual_transfer',
                'gateway_name' => 'manual',
                'payment_status' => 'pending',
                'transaction_date' => now(),
            ]);
            $subscription->update(['pending_transaction_id' => $invoice->id]);

            Log::info('Manual membership renewal invoice created', [
                'invoice' => $invoice->invoice_code, 'user_id' => $user->id,
                'subscription_id' => $subscription->id,
            ]);

            return $invoice;
        }, 3);
    }

    public function cancel(User $actor, Subscription $subscription): void
    {
        $this->changeRenewalStatus($actor, $subscription, false);
    }

    public function reactivate(User $actor, Subscription $subscription): void
    {
        $this->changeRenewalStatus($actor, $subscription, true);
    }

    private function changeRenewalStatus(User $actor, Subscription $subscription, bool $active): void
    {
        DB::transaction(function () use ($actor, $subscription, $active) {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $subscription = Subscription::where('user_id', $user->id)->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            if ($active && $subscription->status === 'active') {
                return;
            }
            if ($active) {
                $plan = MembershipPlan::findOrFail($subscription->membership_plan_id);
                if (! $plan->is_active || $user->membership_plan_id !== $plan->id
                    || $this->isLifetime($subscription->billing_period) || $user->membership_expires_at === null) {
                    $this->invalid('Langganan ini tidak dapat diaktifkan kembali. Silakan pilih membership melalui checkout.');
                }
            }
            $this->invalidatePendingInvoice($subscription);
            $subscription->update([
                'status' => $active ? 'active' : 'cancelled',
                'cancelled_at' => $active ? null : now(),
                'next_billing_at' => $user->membership_expires_at,
            ]);
            Log::info($active ? 'Membership renewal reactivated' : 'Membership renewal cancelled', [
                'user_id' => $user->id, 'subscription_id' => $subscription->id,
            ]);
        }, 3);
    }

    private function invalidatePendingInvoice(Subscription $subscription): void
    {
        if (! $subscription->pending_transaction_id) {
            return;
        }
        $invoice = Transaction::whereKey($subscription->pending_transaction_id)->lockForUpdate()->firstOrFail();
        if ($invoice->isPaid()) {
            $this->invalid('Invoice lunas belum disinkronkan. Hubungi admin.');
        }
        if ($invoice->payment_status === 'pending') {
            $invoice->update(['payment_status' => 'failed', 'expired_at' => now()]);
        }
        $subscription->update(['pending_transaction_id' => null]);
    }

    private function isLifetime(string $period): bool
    {
        return str_contains(strtolower($period), 'lifetime') || str_contains(strtolower($period), 'selamanya');
    }

    private function expiryFrom(Carbon $from, string $period): ?Carbon
    {
        $period = strtolower($period);
        if ($this->isLifetime($period)) {
            return null;
        }

        // Preserve the existing membership duration conventions, including legacy labels.
        return match (true) {
            str_contains($period, 'year'), str_contains($period, 'tahun') => $from->copy()->addYear(),
            str_contains($period, 'semiannual'), str_contains($period, 'semester') => $from->copy()->addMonths(6),
            str_contains($period, 'quarterly'), str_contains($period, 'triwulan') => $from->copy()->addMonths(3),
            str_contains($period, 'week'), str_contains($period, 'minggu') => $from->copy()->addWeek(),
            str_contains($period, 'day'), str_contains($period, 'hari') => $from->copy()->addDay(),
            default => $from->copy()->addMonth(),
        };
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['billing' => $message]);
    }
}
