<?php

use Illuminate\Support\Facades\Route;
use Modules\Agency\Http\Controllers\BillingController;
use Modules\Agency\Http\Controllers\DomainController;
use Modules\Agency\Http\Controllers\InvoiceController;
use Modules\Agency\Http\Controllers\OnboardingController;
use Modules\Agency\Http\Controllers\SettingsController;
use Modules\Agency\Http\Controllers\SubscriptionController;
use Modules\Agency\Http\Controllers\TicketController;
use Modules\Agency\Http\Controllers\WebsiteController;
use Modules\Agency\Http\Controllers\WebsiteManageController;

/*
|--------------------------------------------------------------------------
| Agency Module Routes (Customer Portal)
|--------------------------------------------------------------------------
|
| These routes handle the customer-facing portal, onboarding wizard,
| and self-service pages for websites, subscriptions, invoices, and support.
|
*/

// Customer portal routes — all prefixed with admin slug
Route::prefix(config('app.admin_slug'))->group(function (): void {

    // Guest routes (onboarding / public auth pages)
    Route::middleware('guest')->group(function (): void {
        // Website builder landing + registration page
        Route::get('/get-started', [OnboardingController::class, 'getStarted'])->name('agency.get-started');
        Route::post('/get-started', [OnboardingController::class, 'storeGetStarted'])->name('agency.get-started.store');

        // Custom sign-in page (form posts to existing login.store)
        Route::get('/sign-in', [OnboardingController::class, 'signIn'])->name('agency.sign-in');
    });

    // Authenticated customer routes
    Route::middleware(['auth', 'user.status', 'role:super_user|administrator|customer'])->group(function (): void {
        // Onboarding wizard (post-signup flow) — email must be verified for all steps
        Route::get('/onboarding', [OnboardingController::class, 'wizard'])->name('agency.onboarding.wizard');
        Route::post('/onboarding', [OnboardingController::class, 'complete'])->name('agency.onboarding.complete');

        Route::middleware('verified')->group(function (): void {
            // Domain step (step 1 — must come before plan selection)
            Route::get('/onboarding/domain', [OnboardingController::class, 'domainStep'])->name('agency.onboarding.domain');
            Route::post('/onboarding/domain', [OnboardingController::class, 'storeDomain'])->name('agency.onboarding.domain.store');

            // Plan selection (step in onboarding)
            Route::get('/onboarding/plans', [OnboardingController::class, 'selectPlan'])->name('agency.onboarding.plans');
            Route::post('/onboarding/plans', [OnboardingController::class, 'storePlan'])->name('agency.onboarding.plans.store');

            // Create new website (resets session and starts fresh flow)
            Route::get('/websites/create', [OnboardingController::class, 'createWebsite'])->name('agency.websites.create');

            // Checkout/Payment (step in onboarding)
            Route::get('/onboarding/checkout', [OnboardingController::class, 'checkout'])->name('agency.onboarding.checkout');
            Route::post('/onboarding/checkout', [OnboardingController::class, 'processPayment'])->name('agency.onboarding.checkout.process');
            Route::get('/onboarding/checkout/stripe/success', [OnboardingController::class, 'stripeSuccess'])->name('agency.onboarding.checkout.stripe.success');
            Route::get('/onboarding/checkout/stripe/cancel', [OnboardingController::class, 'stripeCancel'])->name('agency.onboarding.checkout.stripe.cancel');
            Route::post('/onboarding/validate-coupon', [OnboardingController::class, 'validateCoupon'])->name('agency.onboarding.validate-coupon');
            Route::get('/onboarding/provisioning', [OnboardingController::class, 'provisioning'])->name('agency.onboarding.provisioning');
            Route::get('/onboarding/provisioning/status', [OnboardingController::class, 'provisioningStatus'])->name('agency.onboarding.provisioning.status');
            Route::get('/onboarding/provisioning/{website}', [OnboardingController::class, 'provisioning'])
                ->whereNumber('website')
                ->name('agency.onboarding.provisioning.website');
            Route::get('/onboarding/provisioning/{website}/status', [OnboardingController::class, 'provisioningStatus'])
                ->whereNumber('website')
                ->name('agency.onboarding.provisioning.website.status');
            Route::post('/onboarding/provisioning/{website}/confirm-dns', [OnboardingController::class, 'confirmDns'])
                ->whereNumber('website')
                ->name('agency.onboarding.provisioning.website.confirm-dns');
        });

        // My Websites
        Route::prefix('websites')->name('agency.websites.')->group(function (): void {
            Route::get('/', [WebsiteController::class, 'index'])->name('index');
            Route::get('/data', [WebsiteController::class, 'data'])->name('data');
            Route::get('/{id}', [WebsiteController::class, 'show'])->name('show')->whereNumber('id');
        });

        // Domains & DNS Management
        Route::prefix('domains')->name('agency.domains.')->group(function (): void {
            Route::get('/', [DomainController::class, 'index'])->name('index');
            Route::get('/{id}', [DomainController::class, 'show'])->name('show')->whereNumber('id');

            // DNS Record CRUD (AJAX)
            Route::post('/{id}/dns-records', [DomainController::class, 'storeDnsRecord'])->name('dns-records.store')->whereNumber('id');
            Route::put('/{id}/dns-records/{recordId}', [DomainController::class, 'updateDnsRecord'])->name('dns-records.update')->whereNumber(['id', 'recordId']);
            Route::delete('/{id}/dns-records/{recordId}', [DomainController::class, 'destroyDnsRecord'])->name('dns-records.destroy')->whereNumber(['id', 'recordId']);

            // CDN Management (AJAX)
            Route::get('/{id}/cdn/status', [DomainController::class, 'cdnStatus'])->name('cdn.status')->whereNumber('id');
            Route::post('/{id}/cdn/purge', [DomainController::class, 'purgeCdnCache'])->name('cdn.purge')->whereNumber('id');
        });

        // Billing Section
        Route::prefix('billing')->name('agency.billing.')->group(function (): void {
            // Billing Overview
            Route::get('/', [BillingController::class, 'index'])->name('index');

            // Subscriptions
            Route::prefix('subscriptions')->name('subscriptions.')->group(function (): void {
                Route::get('/', [SubscriptionController::class, 'index'])->name('index');
                Route::get('/{id}', [SubscriptionController::class, 'show'])->name('show')->whereNumber('id');
            });

            // Invoices & Payment History
            Route::prefix('invoices')->name('invoices.')->group(function (): void {
                Route::get('/', [InvoiceController::class, 'index'])->name('index');
                Route::get('/{id}', [InvoiceController::class, 'show'])->name('show')->whereNumber('id');
            });

            // Tax Details
            Route::get('/tax-details', [BillingController::class, 'taxDetails'])->name('tax-details');
            Route::post('/tax-details', [BillingController::class, 'updateTaxDetails'])->name('tax-details.update');
        });

        // Support Tickets
        Route::prefix('tickets')->name('agency.tickets.')->group(function (): void {
            Route::get('/', [TicketController::class, 'index'])->name('index');
            Route::get('/create', [TicketController::class, 'createTicket'])->name('create');
            Route::post('/', [TicketController::class, 'storeTicket'])->name('store');
            Route::get('/{id}', [TicketController::class, 'showTicket'])->name('show')->whereNumber('id');
            Route::post('/{id}/reply', [TicketController::class, 'reply'])->name('reply')->whereNumber('id');
        });
    });

}); // End customer portal prefix

