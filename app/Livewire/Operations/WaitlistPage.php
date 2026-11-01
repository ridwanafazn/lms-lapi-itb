<?php

namespace App\Livewire\Operations;

use App\Models\Batch;
use App\Models\BatchWaitlist;
use App\Services\BatchWaitlistService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class WaitlistPage extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('student'), 403);
    }

    public function join(string $batchId): void
    {
        app(BatchWaitlistService::class)->join(auth()->user(), Batch::findOrFail($batchId));
        session()->flash('success', 'Anda masuk antrean. Promosi dilakukan admin jika tersedia kursi.');
    }

    public function cancel(string $id): void
    {
        app(BatchWaitlistService::class)->cancel(auth()->user(), BatchWaitlist::findOrFail($id));
        session()->flash('success', 'Antrean atau reservasi Anda dibatalkan.');
    }

    public function render()
    {
        abort_unless(auth()->user()->hasRole('student'), 403);

        return view('livewire.operations.waitlist-page', [
            'batches' => Batch::where('status', 'open')->orderBy('batch_name')->paginate(15),
            'entries' => BatchWaitlist::with('batch')->where('user_id', auth()->id())->latest('joined_at')->get(),
        ]);
    }
}
