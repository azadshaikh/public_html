<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\Controllers\Api\V1\WebsiteApiController;
use Modules\Platform\Http\Middleware\AgencyApiKeyMiddleware;

/*
|--------------------------------------------------------------------------
| Platform API Routes
|--------------------------------------------------------------------------
|
| RouteServiceProvider prefix: api/platform
| Resolved base URL:           /api/platform/v1/...
|
| Example URLs:
|   GET    /api/platform/v1/websites
|   POST   /api/platform/v1/websites
|   GET    /api/platform/v1/websites/{siteId}
|   PATCH  /api/platform/v1/websites/{siteId}/status
|   DELETE /api/platform/v1/websites/{siteId}
|   POST   /api/platform/v1/websites/{siteId}/restore
|
| Agency Provisioning API — authenticated via X-Agency-Key header.
| All routes are scoped to the calling agency automatically.
|
*/

Route::prefix('v1')->middleware(AgencyApiKeyMiddleware::class)->group(function (): void {
    Route::get('websites', [WebsiteApiController::class, 'index'])->name('platform.api.v1.websites.index');
    Route::post('websites', [WebsiteApiController::class, 'store'])->name('platform.api.v1.websites.store');
    // Static before parameterised to avoid route shadowing
    Route::get('websites/domain-check', [WebsiteApiController::class, 'domainCheck'])->name('platform.api.v1.websites.domain-check');
    Route::get('websites/{siteId}', [WebsiteApiController::class, 'show'])->name('platform.api.v1.websites.show');
    Route::get('websites/{siteId}/provisioning', [WebsiteApiController::class, 'provisioning'])->name('platform.api.v1.websites.provisioning');
    Route::patch('websites/{siteId}/status', [WebsiteApiController::class, 'changeStatus'])->name('platform.api.v1.websites.change-status');
    Route::patch('websites/{siteId}/plan', [WebsiteApiController::class, 'updatePlan'])->name('platform.api.v1.websites.update-plan');
    Route::patch('websites/{siteId}/customer', [WebsiteApiController::class, 'updateCustomer'])->name('platform.api.v1.websites.update-customer');
    Route::delete('websites/{siteId}', [WebsiteApiController::class, 'destroy'])->name('platform.api.v1.websites.destroy');
    Route::delete('websites/{siteId}/force-delete', [WebsiteApiController::class, 'forceDelete'])->name('platform.api.v1.websites.force-delete');
    Route::post('websites/{siteId}/restore', [WebsiteApiController::class, 'restore'])->name('platform.api.v1.websites.restore');
    Route::post('websites/{siteId}/sync', [WebsiteApiController::class, 'sync'])->name('platform.api.v1.websites.sync');
    Route::post('websites/{siteId}/retry-provision', [WebsiteApiController::class, 'retryProvision'])->name('platform.api.v1.websites.retry-provision');
    Route::post('websites/{siteId}/confirm-dns', [WebsiteApiController::class, 'confirmDns'])->name('platform.api.v1.websites.confirm-dns');

    // DNS Record Management
    Route::get('websites/{siteId}/dns-records', [WebsiteApiController::class, 'dnsRecords'])->name('platform.api.v1.websites.dns-records.index');
    Route::post('websites/{siteId}/dns-records', [WebsiteApiController::class, 'addDnsRecord'])->name('platform.api.v1.websites.dns-records.store');
    Route::put('websites/{siteId}/dns-records/{recordId}', [WebsiteApiController::class, 'updateDnsRecord'])->name('platform.api.v1.websites.dns-records.update')->whereNumber('recordId');
    Route::delete('websites/{siteId}/dns-records/{recordId}', [WebsiteApiController::class, 'deleteDnsRecord'])->name('platform.api.v1.websites.dns-records.destroy')->whereNumber('recordId');

    // CDN Management
    Route::get('websites/{siteId}/cdn/status', [WebsiteApiController::class, 'getCdnStatus'])->name('platform.api.v1.websites.cdn.status');
    Route::post('websites/{siteId}/cdn/purge', [WebsiteApiController::class, 'purgeCdnCache'])->name('platform.api.v1.websites.cdn.purge');
});
