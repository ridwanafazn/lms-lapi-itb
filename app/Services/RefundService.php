<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CorporatePurchase;
use App\Models\Enrollment;
use App\Models\EnrollmentPaymentSource;
use App\Models\MembershipGrant;
use App\Models\MembershipPlan;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundService
{
    /** Records an already executed full manual refund; never calls a payment gateway. */
    public function refund(User $actor, Transaction $invoice, string $reason, UploadedFile $proof): Refund
    {
        if (! $actor->hasRole('admin')) {
            throw new AuthorizationException;
        }
        Validator::make(compact('reason', 'proof'), [
            'reason' => ['required', 'string', 'max:2000', 'not_regex:/^\s*$/'],
            'proof' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'mimetypes:application/pdf,image/png,image/jpeg', 'extensions:pdf,png,jpg,jpeg', 'max:10240'],
        ])->validate();
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $invoice, $reason, $proof, &$path) {
                $invoice = Transaction::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                $existing = Refund::where('transaction_id', $invoice->id)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
                if (! $invoice->isPaid() || (float) $invoice->amount <= 0) {
                    $this->invalid('Hanya transaksi berbayar yang sudah lunas dapat direfund penuh.');
                }
                $user = User::withTrashed()->whereKey($invoice->user_id)->lockForUpdate()->firstOrFail();
                if ($invoice->membership_plan_id) {
                    $this->reverseMembership($invoice, $user);
                } else {
                    $sources = EnrollmentPaymentSource::where('transaction_id', $invoice->id)->lockForUpdate()->get();
                    if ($sources->isEmpty() && ! $invoice->corporate_purchase_id) {
                        $this->invalid('Sumber akses transaksi legacy belum tercatat. Lakukan rekonsiliasi sebelum refund.');
                    }
                    foreach ($sources as $source) {
                        $enrollment = Enrollment::whereKey($source->enrollment_id)->lockForUpdate()->firstOrFail();
                        // Current locking reads, not a stale REPEATABLE READ snapshot after waiting.
                        $allSources = EnrollmentPaymentSource::where('enrollment_id', $enrollment->id)->lockForUpdate()->get();
                        $legacy = $allSources->first(fn ($entry) => $entry->preserves_existing_access
                            && in_array($entry->previous_status, ['active', 'completed'], true)
                            && (! $entry->previous_access_expires_at || $entry->previous_access_expires_at->isFuture()));
                        $anotherPayment = $allSources->contains(fn ($entry) => $entry->transaction_id !== $invoice->id && $entry->reversed_at === null);
                        if (! $anotherPayment) {
                            if ($legacy) {
                                $enrollment->update(['status' => $legacy->previous_status,
                                    'access_expires_at' => $legacy->previous_access_expires_at]);
                            } else {
                                app(EnrollmentService::class)->reverseEnrollment($enrollment);
                            }
                        }
                        $source->update(['reversed_at' => now()]);
                    }
                }
                if ($invoice->corporate_purchase_id) {
                    CorporatePurchase::whereKey($invoice->corporate_purchase_id)->lockForUpdate()->firstOrFail()
                        ->update(['status' => 'refunded']);
                }
                $path = $proof->store('refund-evidence/'.$invoice->id, 'local');
                if (! $path) {
                    $this->invalid('Bukti pengembalian gagal disimpan.');
                }
                $refund = Refund::create([
                    'transaction_id' => $invoice->id, 'processed_by' => $actor->id,
                    'amount' => $invoice->amount, 'reason' => trim($reason), 'proof_path' => $path,
                    'proof_sha256' => hash_file('sha256', $proof->getRealPath()), 'processed_at' => now(),
                ]);
                $invoice->update(['refunded_at' => now()]);
                ActivityLog::create([
                    'actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => 'transaction_refunded',
                    'description' => json_encode(['invoice' => $invoice->invoice_code, 'refund_id' => $refund->id,
                        'amount' => $refund->amount, 'reason' => $refund->reason, 'proof_sha256' => $refund->proof_sha256], JSON_THROW_ON_ERROR),
                ]);

                return $refund;
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    private function reverseMembership(Transaction $invoice, User $user): void
    {
        $latest = MembershipGrant::where('user_id', $user->id)->whereNull('reversed_at')
            ->orderByDesc('grant_order')->lockForUpdate()->first();
        if (! $latest || $latest->transaction_id !== $invoice->id || ! $latest->matchesCurrentMembership($user)) {
            $this->invalid('Refund membership hanya untuk pembelian terakhir dengan ledger yang sesuai. Rekonsiliasi diperlukan untuk transaksi lama atau perubahan manual.');
        }
        $subscription = Subscription::whereKey($latest->subscription_id)->lockForUpdate()->firstOrFail();
        if ($subscription->pending_transaction_id) {
            $pending = Transaction::whereKey($subscription->pending_transaction_id)->lockForUpdate()->firstOrFail();
            if ($pending->isPaid()) {
                $this->invalid('Invoice perpanjangan lunas belum disinkronkan. Rekonsiliasi diperlukan.');
            }
            $pending->markAsExpired();
        }
        $user->update(['membership_plan_id' => $latest->previous_plan_id, 'membership_expires_at' => $latest->previous_expires_at]);
        $latest->update(['reversed_at' => now()]);
        $previousGrant = MembershipGrant::where('user_id', $user->id)->whereNull('reversed_at')->orderByDesc('grant_order')->lockForUpdate()->first();
        $subscription->update([
            'membership_plan_id' => $latest->previous_plan_id ?? $subscription->membership_plan_id,
            'billing_period' => $latest->previous_plan_id
                ? MembershipPlan::findOrFail($latest->previous_plan_id)->billing_period : $subscription->billing_period,
            'status' => 'cancelled', 'cancelled_at' => now(), 'pending_transaction_id' => null,
            'next_billing_at' => $latest->previous_expires_at, 'source_transaction_id' => $previousGrant?->transaction_id,
        ]);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['refund' => $message]);
    }
}
