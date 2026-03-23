<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\CouponController;
use Modules\Billing\Http\Controllers\CreditController;
use Modules\Billing\Http\Controllers\InvoiceController;
use Modules\Billing\Http\Controllers\PaymentController;
use Modules\Billing\Http\Controllers\RefundController;
use Modules\Billing\Http\Controllers\SettingsController;
use Modules\Billing\Http\Controllers\TaxController;
use Modules\Billing\Http\Controllers\TransactionController;

/*
|--------------------------------------------------------------------------
| Billing Module Web Routes
|--------------------------------------------------------------------------
|
| Routes for the Billing module. These routes are loaded by the
| RouteServiceProvider within a group which contains the "web" middleware.
|
*/

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::group(['prefix' => config('app.admin_slug').'/billing', 'as' => 'app.billing.'], function (): void {
        // Settings
        Route::group(['prefix' => 'settings', 'as' => 'settings.'], function (): void {
            Route::get('/', [SettingsController::class, 'settings'])->name('index');
            Route::post('/invoice-prefix', [SettingsController::class, 'updateInvoicePrefix'])->name('update-invoice-prefix');
            Route::post('/stripe', [SettingsController::class, 'updateStripe'])->name('update-stripe');
        });

        // Tax Rates CRUD
        Route::group(['prefix' => 'taxes', 'as' => 'taxes.'], function (): void {
            Route::post('/bulk-action', [TaxController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [TaxController::class, 'create'])->name('create');
            Route::post('/', [TaxController::class, 'store'])->name('store');

            Route::get('/{tax}', [TaxController::class, 'show'])
                ->name('show')
                ->where('tax', '[0-9]+');

            Route::get('/{tax}/edit', [TaxController::class, 'edit'])->name('edit');
            Route::put('/{tax}', [TaxController::class, 'update'])->name('update');
            Route::delete('/{tax}', [TaxController::class, 'destroy'])->name('destroy');
            Route::delete('/{tax}/force-delete', [TaxController::class, 'forceDelete'])->name('force-delete');
            Route::patch('/{tax}/restore', [TaxController::class, 'restore'])->name('restore');

            Route::get('/{status?}', [TaxController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|inactive|trash)$');
        });

        // Invoices CRUD
        Route::group(['prefix' => 'invoices', 'as' => 'invoices.'], function (): void {
            Route::post('/bulk-action', [InvoiceController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [InvoiceController::class, 'create'])->name('create');
            Route::post('/', [InvoiceController::class, 'store'])->name('store');

            Route::get('/{invoice}', [InvoiceController::class, 'show'])
                ->name('show')
                ->where('invoice', '[0-9]+');

            Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])->name('edit');
            Route::put('/{invoice}', [InvoiceController::class, 'update'])->name('update');
            Route::delete('/{invoice}', [InvoiceController::class, 'destroy'])->name('destroy');
            Route::delete('/{invoice}/force-delete', [InvoiceController::class, 'forceDelete'])->name('force-delete');
            Route::patch('/{invoice}/restore', [InvoiceController::class, 'restore'])->name('restore');

            Route::get('/{status?}', [InvoiceController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|draft|pending|sent|partial|paid|overdue|cancelled|refunded|trash)$');
        });

        // Payments CRUD
        Route::group(['prefix' => 'payments', 'as' => 'payments.'], function (): void {
            Route::post('/bulk-action', [PaymentController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [PaymentController::class, 'create'])->name('create');
            Route::post('/', [PaymentController::class, 'store'])->name('store');

            Route::get('/{payment}', [PaymentController::class, 'show'])
                ->name('show')
                ->where('payment', '[0-9]+');

            Route::get('/{payment}/edit', [PaymentController::class, 'edit'])->name('edit');
            Route::put('/{payment}', [PaymentController::class, 'update'])->name('update');
            Route::delete('/{payment}', [PaymentController::class, 'destroy'])->name('destroy');
            Route::delete('/{payment}/force-delete', [PaymentController::class, 'forceDelete'])->name('force-delete');
            Route::patch('/{payment}/restore', [PaymentController::class, 'restore'])->name('restore');

            Route::get('/{status?}', [PaymentController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|pending|processing|completed|failed|cancelled|refunded|trash)$');
        });

        // Credits CRUD
        Route::group(['prefix' => 'credits', 'as' => 'credits.'], function (): void {
            Route::post('/bulk-action', [CreditController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [CreditController::class, 'create'])->name('create');
            Route::post('/', [CreditController::class, 'store'])->name('store');

            Route::get('/{credit}', [CreditController::class, 'show'])
                ->name('show')
                ->where('credit', '[0-9]+');

            Route::get('/{credit}/edit', [CreditController::class, 'edit'])->name('edit');
            Route::put('/{credit}', [CreditController::class, 'update'])->name('update');
            Route::delete('/{credit}', [CreditController::class, 'destroy'])->name('destroy');
            Route::delete('/{credit}/force-delete', [CreditController::class, 'forceDelete'])->name('force-delete');
            Route::patch('/{credit}/restore', [CreditController::class, 'restore'])->name('restore');

            Route::get('/{status?}', [CreditController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|exhausted|expired|cancelled|trash)$');
        });

        // Refunds CRUD
        Route::group(['prefix' => 'refunds', 'as' => 'refunds.'], function (): void {
            Route::post('/bulk-action', [RefundController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [RefundController::class, 'create'])->name('create');
            Route::post('/', [RefundController::class, 'store'])->name('store');

            Route::get('/{refund}', [RefundController::class, 'show'])
                ->name('show')
                ->where('refund', '[0-9]+');

            Route::get('/{refund}/edit', [RefundController::class, 'edit'])->name('edit');
            Route::put('/{refund}', [RefundController::class, 'update'])->name('update');
            Route::delete('/{refund}', [RefundController::class, 'destroy'])->name('destroy');
            Route::delete('/{refund}/force-delete', [RefundController::class, 'forceDelete'])->name('force-delete');
            Route::patch('/{refund}/restore', [RefundController::class, 'restore'])->name('restore');

            Route::get('/{status?}', [RefundController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|pending|processing|completed|failed|cancelled|trash)$');
        });

        // Coupons CRUD
        Route::group(['prefix' => 'coupons', 'as' => 'coupons.'], function (): void {
            Route::post('/bulk-action', [CouponController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [CouponController::class, 'create'])->name('create');
            Route::post('/', [CouponController::class, 'store'])->name('store');

            Route::get('/{coupon}', [CouponController::class, 'show'])
                ->name('show')
                ->where('coupon', '[0-9]+');

            Route::get('/{coupon}/edit', [CouponController::class, 'edit'])->name('edit');
            Route::put('/{coupon}', [CouponController::class, 'update'])->name('update');
            Route::delete('/{coupon}', [CouponController::class, 'destroy'])->name('destroy');
            Route::delete('/{coupon}/force-delete', [CouponController::class, 'forceDelete'])->name('force-delete');
            Route::patch('/{coupon}/restore', [CouponController::class, 'restore'])->name('restore');

            Route::get('/{status?}', [CouponController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|inactive|expired|trash)$');
        });

        // Transactions (read-only)
        Route::group(['prefix' => 'transactions', 'as' => 'transactions.'], function (): void {
            Route::get('/{transaction}', [TransactionController::class, 'show'])
                ->name('show')
                ->where('transaction', '[0-9]+');

            Route::get('/{status?}', [TransactionController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|completed|pending|failed|cancelled)$');
        });
    });
});