// ──────────────────────────────────────────────────────────
// Admin Website Management (super_user / administrator only)
// ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'user.status', 'verified', 'profile.completed', 'role:super_user|administrator'])->group(function (): void {
    // Settings
    Route::prefix(config('app.admin_slug').'/agency/settings')->name('agency.admin.settings.')->group(function (): void {
        Route::get('/', [SettingsController::class, 'settings'])->name('index');
        Route::post('/general', [SettingsController::class, 'updateGeneral'])->name('update-general');
        Route::post('/platform', [SettingsController::class, 'updatePlatform'])->name('update-platform');
    });

    Route::prefix(config('app.admin_slug').'/agency/websites')->name('agency.admin.websites.')->group(function (): void {
        // DataGrid data endpoint
        Route::get('/data', [WebsiteManageController::class, 'data'])->name('data');

        // Bulk actions
        Route::post('/bulk-action', [WebsiteManageController::class, 'bulkAction'])->name('bulk-action');

        // Lifecycle actions (static routes before parameterized)
        Route::post('/{id}/suspend', [WebsiteManageController::class, 'suspend'])->name('suspend')->whereNumber('id');
        Route::post('/{id}/unsuspend', [WebsiteManageController::class, 'unsuspend'])->name('unsuspend')->whereNumber('id');
        Route::post('/{id}/sync', [WebsiteManageController::class, 'sync'])->name('sync')->whereNumber('id');
        Route::post('/{id}/retry-provision', [WebsiteManageController::class, 'retryProvision'])->name('retry-provision')->whereNumber('id');
        Route::patch('/{id}/restore', [WebsiteManageController::class, 'restoreWebsite'])->name('restore')->whereNumber('id');
        Route::delete('/{id}/force-delete', [WebsiteManageController::class, 'forceDeleteWebsite'])->name('force-delete')->whereNumber('id');

        // CRUD routes
        Route::get('/{id}/edit', [WebsiteManageController::class, 'edit'])->name('edit')->whereNumber('id');
        Route::put('/{id}', [WebsiteManageController::class, 'update'])->name('update')->whereNumber('id');
        Route::delete('/{id}', [WebsiteManageController::class, 'destroyWebsite'])->name('destroy')->whereNumber('id');
        Route::get('/{id}', [WebsiteManageController::class, 'showWebsite'])->name('show')->whereNumber('id');

        // Index with optional status tab (must be last — catch-all)
        Route::get('/{status?}', [WebsiteManageController::class, 'index'])
            ->name('index')
            ->where('status', '^(all|failed|suspended|expired|trash)$');
    });
});
