<?php

namespace App\Events;

use App\Data\TransactionPaidData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once, at the exact moment a transaction becomes paid.
 *
 * Everything that must happen because of a payment (enrollment, membership,
 * notification, audit trail) is a listener of this event instead of being
 * hardcoded inside the Transaction model.
 */
class TransactionPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TransactionPaidData $data
    ) {}
}
