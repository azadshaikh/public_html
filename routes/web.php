<?php

use App\Http\Controllers\Demo\MovieController;
use App\Http\Controllers\PluginController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('plugins', [PluginController::class, 'index'])->name('plugins.index');
    Route::patch('plugins', [PluginController::class, 'update'])->name('plugins.update');

    Route::prefix('demo')->name('demo.')->group(function () {
        Route::resource('movies', MovieController::class);
    });
});

require __DIR__.'/account.php';
