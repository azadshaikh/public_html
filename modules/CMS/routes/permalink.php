<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\ThemeFrontendController;
use Spatie\MarkdownResponse\Middleware\ProvideMarkdownResponse;

/*
|--------------------------------------------------------------------------
| CMS Permalink Routes
|--------------------------------------------------------------------------
|
| These routes handle dynamic permalink structure for the CMS module.
| Uses a single wildcard parameter that matches any path depth.
| The controller parses segments via request()->segments().
|
*/

Route::middleware(['web', 'theme', 'site.access.protection', 'url.extension', 'cdnCacheHeaders', ProvideMarkdownResponse::class])->group(function (): void {
    // CMS Dynamic permalink route (loads last, lowest priority)
    // Single wildcard pattern - supports unlimited path depth
    Route::get('{path?}', [ThemeFrontendController::class, 'single'])
        ->where('path', '^(?!'.config('app.admin_slug').'|api/|storage/|vendor/|assets/|themes/|demo/|language/|login|register|password|logout|sanctum|_debugbar|_ignition|livewire).*')
        ->name('cms.view')
        ->withoutMiddleware([ValidateCsrfToken::class]);
});
