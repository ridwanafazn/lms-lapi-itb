<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentPaymentSource;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    public function enroll(User $user, Program|Batch|Course $enrollable, ?string $batchId = null, ?Transaction $source = null): Enrollment
    {
        return DB::transaction(function () use ($user, $enrollable, $source) {
            if ($source) {
                $source = Transaction::whereKey($source->id)->lockForUpdate()->firstOrFail();
                if (! $source->isPaid()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'Transaksi sumber tidak lagi lunas.']);
                }
                $existingSource = EnrollmentPaymentSource::where('transaction_id', $source->id)
                    ->whereHas('enrollment', fn ($query) => $query->where('user_id', $user->id)
                        ->where('enrollable_type', $enrollable->getMorphClass())->where('enrollable_id', $enrollable->id))
                    ->lockForUpdate()->first();
                if ($existingSource) {
                    return $existingSource->enrollment;
                }
            }
            // Serialize grants even when the unique enrollment does not exist yet.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('enrollable_type', $enrollable->getMorphClass())
                ->where('enrollable_id', $enrollable->id)->lockForUpdate()->first();
            $hadAccess = $enrollment && in_array($enrollment->status, ['active', 'completed'], true)
                && (! $enrollment->access_expires_at || $enrollment->access_expires_at->isFuture());
            $untrackedAccess = $hadAccess && ! EnrollmentPaymentSource::where('enrollment_id', $enrollment->id)->lockForUpdate()->first();
            $previousStatus = $enrollment?->status;
            $previousExpiry = $enrollment?->access_expires_at;
            if (! $enrollment) {
                $enrollment = Enrollment::create([
                    'user_id' => $user->id, 'enrollable_type' => $enrollable->getMorphClass(),
                    'enrollable_id' => $enrollable->id, 'enrolled_at' => now(),
                    'status' => 'active', 'access_expires_at' => null, 'progress_percentage' => 0,
                ]);
            } elseif (! $hadAccess) {
                $enrollment->update(['status' => 'active', 'access_expires_at' => null]);
            }
            if ($source) {
                EnrollmentPaymentSource::firstOrCreate([
                    'transaction_id' => $source->id, 'enrollment_id' => $enrollment->id,
                ], [
                    'preserves_existing_access' => $untrackedAccess,
                    'previous_status' => $previousStatus,
                    'previous_access_expires_at' => $previousExpiry,
                ]);
            }

            return $enrollment;
        }, 3);
    }

    public function reverseEnrollment(Enrollment $enrollment): bool
    {
        return (bool) $enrollment->update(['status' => 'dropped', 'access_expires_at' => now()]);
    }

    public function deactivate(Enrollment $enrollment): bool
    {
        return $this->reverseEnrollment($enrollment);
    }
}
