<?php

use Illuminate\Support\Facades\Route;
use Modules\Subscriptions\Http\Controllers\PlanController;
use Modules\Subscriptions\Http\Controllers\SubscriptionController;

/*
|--------------------------------------------------------------------------
| Subscriptions Module Routes
|--------------------------------------------------------------------------
|
| Routes for managing subscription plans and customer subscriptions.
|
*/

Route::middleware(['web', 'auth', 'user.status', 'verified', 'profile.completed', 'module_access:subscriptions'])
    ->prefix(config('app.admin_slug').'/subscriptions')
    ->name('subscriptions.')
    ->group(function (): void {
        // Plans CRUD routes
        Route::group(['prefix' => 'plans', 'as' => 'plans.'], function (): void {
            Route::get('/data', [PlanController::class, 'data'])->name('data');
            Route::post('/bulk-action', [PlanController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [PlanController::class, 'create'])->name('create');
            Route::post('/', [PlanController::class, 'store'])->name('store');

            Route::get('/{plan}', [PlanController::class, 'show'])
                ->name('show')
                ->where('plan', '[0-9]+');

            Route::get('/{plan}/edit', [PlanController::class, 'edit'])->name('edit');
            Route::put('/{plan}', [PlanController::class, 'update'])->name('update');
            Route::delete('/{plan}', [PlanController::class, 'destroy'])->name('destroy');
            Route::delete('/{plan}/force-delete', [PlanController::class, 'forceDelete'])->name('force-delete');
            Route::patch('/{plan}/restore', [PlanController::class, 'restore'])->name('restore');

            Route::get('/{status?}', [PlanController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|inactive|trash)$');
        });

        // Subscriptions CRUD routes
        Route::group(['prefix' => 'subscriptions', 'as' => 'subscriptions.'], function (): void {
            Route::get('/data', [SubscriptionController::class, 'data'])->name('data');
            Route::post('/bulk-action', [SubscriptionController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [SubscriptionController::class, 'create'])->name('create');
            Route::post('/', [SubscriptionController::class, 'store'])->name('store');

            Route::get('/{subscription}', [SubscriptionController::class, 'show'])
                ->name('show')
                ->where('subscription', '[0-9]+');

            Route::get('/{subscription}/edit', [SubscriptionController::class, 'edit'])->name('edit');
            Route::put('/{subscription}', [SubscriptionController::class, 'update'])->name('update');
            Route::delete('/{subscription}', [SubscriptionController::class, 'destroy'])->name('destroy');
            Route::delete('/{subscription}/force-delete', [SubscriptionController::class, 'forceDelete'])->name('force-delete');
            Route::patch('/{subscription}/restore', [SubscriptionController::class, 'restore'])->name('restore');

            Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
            Route::post('/{subscription}/resume', [SubscriptionController::class, 'resume'])->name('resume');
            Route::post('/{subscription}/pause', [SubscriptionController::class, 'pause'])->name('pause');

            Route::get('/{status?}', [SubscriptionController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|trialing|past_due|canceled|trash)$');
        });
    });
