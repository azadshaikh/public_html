<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\StripeWebhookController;

/*
|--------------------------------------------------------------------------
| Billing Module API Routes
|--------------------------------------------------------------------------
|
| RouteServiceProvider prefix: api
| Resolved base URL:           /api/billing/v1/...
|
| Stripe webhook endpoint: POST /api/billing/v1/webhooks/stripe
| No auth middleware — signature is verified inside the controller.
|
*/

Route::prefix('billing/v1')->group(function (): void {
    // Stripe webhook — no auth, signature verified inside the controller.
    // Register this URL in Stripe Dashboard → Webhooks.
    // Configure billing_stripe_webhook_secret in Billing → Settings → Stripe.
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
        ->name('billing.webhooks.stripe');
});
