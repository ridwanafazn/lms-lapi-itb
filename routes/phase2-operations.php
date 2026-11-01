<?php

use App\Livewire\Operations\AttendancePage;
use App\Livewire\Operations\BatchOperationsPage;
use App\Livewire\Operations\PayoutsPage;
use App\Livewire\Operations\WaitlistPage;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/payouts', PayoutsPage::class)->name('payouts');
        // Direct multipart upload keeps financial evidence off the default media/temp disk.
        Route::post('/payouts/{payout}/payment', function (Request $request, Payout $payout, PayoutService $service) {
            $request->validate(['evidence' => ['required', 'file', 'max:10240']]);
            $service->recordPayment($request->user(), $payout,
                $request->only(['transfer_reference', 'paid_at', 'notes']), $request->file('evidence'));

            return redirect()->route('admin.payouts')->with('success', 'Pembayaran manual dan bukti transfer berhasil dicatat.');
        })->name('payouts.payment');
        Route::get('/batch-operations', BatchOperationsPage::class)->name('batch-operations');
        Route::get('/attendance', AttendancePage::class)->name('attendance');
    });
    Route::middleware('role:instructor')->prefix('instructor')->name('instructor.')->group(function () {
        Route::get('/payouts', PayoutsPage::class)->name('payouts');
        Route::get('/attendance', AttendancePage::class)->name('attendance');
    });
    Route::get('/waitlist', WaitlistPage::class)->middleware('role:student')->name('student.waitlist');
    Route::get('/payouts/{payout}/evidence', function (Payout $payout, PayoutService $service) {
        return $service->downloadEvidence(auth()->user(), $payout);
    })->middleware('role:admin|instructor')->name('payouts.evidence');
});
