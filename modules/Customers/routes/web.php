<?php

use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\Controllers\CustomerContactController;
use Modules\Customers\Http\Controllers\CustomerController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::group(['prefix' => config('app.admin_slug').'/customers', 'as' => 'app.customers.'], function (): void {
        Route::post('/bulk-action', [CustomerController::class, 'bulkAction'])->name('bulk-action');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/', [CustomerController::class, 'store'])->name('store');

        Route::get('/{customer}', [CustomerController::class, 'show'])
            ->whereNumber('customer')
            ->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy');
        Route::delete('/{customer}/force-delete', [CustomerController::class, 'forceDelete'])->name('force-delete');
        Route::patch('/{customer}/restore', [CustomerController::class, 'restore'])->name('restore');

        Route::get('/{status?}', [CustomerController::class, 'index'])
            ->where('status', '^(all|active|inactive|trash)$')
            ->name('index');

        // Customer Contact routes
        Route::prefix('contacts')->name('contacts.')->group(function (): void {
            Route::post('/bulk-action', [CustomerContactController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [CustomerContactController::class, 'create'])->name('create');
            Route::post('/', [CustomerContactController::class, 'store'])->name('store');

            Route::get('/{customerContact}', [CustomerContactController::class, 'show'])
                ->whereNumber('customerContact')
                ->name('show');
            Route::get('/{customerContact}/edit', [CustomerContactController::class, 'edit'])->name('edit');
            Route::put('/{customerContact}', [CustomerContactController::class, 'update'])->name('update');
            Route::delete('/{customerContact}', [CustomerContactController::class, 'destroy'])->name('destroy');
            Route::delete('/{customerContact}/force-delete', [CustomerContactController::class, 'forceDelete'])->name('force-delete');
            Route::patch('/{customerContact}/restore', [CustomerContactController::class, 'restore'])->name('restore');

            Route::get('/{status?}', [CustomerContactController::class, 'index'])
                ->where('status', '^(all|active|inactive|trash)$')
                ->name('index');
        });
    });
});
