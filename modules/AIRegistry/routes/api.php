<?php

use Illuminate\Support\Facades\Route;
use Modules\AIRegistry\Http\Controllers\Api\V1\AIRegistryApiController;

Route::prefix('v1')->group(function (): void {
    Route::get('/providers', [AIRegistryApiController::class, 'providers'])->name('ai-registry.api.v1.providers.index');
    Route::get('/providers/{providerSlug}/models', [AIRegistryApiController::class, 'providerModels'])->name('ai-registry.api.v1.providers.models');
    Route::get('/models', [AIRegistryApiController::class, 'index'])->name('ai-registry.api.v1.models.index');
});
