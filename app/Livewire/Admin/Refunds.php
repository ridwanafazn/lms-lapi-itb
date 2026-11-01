<?php

namespace App\Livewire\Admin;

use App\Models\Refund;
use App\Models\Transaction;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Refunds extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        return view('livewire.admin.refunds', [
            'invoices' => Transaction::with('user')->whereIn('payment_status', ['paid', 'verified_by_admin'])
                ->whereNull('refunded_at')->where('amount', '>', 0)
                ->when($this->search, fn ($query) => $query->where('invoice_code', 'like', '%'.$this->search.'%'))
                ->latest()->paginate(10),
            'refunds' => Refund::with(['transaction.user', 'processor'])->latest()->limit(30)->get(),
        ]);
    }
}
