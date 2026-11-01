<?php

namespace App\Livewire\Operations;

use App\Models\Batch;
use App\Models\Payout;
use App\Models\User;
use App\Services\OperationsAccess;
use App\Services\PayoutService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PayoutsPage extends Component
{
    use WithPagination;

    public string $batchId = '';

    public string $instructorId = '';

    public string $amount = '';

    public string $honorNotes = '';

    public string $paymentPayoutId = '';

    public function mount(): void
    {
        app(OperationsAccess::class)->staff(auth()->user());
        $previousPayout = old('payment_payout_id');
        if (auth()->user()->hasRole('admin') && is_string($previousPayout) && Payout::whereKey($previousPayout)->exists()) {
            $this->paymentPayoutId = $previousPayout;
        }
    }

    public function setHonor(): void
    {
        app(OperationsAccess::class)->admin(auth()->user());
        $this->validate(['batchId' => 'required|uuid', 'instructorId' => 'required|uuid']);
        app(PayoutService::class)->setHonor(auth()->user(), Batch::findOrFail($this->batchId), User::findOrFail($this->instructorId), $this->amount, $this->honorNotes);
        $this->reset('amount', 'honorNotes', 'instructorId');
        session()->flash('success', 'Honor tetap berhasil disimpan.');
    }

    public function editHonor(string $id): void
    {
        app(OperationsAccess::class)->admin(auth()->user());
        $payout = Payout::findOrFail($id);
        $this->batchId = $payout->batch_id;
        $this->instructorId = $payout->instructor_user_id;
        $this->amount = $payout->amount;
        $this->honorNotes = $payout->notes ?? '';
    }

    public function selectPayment(string $id): void
    {
        app(OperationsAccess::class)->admin(auth()->user());
        Payout::findOrFail($id);
        $this->paymentPayoutId = $id;

        $this->resetValidation();
    }

    public function render()
    {
        $actor = auth()->user();
        $access = app(OperationsAccess::class);
        $access->staff($actor);

        return view('livewire.operations.payouts-page', [
            'isAdmin' => $actor->hasRole('admin'),
            'paymentPayout' => $actor->hasRole('admin') && $this->paymentPayoutId !== ''
                ? Payout::with(['batch', 'instructorUser'])->findOrFail($this->paymentPayoutId) : null,
            'payouts' => app(PayoutService::class)->visibleTo($actor)->with(['batch', 'instructorUser', 'paidBy', 'audits.actor'])->latest()->paginate(15),
            'batches' => $actor->hasRole('admin') ? Batch::orderBy('batch_name')->get() : collect(),
            'instructors' => $actor->hasRole('admin') ? User::role('instructor')->orderBy('name')->get() : collect(),
        ]);
    }
}
