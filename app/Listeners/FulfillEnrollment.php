<?php

namespace App\Listeners;

use App\Data\TransactionPaidData;
use App\Events\TransactionPaid;
use App\Models\Batch;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BatchWaitlistService;
use App\Services\CorporatePurchaseService;
use App\Services\CouponService;
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\Log;

/**
 * Grants learning access for the purchased Program and/or Batch.
 *
 * Runs synchronously inside the payment database transaction so a student
 * never receives a "pembayaran berhasil" page without the matching access.
 */
class FulfillEnrollment
{
    public function handle(TransactionPaid $event): void
    {
        $data = $event->data;
        $transaction = Transaction::findOrFail($data->transactionId);
        if (! $transaction->isPaid()) {
            return;
        }
        app(CouponService::class)->redeem($transaction);
        if ($transaction->corporate_purchase_id) {
            app(CorporatePurchaseService::class)->markPaid($transaction);

            return;
        }

        $fulfill = function () use ($data) {
            if ($data->programId) {
                $this->enroll($data, Program::class, $data->programId);
            }
            if ($data->batchId) {
                $this->enroll($data, Batch::class, $data->batchId);
            }
        };
        if ($data->batchId) {
            app(BatchWaitlistService::class)->withSeat(Batch::findOrFail($data->batchId), $transaction->user, $fulfill);
        } else {
            $fulfill();
        }
    }

    private function enroll(TransactionPaidData $data, string $enrollableType, string $enrollableId): void
    {
        $enrollment = app(EnrollmentService::class)->enroll(
            User::findOrFail($data->userId),
            $enrollableType::findOrFail($enrollableId),
            $data->batchId,
            Transaction::findOrFail($data->transactionId),
        );
        Log::info('Enrollment fulfilled after payment', [
            'enrollment_id' => $enrollment->id, 'user_id' => $data->userId, 'invoice' => $data->invoiceCode,
        ]);
    }
}
