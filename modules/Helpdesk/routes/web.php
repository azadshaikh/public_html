<?php

use Illuminate\Support\Facades\Route;
use Modules\Helpdesk\Http\Controllers\DepartmentController;
use Modules\Helpdesk\Http\Controllers\SettingsController;
use Modules\Helpdesk\Http\Controllers\TicketController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::group(['prefix' => config('app.admin_slug').'/helpdesk', 'as' => 'helpdesk.'], function (): void {
        Route::prefix('departments')->name('departments.')->middleware(['crud.exceptions'])->group(function (): void {
            Route::post('/bulk-action', [DepartmentController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [DepartmentController::class, 'create'])->name('create');
            Route::post('/', [DepartmentController::class, 'store'])->name('store');

            Route::get('/{department}', [DepartmentController::class, 'show'])
                ->name('show')
                ->whereNumber('department');
            Route::get('/{department}/edit', [DepartmentController::class, 'edit'])
                ->whereNumber('department')
                ->name('edit');
            Route::put('/{department}', [DepartmentController::class, 'update'])
                ->whereNumber('department')
                ->name('update');
            Route::delete('/{department}', [DepartmentController::class, 'destroy'])
                ->whereNumber('department')
                ->name('destroy');
            Route::delete('/{department}/force-delete', [DepartmentController::class, 'forceDelete'])
                ->whereNumber('department')
                ->name('force-delete');
            Route::patch('/{department}/restore', [DepartmentController::class, 'restore'])
                ->whereNumber('department')
                ->name('restore');

            Route::get('/{status?}', [DepartmentController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|inactive|trash)$');
        });

        Route::group(['prefix' => 'settings', 'as' => 'settings.'], function (): void {
            Route::get('/', [SettingsController::class, 'settings'])->name('index');
            Route::post('/update', [SettingsController::class, 'updateSettings'])->name('update');
        });

        Route::prefix('tickets')->name('tickets.')->middleware(['crud.exceptions'])->group(function (): void {
            Route::post('/bulk-action', [TicketController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [TicketController::class, 'create'])->name('create');
            Route::post('/', [TicketController::class, 'store'])->name('store');

            Route::get('/{ticket}', [TicketController::class, 'show'])
                ->whereNumber('ticket')
                ->name('show');

            Route::get('/{ticket}/edit', [TicketController::class, 'edit'])
                ->whereNumber('ticket')
                ->name('edit');
            Route::put('/{ticket}', [TicketController::class, 'update'])
                ->whereNumber('ticket')
                ->name('update');
            Route::delete('/{ticket}', [TicketController::class, 'destroy'])
                ->whereNumber('ticket')
                ->name('destroy');
            Route::delete('/{ticket}/force-delete', [TicketController::class, 'forceDelete'])
                ->whereNumber('ticket')
                ->name('force-delete');
            Route::patch('/{ticket}/restore', [TicketController::class, 'restore'])
                ->whereNumber('ticket')
                ->name('restore');

            Route::post('/{ticket}/reply/store', [TicketController::class, 'storeReply'])
                ->whereNumber('ticket')
                ->name('reply.store');
            Route::delete('/{ticket}/reply/delete/{reply}', [TicketController::class, 'deleteReply'])
                ->whereNumber('ticket')
                ->whereNumber('reply')
                ->name('reply.delete');

            Route::get('/{status?}', [TicketController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|open|pending|resolved|on_hold|closed|cancelled|trash)$');
        });
    });
});
