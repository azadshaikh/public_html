<?php

use Illuminate\Support\Facades\Route;
use Modules\AIRegistry\Http\Controllers\AiModelController;
use Modules\AIRegistry\Http\Controllers\AiProviderController;
use Modules\AIRegistry\Http\Controllers\Api\V1\AIRegistryApiController;

Route::middleware(['auth', 'module_access:airegistry'])
    ->prefix(config('app.admin_slug'))
    ->group(function (): void {

        Route::get('/ai-registry/api/v1/models', [AIRegistryApiController::class, 'adminIndex'])
            ->name('ai-registry.admin-api.v1.models.index');

        // AI Providers
        Route::group(['prefix' => 'ai-registry/providers', 'as' => 'ai-registry.providers.'], function (): void {
            Route::post('/bulk-action', [AiProviderController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [AiProviderController::class, 'create'])->name('create');
            Route::post('/', [AiProviderController::class, 'store'])->name('store');

            Route::get('/{aiProvider}/edit', [AiProviderController::class, 'edit'])->name('edit')
                ->whereNumber('aiProvider');
            Route::put('/{aiProvider}', [AiProviderController::class, 'update'])->name('update')
                ->whereNumber('aiProvider');
            Route::delete('/{aiProvider}', [AiProviderController::class, 'destroy'])->name('destroy')
                ->whereNumber('aiProvider');
            Route::delete('/{aiProvider}/force-delete', [AiProviderController::class, 'forceDelete'])->name('force-delete')
                ->whereNumber('aiProvider');
            Route::patch('/{aiProvider}/restore', [AiProviderController::class, 'restore'])->name('restore')
                ->whereNumber('aiProvider');

            Route::get('/{status?}', [AiProviderController::class, 'index'])->name('index')
                ->where('status', '^(all|active|inactive|trash)$');
        });

        // AI Models
        Route::group(['prefix' => 'ai-registry/models', 'as' => 'ai-registry.models.'], function (): void {
            Route::post('/bulk-action', [AiModelController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [AiModelController::class, 'create'])->name('create');
            Route::post('/', [AiModelController::class, 'store'])->name('store');

            Route::get('/{aiModel}/edit', [AiModelController::class, 'edit'])->name('edit')
                ->whereNumber('aiModel');
            Route::put('/{aiModel}', [AiModelController::class, 'update'])->name('update')
                ->whereNumber('aiModel');
            Route::delete('/{aiModel}', [AiModelController::class, 'destroy'])->name('destroy')
                ->whereNumber('aiModel');
            Route::delete('/{aiModel}/force-delete', [AiModelController::class, 'forceDelete'])->name('force-delete')
                ->whereNumber('aiModel');
            Route::patch('/{aiModel}/restore', [AiModelController::class, 'restore'])->name('restore')
                ->whereNumber('aiModel');

            Route::get('/{status?}', [AiModelController::class, 'index'])->name('index')
                ->where('status', '^(all|active|inactive|trash)$');
        });
    });
