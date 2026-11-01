<?php

namespace App\Data;

use App\Models\Transaction;
use Carbon\CarbonImmutable;

/**
 * Immutable snapshot of a paid transaction.
 *
 * Listeners receive this instead of the Eloquent model so that fulfillment
 * logic never mutates the transaction and stays serializable for queues.
 */
final readonly class TransactionPaidData
{
    public function __construct(
        public string $transactionId,
        public string $userId,
        public string $invoiceCode,
        public float $amount,
        public string $gatewayName,
        public ?string $gatewayReference,
        public ?string $programId,
        public ?string $batchId,
        public ?string $membershipPlanId,
        public CarbonImmutable $paidAt,
        public array $gatewayPayload = [],
    ) {}

    public static function fromTransaction(Transaction $transaction): self
    {
        return new self(
            transactionId: $transaction->id,
            userId: $transaction->user_id,
            invoiceCode: $transaction->invoice_code,
            amount: (float) $transaction->amount,
            gatewayName: $transaction->gateway_name ?: 'manual',
            gatewayReference: $transaction->gateway_reference,
            programId: $transaction->program_id,
            batchId: $transaction->batch_id,
            membershipPlanId: $transaction->membership_plan_id,
            paidAt: CarbonImmutable::parse($transaction->paid_at ?? now()),
            gatewayPayload: $transaction->gateway_payload ?? [],
        );
    }

    /**
     * Human readable label used in e-mails and activity logs.
     */
    public function describeItem(): string
    {
        return match (true) {
            (bool) $this->membershipPlanId => 'Langganan Membership',
            (bool) $this->batchId => 'Pendaftaran Batch',
            (bool) $this->programId => 'Pembelian Program',
            default => 'Pembelian',
        };
    }
}
