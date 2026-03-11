<?php

use App\Http\Controllers\Demo\MovieController;
use App\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
    Route::patch('modules', [ModuleController::class, 'update'])->name('modules.update');

    Route::prefix('demo')->name('demo.')->group(function () {
        Route::resource('movies', MovieController::class);
    });
});

require __DIR__.'/account.php';
