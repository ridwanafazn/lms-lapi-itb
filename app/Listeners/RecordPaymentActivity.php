<?php

namespace App\Listeners;

use App\Events\TransactionPaid;
use App\Models\ActivityLog;
use App\Models\User;

/**
 * Writes an audit trail entry for every successful payment.
 *
 * Kept separate from the webhook log: PaymentWebhookLog stores raw gateway
 * payloads, ActivityLog stores "what the business did about it".
 */
class RecordPaymentActivity
{
    public function handle(TransactionPaid $event): void
    {
        $data = $event->data;

        ActivityLog::create([
            'actor_id' => $data->userId,
            'actor_name' => User::find($data->userId)?->name ?? 'system',
            'action' => 'payment_paid',
            'description' => sprintf(
                '%s lunas: %s (%s) sebesar Rp%s via %s.',
                $data->describeItem(),
                $data->invoiceCode,
                $data->gatewayReference ?: 'tanpa referensi gateway',
                number_format($data->amount, 0, ',', '.'),
                $data->gatewayName
            ),
            'ip_address' => request()->ip(),
        ]);
    }
}
