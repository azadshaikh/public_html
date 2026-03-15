<?php

use Illuminate\Support\Facades\Route;
use Modules\Todos\Http\Controllers\TodoController;

Route::middleware(['auth', 'user.status', 'verified', 'profile.completed'])
    ->prefix(trim((string) config('app.admin_slug'), '/'))
    ->as('app.')
    ->group(function (): void {
        Route::prefix('todos')
            ->as('todos.')
            ->group(function (): void {
                // Bulk actions
                Route::post('/bulk-action', [TodoController::class, 'bulkAction'])->name('bulk-action');

                // Create routes
                Route::get('/create', [TodoController::class, 'create'])->name('create');
                Route::post('/', [TodoController::class, 'store'])->name('store');

                // Individual todo routes
                Route::get('/{todo}', [TodoController::class, 'show'])
                    ->whereNumber('todo')
                    ->name('show');
                Route::get('/{todo}/edit', [TodoController::class, 'edit'])->name('edit');
                Route::put('/{todo}', [TodoController::class, 'update'])->name('update');
                Route::delete('/{todo}', [TodoController::class, 'destroy'])->name('destroy');
                Route::delete('/{todo}/force-delete', [TodoController::class, 'forceDelete'])->name('force-delete');
                Route::patch('/{todo}/restore', [TodoController::class, 'restore'])->name('restore');

                // Index route with optional status filter (must remain last)
                Route::get('/{status?}', [TodoController::class, 'index'])
                    ->where('status', '^(all|pending|in_progress|completed|on_hold|cancelled|trash)$')
                    ->name('index');
            });
    });
