<?php

namespace App\Livewire\Billing;

use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\BillingProofService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Invoices extends Component
{
    use WithFileUploads, WithPagination;

    #[Locked]
    public ?string $invoiceId = null;

    public $proof;

    public function mount(?Transaction $invoice = null): void
    {
        $this->assertOwner();
        if ($invoice?->exists) {
            $this->invoiceId = $this->ownedInvoices()->findOrFail($invoice->id)->id;
        }
    }

    public function cancelSubscription(string $id): void
    {
        $this->assertOwner();
        $subscription = Subscription::where('user_id', Auth::id())->find($id);
        abort_unless($subscription, 404);
        app(SubscriptionService::class)->cancel(Auth::user(), $subscription);
        session()->flash('billingStatus', 'Perpanjangan dibatalkan. Akses yang sudah dibayar tetap berlaku sampai kedaluwarsa. Invoice perpanjangan lama tidak berlaku lagi.');
    }

    public function reactivateSubscription(string $id): void
    {
        $this->assertOwner();
        $subscription = Subscription::where('user_id', Auth::id())->find($id);
        abort_unless($subscription, 404);
        app(SubscriptionService::class)->reactivate(Auth::user(), $subscription);
        session()->flash('billingStatus', 'Perpanjangan diaktifkan kembali. Invoice baru akan dibuat saat jatuh tempo; akses hanya diperpanjang setelah pembayaran diverifikasi.');
    }

    public function uploadProof(): void
    {
        $this->assertOwner();
        abort_unless($this->invoiceId, 404);
        app(BillingProofService::class)->store($this->invoiceId, $this->proof);
        $this->reset('proof');
        session()->flash('billingStatus', 'Bukti tersimpan secara privat. Pembayaran menunggu verifikasi admin; akses belum diperpanjang.');
    }

    private function ownedInvoices()
    {
        return Transaction::where('user_id', Auth::id());
    }

    private function assertOwner(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasVerifiedEmail(), 403);
    }

    public function render()
    {
        $this->assertOwner();

        return view('livewire.billing.invoices', [
            'invoices' => $this->ownedInvoices()->with('membershipPlan')->latest()->orderBy('id')->paginate(10),
            'invoice' => $this->invoiceId ? $this->ownedInvoices()->with('membershipPlan')->findOrFail($this->invoiceId) : null,
            'subscription' => Subscription::where('user_id', Auth::id())->with('membershipPlan')->first(),
            'bank' => config('billing.manual_transfer', []),
        ]);
    }
}
