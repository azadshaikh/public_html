<?php

use Illuminate\Support\Facades\Route;
use Modules\Agency\Http\Controllers\Api\WebhookController;

/*
|--------------------------------------------------------------------------
| Agency Module API Routes
|--------------------------------------------------------------------------
|
| RouteServiceProvider prefix: api/agency
| Resolved base URL:           /api/agency/v1/...
|
| Example URLs:
|   POST /api/agency/v1/webhooks/platform
|
| Webhook endpoint for receiving events from the Platform (console) instance.
| No auth middleware — signature is verified inside the controller.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::post('/webhooks/platform', [WebhookController::class, 'handle'])
        ->name('agency.webhooks.platform');
});
