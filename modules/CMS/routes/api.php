<?php

use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\SeoSettingController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {
    Route::apiResource('seos', SeoSettingController::class)->names('seo');
});
