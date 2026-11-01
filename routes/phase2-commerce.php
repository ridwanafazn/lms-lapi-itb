<?php

use App\Livewire\Commerce\CorporateDashboard;
use App\Livewire\Commerce\CouponManager;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/corporate', CorporateDashboard::class)->name('corporate.dashboard');
    Route::get('/admin/coupons', CouponManager::class)->middleware('role:admin')->name('admin.coupons');
});
