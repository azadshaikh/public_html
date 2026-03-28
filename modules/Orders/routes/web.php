<?php

use Illuminate\Support\Facades\Route;
use Modules\Orders\Http\Controllers\OrderController;
use Modules\Orders\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Orders Module Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth', 'user.status', 'verified', 'profile.completed', 'module_access:orders'])
    ->prefix(config('app.admin_slug').'/orders')
    ->as('app.orders.')
    ->group(function (): void {
        // Settings
        Route::group(['prefix' => 'settings', 'as' => 'settings.'], function (): void {
            Route::get('/', [SettingsController::class, 'settings'])->name('index');
            Route::post('/order-number', [SettingsController::class, 'updateOrderNumber'])->name('update-order-number');
        });

        // DataGrid endpoints (static routes — before parameterized)
        Route::get('/data', [OrderController::class, 'data'])->name('data');
        Route::post('/bulk-action', [OrderController::class, 'bulkAction'])->name('bulk-action');

        // Order detail (read-only)
        Route::get('/{order}', [OrderController::class, 'show'])
            ->name('show')
            ->where('order', '[0-9]+');

        // Soft-delete management
        Route::delete('/{order}', [OrderController::class, 'destroy'])
            ->name('destroy')
            ->where('order', '[0-9]+');

        Route::delete('/{order}/force-delete', [OrderController::class, 'forceDelete'])
            ->name('force-delete')
            ->where('order', '[0-9]+');

        Route::patch('/{order}/restore', [OrderController::class, 'restore'])
            ->name('restore')
            ->where('order', '[0-9]+');

        // Status filter catch-all (ALWAYS last)
        Route::get('/{status?}', [OrderController::class, 'index'])
            ->name('index')
            ->where('status', '^(all|pending|processing|active|cancelled|refunded|trash)$');
    });
