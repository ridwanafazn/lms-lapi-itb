<?php

namespace App\Listeners;

use App\Events\TransactionPaid;
use App\Mail\PaymentReceipt;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the payment receipt e-mail.
 *
 * Queued on purpose: a slow or down SMTP server must never roll back a
 * successful payment. Failures are retried and then reported through failed().
 */
class SendPaymentConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(TransactionPaid $event): void
    {
        $transaction = Transaction::with(['user', 'program', 'batch', 'membershipPlan'])
            ->find($event->data->transactionId);

        if (! $transaction || ! $transaction->user?->email) {
            return;
        }

        Mail::to($transaction->user->email)->send(new PaymentReceipt($transaction));
    }

    public function failed(TransactionPaid $event, \Throwable $exception): void
    {
        Log::error('Payment receipt e-mail failed', [
            'invoice' => $event->data->invoiceCode,
            'error' => $exception->getMessage(),
        ]);
    }
}
