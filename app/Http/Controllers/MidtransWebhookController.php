<?php

namespace App\Http\Controllers;

use App\Models\PaymentWebhookLog;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Notification;

class MidtransWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $rawPayload = $request->all();
        $signature = $request->header('x-signature-key');

        $webhookLog = PaymentWebhookLog::create([
            'id' => \Str::uuid(),
            'gateway' => 'midtrans',
            'signature' => $signature,
            'payload' => $rawPayload,
            'processing_status' => 'received',
        ]);

        try {
            $notification = app(Notification::class);
        } catch (\Exception $e) {
            $webhookLog->update([
                'processing_status' => 'invalid_signature',
                'processed_at' => now(),
            ]);

            Log::critical('Midtrans Webhook Invalid', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 403);
        }

        $orderId = $notification->order_id;
        $transactionStatus = $notification->transaction_status;
        $fraudStatus = $notification->fraud_status ?? null;

        $transaction = Transaction::where('invoice_code', $orderId)->first();

        if (! $transaction) {
            $webhookLog->update([
                'processing_status' => 'transaction_not_found',
                'processed_at' => now(),
            ]);

            return response()->json(['status' => 'not_found'], 404);
        }

        DB::beginTransaction();

        try {
            $transaction = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if (in_array($transactionStatus, ['capture', 'settlement'])) {

                if ($transactionStatus === 'capture' && $fraudStatus === 'challenge') {
                    // A nonterminal callback must never downgrade a settled/refunded invoice.
                } else {

                    $transaction->markAsPaid([
                        'transaction_id' => $notification->transaction_id ?? null,
                        'settlement_time' => $notification->settlement_time ?? now(),
                        'raw' => $rawPayload,
                    ]);

                    // Fungsi activateMembershipIfNeeded() dihapus dari sini
                    // karena pembaruan ditangani otomatis oleh Event/Listener
                    // saat markAsPaid() dipanggil.
                }

            } elseif (in_array($transactionStatus, ['expire', 'cancel', 'deny'])) {

                $transaction->markAsExpired();

            } elseif ($transactionStatus === 'pending') {

                // Invoices are created pending. Reordered callbacks cannot reopen failed or paid invoices.
            }

            $webhookLog->update([
                'processing_status' => 'processed',
                'processed_at' => now(),
            ]);

            DB::commit();

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {

            DB::rollBack();

            $webhookLog->update([
                'processing_status' => 'failed_processing',
                'processed_at' => now(),
            ]);

            Log::error('Webhook Processing Failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }
    }
}
