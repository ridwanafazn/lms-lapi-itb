<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\CorporatePurchase;
use App\Models\CorporateSeat;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CorporatePurchaseService
{
    public function __construct(private CheckoutService $checkout, private EnrollmentService $enrollments) {}

    public function create(User $buyer, Program|Batch $item, string $companyName, int $seatCount, string $method = 'manual_transfer'): CorporatePurchase
    {
        if (! $buyer->hasVerifiedEmail()) {
            throw new AuthorizationException;
        }
        Validator::make(['companyName' => trim($companyName), 'seatCount' => $seatCount, 'paymentMethod' => $method], [
            'companyName' => 'required|string|max:255', 'seatCount' => 'required|integer|min:1|max:10000',
            'paymentMethod' => 'required|in:manual_transfer,gateway_va',
        ])->validate();
        $purchase = DB::transaction(function () use ($buyer, $item, $companyName, $seatCount) {
            $item = $item->fresh();
            if (! $item) {
                throw ValidationException::withMessages(['checkout' => 'Produk tidak tersedia.']);
            }
            $this->checkout->assertAvailable($item);
            $price = $this->checkout->price($item);
            if ($price <= 0) {
                throw ValidationException::withMessages(['checkout' => 'Pembelian korporat memerlukan produk berbayar.']);
            }
            $purchase = CorporatePurchase::create([
                'company_name' => trim($companyName), 'buyer_user_id' => $buyer->id,
                'program_id' => $item instanceof Program ? $item->id : null,
                'batch_id' => $item instanceof Batch ? $item->id : null,
                'seat_count' => $seatCount, 'unit_amount' => $price,
                'total_amount' => round($price * $seatCount, 2), 'status' => 'pending',
            ]);
            // Persist the complete invoice before contacting an external gateway.
            $this->checkout->create($buyer, $item, method: 'manual_transfer', purchase: $purchase);

            return $purchase;
        }, 3);
        if ($method === 'gateway_va') {
            $transaction = $purchase->transaction;
            $transaction->update(['payment_method' => 'gateway_va', 'gateway_name' => 'midtrans']);
            $this->checkout->startGateway($transaction);
        }

        return $purchase->fresh(['transaction', 'seats.user']);
    }

    public function markPaid(Transaction $transaction): void
    {
        if (! $transaction->corporate_purchase_id) {
            return;
        }
        DB::transaction(function () use ($transaction) {
            $transaction = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $purchase = CorporatePurchase::whereKey($transaction->corporate_purchase_id)->lockForUpdate()->firstOrFail();
            if (! $transaction->isPaid() || $transaction->user_id !== $purchase->buyer_user_id
                || round((float) $transaction->amount, 2) !== round((float) $purchase->total_amount, 2)
                || $transaction->program_id !== $purchase->program_id || $transaction->batch_id !== $purchase->batch_id) {
                throw ValidationException::withMessages(['purchase' => 'Invoice korporat belum lunas atau tidak sesuai.']);
            }
            if ($purchase->status === 'pending') {
                if ($purchase->batch_id) {
                    $batch = Batch::whereKey($purchase->batch_id)->lockForUpdate()->firstOrFail();
                    $occupied = app(BatchWaitlistService::class)->occupiedSeats($batch);
                    if ($batch->quota <= 0 || $occupied + $purchase->seat_count > $batch->quota) {
                        throw ValidationException::withMessages(['purchase' => 'Kuota batch tidak cukup untuk seluruh kursi korporat.']);
                    }
                }
                $purchase->update(['status' => 'paid', 'paid_at' => $transaction->paid_at ?? now()]);
                Log::info('Corporate invoice paid', ['invoice' => $transaction->invoice_code, 'user_id' => $purchase->buyer_user_id, 'purchase_id' => $purchase->id]);
            }
        }, 3);
    }

    public function allocate(?User $actor, CorporatePurchase $purchase, array $userIds): int
    {
        if (! $actor || $actor->id !== $purchase->buyer_user_id || ! $actor->hasVerifiedEmail()) {
            throw new AuthorizationException;
        }
        Validator::make(['participants' => $userIds], [
            'participants' => 'required|array|min:1|max:10000', 'participants.*' => 'required|uuid|distinct',
        ])->validate();

        return DB::transaction(function () use ($actor, $purchase, $userIds) {
            // Refunds and allocations serialize on the source invoice before touching seats.
            $transaction = Transaction::where('corporate_purchase_id', $purchase->id)->lockForUpdate()->firstOrFail();
            $purchase = CorporatePurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($actor->id !== $purchase->buyer_user_id) {
                throw new AuthorizationException;
            }
            if (! in_array($purchase->status, ['paid', 'fulfilled'], true) || ! $transaction->isPaid()) {
                throw ValidationException::withMessages(['participants' => 'Kursi hanya dapat dialokasikan setelah invoice lunas.']);
            }
            $assigned = $purchase->seats()->lockForUpdate()->pluck('user_id');
            $newIds = collect($userIds)->diff($assigned)->sort()->values();
            if ($assigned->count() + $newIds->count() > $purchase->seat_count) {
                throw ValidationException::withMessages(['participants' => 'Jumlah peserta melebihi kursi yang telah dibayar.']);
            }
            $target = $purchase->batch ?? $purchase->program;
            if (! $target) {
                throw ValidationException::withMessages(['participants' => 'Program atau batch tidak tersedia.']);
            }
            foreach ($newIds as $id) {
                $student = User::whereKey($id)->lockForUpdate()->first();
                if (! $student || ! $student->is_active || ! $student->hasRole('student')) {
                    throw ValidationException::withMessages(['participants' => 'Semua peserta harus merupakan akun siswa yang masih aktif.']);
                }
                $fulfill = function () use ($student, $target, $purchase, $actor, $transaction) {
                    if (Enrollment::where('user_id', $student->id)->where('enrollable_type', $target->getMorphClass())
                        ->where('enrollable_id', $target->id)->whereIn('status', ['active', 'completed'])->lockForUpdate()->exists()) {
                        throw ValidationException::withMessages(['participants' => 'Peserta sudah terdaftar pada produk ini.']);
                    }
                    $enrollment = $this->enrollments->enroll($student, $target, $purchase->batch_id, $transaction);
                    CorporateSeat::create([
                        'corporate_purchase_id' => $purchase->id, 'user_id' => $student->id,
                        'allocated_by' => $actor->id, 'enrollment_id' => $enrollment->id, 'allocated_at' => now(),
                    ]);
                };
                if ($target instanceof Batch) {
                    app(BatchWaitlistService::class)->withSeat($target, $student, $fulfill, $purchase);
                } else {
                    $fulfill();
                }
            }
            if ($assigned->count() + $newIds->count() === $purchase->seat_count) {
                $purchase->update(['status' => 'fulfilled']);
            }
            Log::info('Corporate seats allocated', ['purchase_id' => $purchase->id, 'invoice' => $transaction->invoice_code, 'user_id' => $actor->id, 'allocated_count' => $newIds->count()]);

            return $newIds->count();
        }, 3);
    }
}
