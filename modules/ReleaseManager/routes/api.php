<?php

use Illuminate\Support\Facades\Route;
use Modules\ReleaseManager\Http\Controllers\Api\V1\ReleaseController;

/*
|--------------------------------------------------------------------------
| ReleaseManager API Routes
|--------------------------------------------------------------------------
|
| RouteServiceProvider prefix: api/release-manager
| Resolved base URL:           /api/release-manager/v1/...
|
| Example URLs:
|   GET /api/release-manager/v1/releases/latest-update/{type}/{packageIdentifier}
|
| Protected by X-Release-Key header via release.api.key middleware.
|
*/

Route::prefix('v1')->middleware('release.api.key')->group(function (): void {
    Route::get('releases/latest-update/{type}/{packageIdentifier}', [ReleaseController::class, 'latestUpdate'])->name('releasemanager.latest.update');
});
