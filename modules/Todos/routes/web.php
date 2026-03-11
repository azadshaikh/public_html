<?php

use Illuminate\Support\Facades\Route;
use Modules\Todos\Http\Controllers\TodosDashboardController;

Route::middleware(['auth', 'verified'])
    ->prefix('todos')
    ->name('todos.')
    ->group(function (): void {
        Route::get('/', TodosDashboardController::class)->name('index');
    });
