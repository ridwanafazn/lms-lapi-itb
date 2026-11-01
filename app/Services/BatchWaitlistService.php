<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\BatchWaitlist;
use App\Models\CorporatePurchase;
use App\Models\Enrollment;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchWaitlistService
{
    public function __construct(private OperationsAccess $access) {}

    public function join(User $actor, Batch $batch): BatchWaitlist
    {
        if (! $actor->hasRole('student')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $batch) {
            $batch = Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($batch);
            if ($this->enrollments($batch)->where('user_id', $actor->id)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['waitlist' => 'Anda sudah terdaftar pada batch ini.']);
            }
            $entry = BatchWaitlist::where('batch_id', $batch->id)->where('user_id', $actor->id)->lockForUpdate()->first();
            if ($entry && in_array($entry->status, ['waiting', 'promoted'], true)) {
                return $entry;
            }
            $number = ((int) BatchWaitlist::where('batch_id', $batch->id)
                ->orderByDesc('queue_number')->lockForUpdate()->value('queue_number')) + 1;
            $entry ??= new BatchWaitlist(['batch_id' => $batch->id, 'user_id' => $actor->id]);
            $entry->fill([
                'queue_number' => $number, 'status' => 'waiting', 'joined_at' => now(),
                'promoted_at' => null, 'promoted_by' => null, 'cancelled_at' => null,
            ])->save();
            $this->audit($actor, 'waitlist_joined', $entry);

            return $entry;
        }, 3);
    }

    public function promote(User $actor, BatchWaitlist $entry): BatchWaitlist
    {
        $this->access->admin($actor);

        return DB::transaction(function () use ($actor, $entry) {
            $batch = Batch::whereKey($entry->batch_id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($batch);
            $entry = BatchWaitlist::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            $head = BatchWaitlist::where('batch_id', $batch->id)->where('status', 'waiting')
                ->orderBy('queue_number')->lockForUpdate()->first();
            if ($entry->status !== 'waiting' || $head?->id !== $entry->id) {
                throw ValidationException::withMessages(['waitlist' => 'Promosi harus mengikuti urutan antrean FIFO.']);
            }
            if (! $entry->user) {
                throw ValidationException::withMessages(['waitlist' => 'Peserta tidak aktif; batalkan antrean ini terlebih dahulu.']);
            }
            if ($this->enrollments($batch)->where('user_id', $entry->user_id)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['waitlist' => 'Peserta sudah terdaftar; batalkan antrean ini terlebih dahulu.']);
            }
            $this->assertCapacity($batch);
            $entry->update(['status' => 'promoted', 'promoted_at' => now(), 'promoted_by' => $actor->id]);
            $this->audit($actor, 'waitlist_promoted', $entry);

            // A reservation is not an enrollment or a payment exemption.
            return $entry;
        }, 3);
    }

    public function cancel(User $actor, BatchWaitlist $entry): void
    {
        if (! $actor->hasRole('admin') && $entry->user_id !== $actor->id) {
            throw new AuthorizationException;
        }
        DB::transaction(function () use ($actor, $entry) {
            Batch::whereKey($entry->batch_id)->lockForUpdate()->firstOrFail();
            $entry = BatchWaitlist::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if (! $actor->hasRole('admin') && $entry->user_id !== $actor->id) {
                throw new AuthorizationException;
            }
            if (in_array($entry->status, ['waiting', 'promoted'], true)) {
                $entry->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                $this->audit($actor, 'waitlist_cancelled', $entry);
            }
        }, 3);
    }

    public function occupiedSeats(Batch $batch, ?string $excludingReservationUser = null): int
    {
        // Locking reads avoid stale MySQL REPEATABLE READ snapshots in payment transactions.
        $locking = DB::transactionLevel() > 0;
        $enrolledIds = $this->enrollments($batch)
            ->when($locking, fn (Builder $query) => $query->lockForUpdate())->pluck('user_id');
        $reservedIds = BatchWaitlist::where('batch_id', $batch->id)->where('status', 'promoted')
            ->when($excludingReservationUser, fn (Builder $query) => $query->where('user_id', '!=', $excludingReservationUser))
            ->when($locking, fn (Builder $query) => $query->lockForUpdate())->pluck('user_id');

        $corporateReservations = CorporatePurchase::where('batch_id', $batch->id)
            ->whereIn('status', ['paid', 'fulfilled'])
            ->whereHas('transaction', fn (Builder $query) => $query->whereNull('refunded_at')->whereIn('payment_status', ['paid', 'verified_by_admin']))
            ->withCount('seats')->when($locking, fn (Builder $query) => $query->lockForUpdate())->get()
            ->sum(fn (CorporatePurchase $purchase) => max(0, $purchase->seat_count - $purchase->seats_count));

        return $enrolledIds->merge($reservedIds)->unique()->count() + $corporateReservations;
    }

    /** Run normal payment/enrollment fulfillment under the same lock as promotions. */
    public function withSeat(Batch $batch, User $student, Closure $fulfill, ?CorporatePurchase $purchase = null): mixed
    {
        return DB::transaction(function () use ($batch, $student, $fulfill, $purchase) {
            $batch = Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if (! $this->enrollments($batch)->where('user_id', $student->id)->lockForUpdate()->first()) {
                if ($purchase) {
                    $reserved = CorporatePurchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
                    if ($reserved->batch_id !== $batch->id || ! in_array($reserved->status, ['paid', 'fulfilled'], true)
                        || ! $reserved->transaction?->isPaid() || $reserved->seats()->count() >= $reserved->seat_count) {
                        throw ValidationException::withMessages(['participants' => 'Reservasi korporat tidak berlaku.']);
                    }
                    // Convert an already-counted corporate reservation, rather than adding a seat.
                    if ($batch->quota <= 0 || $this->occupiedSeats($batch, $student->id) > $batch->quota) {
                        throw ValidationException::withMessages(['participants' => 'Kuota batch tidak mencukupi.']);
                    }
                } else {
                    $this->assertCapacity($batch, $student->id);
                }
            }

            $result = $fulfill();
            BatchWaitlist::where('batch_id', $batch->id)->where('user_id', $student->id)
                ->whereIn('status', ['waiting', 'promoted'])->update(['status' => 'enrolled']);

            return $result;
        });
    }

    private function enrollments(Batch $batch): Builder
    {
        return Enrollment::where('enrollable_type', $batch->getMorphClass())
            ->where('enrollable_id', $batch->id)->whereIn('status', ['active', 'completed']);
    }

    private function assertOpen(Batch $batch): void
    {
        if ($batch->status !== 'open') {
            throw ValidationException::withMessages(['waitlist' => 'Antrean hanya tersedia untuk batch berstatus terbuka.']);
        }
    }

    private function assertCapacity(Batch $batch, ?string $studentId = null): void
    {
        if ($batch->quota <= 0 || $this->occupiedSeats($batch, $studentId) >= $batch->quota) {
            throw ValidationException::withMessages(['waitlist' => 'Kuota batch penuh atau belum ditetapkan.']);
        }
    }

    private function audit(User $actor, string $action, BatchWaitlist $entry): void
    {
        ActivityLog::create([
            'actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action,
            'description' => json_encode($entry->only(['id', 'batch_id', 'user_id', 'queue_number', 'status']), JSON_THROW_ON_ERROR),
        ]);
    }
}
