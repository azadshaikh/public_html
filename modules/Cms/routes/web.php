<?php

use Illuminate\Support\Facades\Route;
use Modules\Cms\Http\Controllers\CmsPageController;

Route::middleware(['auth', 'verified'])
    ->prefix('cms')
    ->name('cms.')
    ->group(function (): void {
        Route::get('/', [CmsPageController::class, 'index'])->name('index');
        Route::get('/create', [CmsPageController::class, 'create'])->name('create');
        Route::post('/', [CmsPageController::class, 'store'])->name('store');
        Route::get('/{cmsPage}/edit', [CmsPageController::class, 'edit'])->name('edit');
        Route::patch('/{cmsPage}', [CmsPageController::class, 'update'])->name('update');
        Route::delete('/{cmsPage}', [CmsPageController::class, 'destroy'])->name('destroy');
    });
