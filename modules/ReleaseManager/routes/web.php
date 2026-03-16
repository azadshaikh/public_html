<?php

use Illuminate\Support\Facades\Route;
use Modules\ReleaseManager\Http\Controllers\ReleaseController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::group(['prefix' => config('app.admin_slug').'/releasemanager', 'as' => 'releasemanager.'], function (): void {
        $registerReleaseRoutes = function (string $prefix, string $name, string $type): void {
            Route::prefix($prefix)
                ->name($name.'.')
                ->middleware(['crud.exceptions'])
                ->group(function () use ($type): void {
                    Route::get('/next-version', [ReleaseController::class, 'getNextVersion'])->defaults('type', $type)->name('next-version');
                    Route::post('/bulk-action', [ReleaseController::class, 'bulkAction'])->defaults('type', $type)->name('bulk-action');

                    Route::get('/create', [ReleaseController::class, 'create'])->defaults('type', $type)->name('create');
                    Route::post('/', [ReleaseController::class, 'store'])->defaults('type', $type)->name('store');
                    Route::get('/{release}/edit', [ReleaseController::class, 'edit'])->defaults('type', $type)->whereNumber('release')->name('edit');
                    Route::put('/{release}', [ReleaseController::class, 'update'])->defaults('type', $type)->whereNumber('release')->name('update');
                    Route::delete('/{release}', [ReleaseController::class, 'destroy'])->defaults('type', $type)->whereNumber('release')->name('destroy');

                    Route::patch('/{release}/restore', [ReleaseController::class, 'restore'])->defaults('type', $type)->whereNumber('release')->name('restore');
                    Route::delete('/{release}/force-delete', [ReleaseController::class, 'forceDelete'])->defaults('type', $type)->whereNumber('release')->name('force-delete');
                    Route::get('/{release}', [ReleaseController::class, 'show'])->defaults('type', $type)->whereNumber('release')->name('show');

                    Route::get('/', [ReleaseController::class, 'index'])->defaults('type', $type)->name('index');
                });
        };

        $registerReleaseRoutes('application', 'application', 'application');
        $registerReleaseRoutes('module', 'module', 'module');

        Route::prefix('releases')->name('releases.')->middleware(['crud.exceptions'])->group(function (): void {
            Route::get('/', function () {
                return redirect()->to('/'.trim((string) config('app.admin_slug'), '/').'/releasemanager/application');
            })->name('index');

            Route::get('/create', function () {
                return redirect()->to('/'.trim((string) config('app.admin_slug'), '/').'/releasemanager/application/create');
            })->name('create');

            Route::get('/next-version', [ReleaseController::class, 'getNextVersion'])->name('next-version');
            Route::post('/bulk-action', [ReleaseController::class, 'bulkAction'])->name('bulk-action');
            Route::post('/', [ReleaseController::class, 'store'])->name('store');
            Route::get('/{release}', [ReleaseController::class, 'show'])->whereNumber('release')->name('show');
            Route::get('/{release}/edit', [ReleaseController::class, 'edit'])->whereNumber('release')->name('edit');
            Route::put('/{release}', [ReleaseController::class, 'update'])->whereNumber('release')->name('update');
            Route::delete('/{release}', [ReleaseController::class, 'destroy'])->whereNumber('release')->name('destroy');
            Route::patch('/{release}/restore', [ReleaseController::class, 'restore'])->whereNumber('release')->name('restore');
            Route::delete('/{release}/force-delete', [ReleaseController::class, 'forceDelete'])->whereNumber('release')->name('force-delete');
        });
    });
});
