<?php

use App\Livewire\Billing\Invoices;
use App\Models\Transaction;
use App\Services\BillingProofService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {
    Route::get('/', Invoices::class)->name('index');
    Route::get('/invoices/{invoice}', Invoices::class)->name('invoices.show');
    Route::post('/invoices/{invoice}/proof', function (Request $request, Transaction $invoice, BillingProofService $proofs) {
        $proofs->store($invoice->id, $request->file('proof'));

        return redirect()->route('billing.invoices.show', $invoice)->with(
            'billingStatus', 'Bukti tersimpan secara privat. Pembayaran menunggu verifikasi admin; akses belum diperpanjang.'
        );
    })->name('invoices.proof.store');
    Route::get('/invoices/{invoice}/proof', function (Transaction $invoice) {
        abort_unless($invoice->user_id === auth()->id() || auth()->user()->hasRole('admin'), 404);
        $path = $invoice->proof_file_url;
        // Only billing-owned local paths may be served; legacy remote/public URLs are never fetched.
        $prefix = 'billing/proofs/'.$invoice->user_id.'/';
        abort_unless(is_string($path) && str_starts_with($path, $prefix)
            && preg_match('/^[a-zA-Z0-9-]+\.(pdf|jpg|jpeg|png)$/', substr($path, strlen($prefix)))
            && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'bukti-transfer.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    })->name('invoices.proof');
});
