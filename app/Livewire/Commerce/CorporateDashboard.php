<?php

namespace App\Livewire\Commerce;

use App\Models\Batch;
use App\Models\CorporatePurchase;
use App\Models\Program;
use App\Models\User;
use App\Services\CorporatePurchaseService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CorporateDashboard extends Component
{
    use WithPagination;

    public string $companyName = '';

    public string $product = '';

    public int $seatCount = 1;

    public string $paymentMethod = 'manual_transfer';

    public string $purchaseId = '';

    public string $participantEmail = '';

    public function createPurchase(): void
    {
        $this->assertBuyer();
        $this->validate([
            'companyName' => 'required|string|max:255', 'product' => 'required|string',
            'seatCount' => 'required|integer|min:1|max:10000', 'paymentMethod' => 'required|in:manual_transfer,gateway_va',
        ]);
        [$type, $id] = array_pad(explode(':', $this->product, 2), 2, '');
        abort_unless(in_array($type, ['program', 'batch'], true), 422);
        $item = $type === 'program' ? Program::findOrFail($id) : Batch::findOrFail($id);
        $purchase = app(CorporatePurchaseService::class)->create(Auth::user(), $item, $this->companyName, $this->seatCount, $this->paymentMethod);
        $this->purchaseId = $purchase->id;
        if ($purchase->transaction->snap_token) {
            $this->dispatch('commerce-payment', token: $purchase->transaction->snap_token);
        }
        session()->flash('commerceStatus', 'Invoice seluruh kursi dibuat. Alokasi peserta tersedia setelah pembayaran diverifikasi.');
        $this->resetPage();
    }

    public function allocate(): void
    {
        $this->assertBuyer();
        $this->validate(['purchaseId' => 'required|uuid', 'participantEmail' => 'required|email|max:255']);
        $purchase = CorporatePurchase::where('buyer_user_id', Auth::id())->find($this->purchaseId);
        abort_unless($purchase, 404);
        $student = User::where('email', trim($this->participantEmail))->first();
        if (! $student || ! $student->is_active || ! $student->hasRole('student')) {
            $this->addError('participantEmail', 'Akun siswa tidak ditemukan.');

            return;
        }
        app(CorporatePurchaseService::class)->allocate(Auth::user(), $purchase, [$student->id]);
        $this->reset('participantEmail');
        session()->flash('commerceStatus', 'Peserta dialokasikan. Peserta yang telah dialokasikan tidak dapat diganti.');
    }

    private function assertBuyer(): void
    {
        abort_unless(Auth::check() && Auth::user()->hasVerifiedEmail(), 403);
    }

    public function render()
    {
        $this->assertBuyer();

        return view('livewire.commerce.corporate-dashboard', [
            'purchases' => CorporatePurchase::where('buyer_user_id', Auth::id())
                ->with(['transaction', 'program', 'batch', 'seats.user'])->latest()->paginate(10),
            'programs' => Program::where('is_active', true)->where('type', 'self_paced')->where('price', '>', 0)->orderBy('title')->get(),
            'batches' => Batch::where('status', 'open')->with('program')->get()->filter(fn ($batch) => $batch->effective_price > 0),
        ]);
    }
}
