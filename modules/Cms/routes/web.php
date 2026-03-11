<?php

use Illuminate\Support\Facades\Route;
use Modules\Cms\Http\Controllers\CmsDashboardController;

Route::middleware(['auth', 'verified'])
    ->prefix('cms')
    ->name('cms.')
    ->group(function (): void {
        Route::get('/', CmsDashboardController::class)->name('index');
    });
