<?php

use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('account', '/account/profile');

    Route::get('account/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('account/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('account/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('account/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('account/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('account/appearance', 'account/appearance')->name('appearance.edit');

    Route::get('account/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
