<?php

namespace App\Listeners;

use App\Events\TransactionPaid;
use Illuminate\Support\Facades\Log;

class LogDomainEvent
{
    public function handleTransactionPaid(TransactionPaid $event): void
    {
        Log::channel('domain')->info('Payment fulfilled', [
            'user_id' => $event->data->userId,
            'amount' => $event->data->amount,
            'enrollable_type' => $event->data->enrollableType ?? null,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
