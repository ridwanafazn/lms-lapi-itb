<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BillingProofService
{
    public function store(string $invoiceId, mixed $proof): Transaction
    {
        // Both HTTP and Livewire callers must use the authenticated owner; admin has no upload override.
        $owner = Auth::user();
        abort_unless($owner && $owner->hasVerifiedEmail(), 403);
        $stored = null;
        try {
            return DB::transaction(function () use ($invoiceId, $proof, $owner, &$stored) {
                $invoice = Transaction::where('user_id', $owner->id)->whereKey($invoiceId)->lockForUpdate()->first();
                abort_unless($invoice, 404);
                if ($invoice->payment_status !== 'pending' || $invoice->payment_method !== 'manual_transfer'
                    || $invoice->refunded_at !== null) {
                    throw ValidationException::withMessages(['proof' => 'Bukti hanya dapat diunggah untuk invoice transfer manual yang masih pending dan belum direfund.']);
                }
                if ($invoice->subscription_id && ! Subscription::whereKey($invoice->subscription_id)
                    ->where('user_id', $owner->id)->where('status', 'active')
                    ->where('pending_transaction_id', $invoice->id)->exists()) {
                    throw ValidationException::withMessages(['proof' => 'Invoice perpanjangan ini tidak berlaku lagi.']);
                }
                $validated = Validator::make(['proof' => $proof], [
                    'proof' => 'required|file|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png|max:5120',
                ])->validate();
                // Explicit disk and visibility: never use the default (Google) disk or a public URL.
                $stored = $validated['proof']->store('billing/proofs/'.$owner->id, ['disk' => 'local', 'visibility' => 'private']);
                if (! $stored) {
                    throw ValidationException::withMessages(['proof' => 'Bukti gagal disimpan. Silakan coba lagi.']);
                }
                $invoice->update(['proof_file_url' => $stored]);

                return $invoice;
            });
        } catch (\Throwable $exception) {
            if ($stored) {
                Storage::disk('local')->delete($stored);
            }
            throw $exception;
        }
    }
}
