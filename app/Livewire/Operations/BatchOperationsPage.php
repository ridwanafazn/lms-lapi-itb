<?php

namespace App\Livewire\Operations;

use App\Models\Batch;
use App\Models\BatchWaitlist;
use App\Services\BatchCloneService;
use App\Services\BatchWaitlistService;
use App\Services\OperationsAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class BatchOperationsPage extends Component
{
    use WithPagination;

    #[Url]
    public string $batchId = '';

    public string $cloneName = '';

    public function mount(): void
    {
        app(OperationsAccess::class)->admin(auth()->user());
    }

    public function updatedBatchId(): void
    {
        $this->resetPage();
        $this->resetValidation();
    }

    public function cloneBatch(): void
    {
        app(OperationsAccess::class)->admin(auth()->user());
        $this->validate(['batchId' => 'required|uuid']);
        $batch = app(BatchCloneService::class)->clone(auth()->user(), Batch::findOrFail($this->batchId), $this->cloneName);
        $this->batchId = $batch->id;
        $this->reset('cloneName');
        $this->resetPage();
        session()->flash('success', 'Batch disalin tanpa peserta, transaksi, progres, honor, dan absensi. Tinjau jadwal serta konfigurasi sebelum digunakan.');
    }

    public function promote(string $id): void
    {
        app(BatchWaitlistService::class)->promote(auth()->user(), BatchWaitlist::findOrFail($id));
        session()->flash('success', 'Peserta dipromosikan. Pembayaran dan enrollment tetap melalui alur normal.');
    }

    public function cancel(string $id): void
    {
        app(OperationsAccess::class)->admin(auth()->user());
        app(BatchWaitlistService::class)->cancel(auth()->user(), BatchWaitlist::findOrFail($id));
        session()->flash('success', 'Antrean atau reservasi dibatalkan. Enrollment yang sudah ada tidak diubah.');
    }

    public function render()
    {
        app(OperationsAccess::class)->admin(auth()->user());
        $batch = $this->batchId !== '' ? Batch::findOrFail($this->batchId) : null;

        return view('livewire.operations.batch-operations-page', [
            'batches' => Batch::orderBy('batch_name')->get(), 'batch' => $batch,
            'occupied' => $batch ? app(BatchWaitlistService::class)->occupiedSeats($batch) : 0,
            'entries' => BatchWaitlist::with('user')->where('batch_id', $this->batchId)->orderBy('queue_number')->paginate(20),
        ]);
    }
}
