<?php

use App\Http\Controllers\Demo\MovieController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('modules', [ModuleController::class, 'index'])
        ->middleware('permission:manage_modules')
        ->name('modules.index');
    Route::patch('modules', [ModuleController::class, 'update'])
        ->middleware('permission:manage_modules')
        ->name('modules.update');

    Route::prefix('roles')->name('roles.')->group(function (): void {
        Route::get('/', [RoleController::class, 'index'])
            ->middleware('permission:view_roles')
            ->name('index');
        Route::get('create', [RoleController::class, 'create'])
            ->middleware('permission:add_roles')
            ->name('create');
        Route::post('/', [RoleController::class, 'store'])
            ->middleware('permission:add_roles')
            ->name('store');
        Route::get('{role}/edit', [RoleController::class, 'edit'])
            ->middleware('permission:edit_roles')
            ->name('edit');
        Route::put('{role}', [RoleController::class, 'update'])
            ->middleware('permission:edit_roles')
            ->name('update');
        Route::delete('{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:delete_roles')
            ->name('destroy');
    });

    Route::prefix('demo')->name('demo.')->group(function (): void {
        Route::resource('movies', MovieController::class);
    });
});

require __DIR__.'/account.php';
