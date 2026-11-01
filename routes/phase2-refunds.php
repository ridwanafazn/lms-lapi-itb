<?php

use App\Livewire\Admin\Refunds;
use App\Models\Refund;
use App\Models\Transaction;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/refunds', Refunds::class)->name('refunds');
    Route::post('/refunds/{invoice}', function (Request $request, Transaction $invoice, RefundService $service) {
        $request->validate(['reason' => 'required|string|max:2000', 'proof' => 'required|file|max:10240']);
        $service->refund($request->user(), $invoice, $request->string('reason')->toString(), $request->file('proof'));

        return redirect()->route('admin.refunds')->with('success', 'Refund penuh tercatat. Akses dari transaksi ini telah dibatalkan tanpa menghapus sumber akses lain.');
    })->name('refunds.store');
    Route::get('/refund-evidence/{refund}', function (Refund $refund) {
        abort_unless(Storage::disk('local')->exists($refund->proof_path), 404);

        return Storage::disk('local')->download($refund->proof_path, 'bukti-refund-'.$refund->id.'.'.pathinfo($refund->proof_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    })->name('refunds.proof');
});
