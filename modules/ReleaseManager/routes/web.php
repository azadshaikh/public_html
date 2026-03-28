<?php

use Illuminate\Support\Facades\Route;
use Modules\ReleaseManager\Http\Controllers\ReleaseController;

/** @var string $releaseTypePattern */
$releaseTypePattern = collect(config('releasemanager.release_types', []))
    ->pluck('value')
    ->filter()
    ->map(fn (mixed $value): string => preg_quote((string) $value, '/'))
    ->implode('|');

/** @var string $statusPattern */
$statusPattern = collect(config('releasemanager.status_options', []))
    ->pluck('value')
    ->filter()
    ->map(fn (mixed $value): string => preg_quote((string) $value, '/'))
    ->prepend('trash')
    ->prepend('all')
    ->unique()
    ->implode('|');

$releaseTypePattern = $releaseTypePattern !== '' ? '^('.$releaseTypePattern.')$' : '^(application|module)$';
$statusPattern = $statusPattern !== '' ? '^('.$statusPattern.')$' : '^(all|draft|published|deprecate|trash)$';

Route::middleware(['auth', 'user.status', 'verified', 'profile.completed'])->group(function () use ($releaseTypePattern, $statusPattern): void {
    Route::group(['prefix' => config('app.admin_slug').'/releasemanager', 'as' => 'releasemanager.'], function () use ($releaseTypePattern, $statusPattern): void {
        Route::group([
            'prefix' => 'releases/{type}',
            'as' => 'releases.',
            'middleware' => ['crud.exceptions'],
            'where' => ['type' => $releaseTypePattern],
        ], function () use ($statusPattern): void {
            Route::get('/next-version', [ReleaseController::class, 'getNextVersion'])->name('next-version');
            Route::post('/bulk-action', [ReleaseController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [ReleaseController::class, 'create'])->name('create');
            Route::post('/', [ReleaseController::class, 'store'])->name('store');
            Route::get('/{release}/edit', [ReleaseController::class, 'edit'])->whereNumber('release')->name('edit');
            Route::put('/{release}', [ReleaseController::class, 'update'])->whereNumber('release')->name('update');
            Route::delete('/{release}', [ReleaseController::class, 'destroy'])->whereNumber('release')->name('destroy');
            Route::patch('/{release}/restore', [ReleaseController::class, 'restore'])->whereNumber('release')->name('restore');
            Route::delete('/{release}/force-delete', [ReleaseController::class, 'forceDelete'])->whereNumber('release')->name('force-delete');
            Route::get('/{release}', [ReleaseController::class, 'show'])->whereNumber('release')->name('show');

            Route::get('/{status?}', [ReleaseController::class, 'index'])
                ->where('status', $statusPattern)
                ->name('index');
        });
    });
});
