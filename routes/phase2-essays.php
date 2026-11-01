<?php

use App\Livewire\Instructor\EssayGrading;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin|instructor'])->group(function () {
    Route::get('/grading/essays/{attempt?}', EssayGrading::class)->name('grading.essays');
});
