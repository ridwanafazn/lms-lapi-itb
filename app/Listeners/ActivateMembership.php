<?php

namespace App\Listeners;

use App\Events\TransactionPaid;
use App\Models\Transaction;
use App\Services\SubscriptionService;

class ActivateMembership
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function handle(TransactionPaid $event): void
    {
        if (! $event->data->membershipPlanId) {
            return;
        }

        $this->subscriptions->activateFromPaidTransaction(
            Transaction::findOrFail($event->data->transactionId)
        );
    }
}
