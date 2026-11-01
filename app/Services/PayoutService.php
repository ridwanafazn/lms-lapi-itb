<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PayoutService
{
    public function __construct(private OperationsAccess $access) {}

    public function visibleTo(User $actor): Builder
    {
        $this->access->staff($actor);

        return Payout::query()->when(! $actor->hasRole('admin'), fn (Builder $query) => $query->where('instructor_user_id', $actor->id));
    }

    public function setHonor(User $actor, Batch $batch, User $instructor, string $amount, ?string $notes = null): Payout
    {
        $this->access->admin($actor);
        Validator::make(compact('amount', 'notes'), [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $batch, $instructor, $amount, $notes) {
            $batch = Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $instructor = User::findOrFail($instructor->id);
            if (! $this->access->relatedInstructor($instructor, $batch)) {
                throw ValidationException::withMessages(['instructorId' => 'Instruktur harus terkait dengan batch atau kelasnya.']);
            }
            $payout = Payout::where('batch_id', $batch->id)->where('instructor_user_id', $instructor->id)->lockForUpdate()->first();
            if ($payout && $payout->status !== 'pending') {
                throw ValidationException::withMessages(['amount' => 'Honor yang sudah dibayar tidak dapat diubah.']);
            }
            $previous = $payout?->only(['amount', 'notes']);
            $payout ??= new Payout(['batch_id' => $batch->id, 'instructor_user_id' => $instructor->id, 'created_by' => $actor->id, 'status' => 'pending']);
            $payout->fill(['amount' => $amount, 'notes' => $notes])->save();
            $payout->audits()->create([
                'actor_id' => $actor->id, 'action' => $previous ? 'honor_updated' : 'honor_set',
                'details' => ['previous' => $previous, 'current' => $payout->only(['amount', 'notes'])],
            ]);

            return $payout;
        }, 3);
    }

    public function recordPayment(User $actor, Payout $payout, array $data, UploadedFile $evidence): Payout
    {
        $this->access->admin($actor);
        $validated = Validator::make(array_merge($data, ['evidence' => $evidence]), [
            'transfer_reference' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'extensions:pdf,jpg,jpeg,png', 'max:10240'],
        ])->validate();
        $path = null;
        try {
            // Do not retry a transaction that writes a file: a retry would orphan its first upload.
            return DB::transaction(function () use ($actor, $payout, $validated, $evidence, &$path) {
                $payout = Payout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
                if ($payout->status !== 'pending') {
                    throw ValidationException::withMessages(['payment' => 'Honor ini sudah dibayar.']);
                }
                $path = $evidence->store('payout-evidence/'.$payout->id, 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['evidence' => 'Bukti transfer gagal disimpan.']);
                }
                $payout->fill([
                    'status' => 'paid', 'paid_by' => $actor->id, 'paid_at' => $validated['paid_at'],
                    'transfer_reference' => trim($validated['transfer_reference']),
                    'evidence_path' => $path,
                    'evidence_name' => basename($evidence->getClientOriginalName()),
                    'evidence_sha256' => hash_file('sha256', $evidence->getRealPath()),
                ])->save();
                $payout->audits()->create([
                    'actor_id' => $actor->id, 'action' => 'payment_recorded',
                    'details' => array_merge($payout->only(['amount', 'paid_at', 'transfer_reference', 'evidence_sha256']), ['notes' => $validated['notes'] ?? null]),
                ]);

                return $payout;
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function downloadEvidence(User $actor, Payout $payout): StreamedResponse
    {
        $this->access->staff($actor);
        $payout = Payout::findOrFail($payout->id);
        if (! $actor->hasRole('admin') && $payout->instructor_user_id !== $actor->id) {
            throw new AuthorizationException;
        }
        abort_unless($payout->evidence_path && Storage::disk('local')->exists($payout->evidence_path), 404);

        return Storage::disk('local')->download($payout->evidence_path, 'bukti-transfer-'.$payout->id.'.'.pathinfo($payout->evidence_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
