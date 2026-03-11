<?php

use Illuminate\Support\Facades\Route;
use Modules\Todos\Http\Controllers\TodoTaskController;

Route::middleware(['auth', 'verified'])
    ->prefix('todos')
    ->name('todos.')
    ->group(function (): void {
        Route::get('/', [TodoTaskController::class, 'index'])->name('index');
        Route::get('/create', [TodoTaskController::class, 'create'])->name('create');
        Route::post('/', [TodoTaskController::class, 'store'])->name('store');
        Route::get('/{todoTask}/edit', [TodoTaskController::class, 'edit'])->name('edit');
        Route::patch('/{todoTask}', [TodoTaskController::class, 'update'])->name('update');
        Route::delete('/{todoTask}', [TodoTaskController::class, 'destroy'])->name('destroy');
    });
