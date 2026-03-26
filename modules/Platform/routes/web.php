<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\Http\Controllers\AgencyController;
use Modules\Platform\Http\Controllers\AgencyProviderController;
use Modules\Platform\Http\Controllers\AgencyServerController;
use Modules\Platform\Http\Controllers\DomainController;
use Modules\Platform\Http\Controllers\DomainDnsController;
use Modules\Platform\Http\Controllers\DomainSslCertificateController;
use Modules\Platform\Http\Controllers\ProviderController;
use Modules\Platform\Http\Controllers\SecretController;
use Modules\Platform\Http\Controllers\ServerAgencyController;
use Modules\Platform\Http\Controllers\ServerController;
use Modules\Platform\Http\Controllers\SettingsController;
use Modules\Platform\Http\Controllers\TldController;
use Modules\Platform\Http\Controllers\WebsiteController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::group(['prefix' => config('app.admin_slug').'/platform', 'as' => 'platform.'], function (): void {
        // ---------------------------------------------------------------------
        // Servers (Scaffold)
        // ---------------------------------------------------------------------
        Route::prefix('servers')->name('servers.')->middleware(['crud.exceptions'])->group(function (): void {
            // Static routes first
            Route::get('/data', [ServerController::class, 'data'])->name('data');
            Route::post('/bulk-action', [ServerController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [ServerController::class, 'createWizard'])->name('create');
            Route::post('/', [ServerController::class, 'store'])->name('store');

            // Wizard API routes
            Route::post('/generate-ssh-key', [ServerController::class, 'generateSSHKey'])->name('generate-ssh-key');
            Route::post('/verify-connection', [ServerController::class, 'verifyConnection'])
                ->middleware('throttle:10,1')
                ->name('verify-connection');

            // Server-Agency association routes (static paths first, then parameterized)
            Route::get('/{server}/agencies', [ServerAgencyController::class, 'getAgencies'])
                ->middleware('can:view_servers')
                ->whereNumber('server')
                ->name('agencies.index');
            Route::post('/{server}/agencies', [ServerAgencyController::class, 'attachAgencies'])
                ->middleware('can:edit_servers')
                ->whereNumber('server')
                ->name('agencies.attach');
            Route::delete('/{server}/agencies/{agency}', [ServerAgencyController::class, 'detachAgency'])
                ->middleware('can:edit_servers')
                ->whereNumber('server')
                ->whereNumber('agency')
                ->name('agencies.detach');
            Route::post('/{server}/agencies/{agency}/set-primary', [ServerAgencyController::class, 'setPrimaryAgency'])
                ->middleware('can:edit_servers')
                ->whereNumber('server')
                ->whereNumber('agency')
                ->name('agencies.set-primary');
            Route::get('/{server}/available-agencies', [ServerAgencyController::class, 'getAvailableAgencies'])
                ->middleware('can:view_servers')
                ->whereNumber('server')
                ->name('available-agencies');

            // Custom endpoints
            Route::post('/{server}/update-releases', [ServerController::class, 'updateReleases'])->name('update-releases')->whereNumber('server');
            Route::post('/{server}/sync-server', [ServerController::class, 'syncServer'])->name('sync-server')->whereNumber('server');
            Route::get('/{server}/websites', [ServerController::class, 'websites'])->name('websites')->whereNumber('server');

            // Provisioning and management routes
            Route::post('/{server}/provision', [ServerController::class, 'provision'])->name('provision')->whereNumber('server');
            Route::post('/{server}/update-scripts', [ServerController::class, 'updateScripts'])->name('update-scripts')->whereNumber('server');
            Route::post('/{server}/test-connection', [ServerController::class, 'testConnection'])
                ->middleware('throttle:10,1')
                ->name('test-connection')
                ->whereNumber('server');
            Route::get('/{server}/script-log', [ServerController::class, 'scriptLog'])
                ->name('script-log.show')
                ->whereNumber('server');
            Route::delete('/{server}/script-log', [ServerController::class, 'clearScriptLog'])
                ->name('script-log.clear')
                ->whereNumber('server');
            Route::post('/{server}/retry-provisioning', [ServerController::class, 'retryProvisioning'])->name('retry-provisioning')->whereNumber('server');
            Route::post('/{server}/reprovision', [ServerController::class, 'reprovisionServer'])->name('reprovision')->whereNumber('server');
            Route::post('/{server}/stop-provisioning', [ServerController::class, 'stopProvisioning'])->name('stop-provisioning')->whereNumber('server');
            Route::post('/{server}/provisioning/{step}/execute', [ServerController::class, 'executeProvisioningStep'])->name('execute.step')->whereNumber('server');
            Route::get('/{server}/provisioning-status', [ServerController::class, 'provisioningStatus'])->name('provisioning-status')->whereNumber('server');

            // Secrets (on-demand reveal)
            Route::post('/{server}/secrets/{secret}/reveal', [ServerController::class, 'revealSecret'])
                ->middleware('throttle:20,1')
                ->name('secrets.reveal')
                ->whereNumber('server')
                ->whereNumber('secret');

            // Access key secret (on-demand reveal with password reconfirmation)
            Route::post('/{server}/access-key-secret/reveal', [ServerController::class, 'revealAccessKeySecret'])
                ->middleware('throttle:10,1')
                ->name('access-key-secret.reveal')
                ->whereNumber('server');

            // SSH key pair (on-demand reveal with password reconfirmation)
            Route::post('/{server}/ssh-key-pair/reveal', [ServerController::class, 'revealSshKeyPair'])
                ->middleware('throttle:10,1')
                ->name('ssh-key-pair.reveal')
                ->whereNumber('server');

            // ACME setup
            Route::post('/{server}/setup-acme', [ServerController::class, 'setupAcme'])
                ->middleware('can:edit_servers')
                ->name('setup-acme')
                ->whereNumber('server');

            // Optimization tool
            Route::get('/{server}/optimization-tool', [ServerController::class, 'optimizationTool'])
                ->name('optimization-tool')
                ->whereNumber('server');
            Route::post('/{server}/apply-optimization', [ServerController::class, 'applyOptimization'])
                ->middleware('can:edit_servers')
                ->name('apply-optimization')
                ->whereNumber('server');

            // Parameterized CRUD
            Route::get('/{server}', [ServerController::class, 'show'])->name('show')->whereNumber('server');
            Route::get('/{server}/edit', [ServerController::class, 'edit'])->name('edit')->whereNumber('server');
            Route::put('/{server}', [ServerController::class, 'update'])->name('update')->whereNumber('server');
            Route::delete('/{server}', [ServerController::class, 'destroy'])->name('destroy')->whereNumber('server');
            Route::delete('/{server}/force-delete', [ServerController::class, 'forceDelete'])->name('force-delete')->whereNumber('server');
            Route::patch('/{server}/restore', [ServerController::class, 'restore'])->name('restore')->whereNumber('server');

            // Catch-all last
            Route::get('/{status?}', [ServerController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|failed|inactive|maintenance|trash)$');
        });

        // ---------------------------------------------------------------------
        // Agencies (Scaffold)
        // ---------------------------------------------------------------------
        Route::prefix('agencies')->name('agencies.')->middleware(['crud.exceptions'])->group(function (): void {
            // Static routes first
            Route::get('/data', [AgencyController::class, 'data'])->name('data');
            Route::post('/bulk-action', [AgencyController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [AgencyController::class, 'create'])->name('create');
            Route::post('/', [AgencyController::class, 'store'])->name('store');

            // Agency-Server association routes
            Route::get('/{agency}/servers', [AgencyServerController::class, 'getServers'])
                ->middleware('can:view_agencies')
                ->whereNumber('agency')
                ->name('servers.index');
            Route::post('/{agency}/servers', [AgencyServerController::class, 'attachServers'])
                ->middleware('can:edit_agencies')
                ->whereNumber('agency')
                ->name('servers.attach');
            Route::delete('/{agency}/servers/{server}', [AgencyServerController::class, 'detachServer'])
                ->middleware('can:edit_agencies')
                ->whereNumber('agency')
                ->whereNumber('server')
                ->name('servers.detach');
            Route::post('/{agency}/servers/{server}/set-primary', [AgencyServerController::class, 'setPrimaryServer'])
                ->middleware('can:edit_agencies')
                ->whereNumber('agency')
                ->whereNumber('server')
                ->name('servers.set-primary');
            Route::get('/{agency}/available-servers', [AgencyServerController::class, 'getAvailableServers'])
                ->middleware('can:view_agencies')
                ->whereNumber('agency')
                ->name('available-servers');

            // Agency-Provider association routes (DNS)
            Route::get('/{agency}/dns-providers', [AgencyProviderController::class, 'getDnsProviders'])
                ->middleware('can:view_agencies')
                ->whereNumber('agency')
                ->name('dns-providers.index');
            Route::post('/{agency}/dns-providers', [AgencyProviderController::class, 'attachDnsProviders'])
                ->middleware('can:edit_agencies')
                ->whereNumber('agency')
                ->name('dns-providers.attach');
            Route::post('/{agency}/dns-providers/{provider}/set-primary', [AgencyProviderController::class, 'setPrimaryDnsProvider'])
                ->middleware('can:edit_agencies')
                ->whereNumber('agency')
                ->whereNumber('provider')
                ->name('dns-providers.set-primary');
            Route::get('/{agency}/available-dns-providers', [AgencyProviderController::class, 'getAvailableDnsProviders'])
                ->middleware('can:view_agencies')
                ->whereNumber('agency')
                ->name('available-dns-providers');

            // Agency-Provider association routes (CDN)
            Route::get('/{agency}/cdn-providers', [AgencyProviderController::class, 'getCdnProviders'])
                ->middleware('can:view_agencies')
                ->whereNumber('agency')
                ->name('cdn-providers.index');
            Route::post('/{agency}/cdn-providers', [AgencyProviderController::class, 'attachCdnProviders'])
                ->middleware('can:edit_agencies')
                ->whereNumber('agency')
                ->name('cdn-providers.attach');
            Route::post('/{agency}/cdn-providers/{provider}/set-primary', [AgencyProviderController::class, 'setPrimaryCdnProvider'])
                ->middleware('can:edit_agencies')
                ->whereNumber('agency')
                ->whereNumber('provider')
                ->name('cdn-providers.set-primary');
            Route::get('/{agency}/available-cdn-providers', [AgencyProviderController::class, 'getAvailableCdnProviders'])
                ->middleware('can:view_agencies')
                ->whereNumber('agency')
                ->name('available-cdn-providers');

            // Shared provider detach route (works for both DNS and CDN)
            Route::delete('/{agency}/providers/{provider}', [AgencyProviderController::class, 'detachProvider'])
                ->middleware('can:edit_agencies')
                ->whereNumber('agency')
                ->whereNumber('provider')
                ->name('providers.detach');

            // Agency secret key
            Route::post('/{agency}/regenerate-secret-key', [AgencyController::class, 'regenerateSecretKey'])
                ->middleware('can:edit_agencies')
                ->whereNumber('agency')
                ->name('regenerate-secret-key');
            Route::post('/{agency}/secret-key/reveal', [AgencyController::class, 'revealSecretKey'])
                ->middleware(['can:edit_agencies', 'throttle:10,1'])
                ->whereNumber('agency')
                ->name('secret-key.reveal');

            // Parameterized CRUD
            Route::get('/{agency}', [AgencyController::class, 'show'])->name('show')->whereNumber('agency');
            Route::get('/{agency}/edit', [AgencyController::class, 'edit'])->name('edit')->whereNumber('agency');
            Route::put('/{agency}', [AgencyController::class, 'update'])->name('update')->whereNumber('agency');
            Route::delete('/{agency}', [AgencyController::class, 'destroy'])->name('destroy')->whereNumber('agency');
            Route::delete('/{agency}/force-delete', [AgencyController::class, 'forceDelete'])->name('force-delete')->whereNumber('agency');
            Route::patch('/{agency}/restore', [AgencyController::class, 'restore'])->name('restore')->whereNumber('agency');

            // Catch-all last
            Route::get('/{status?}', [AgencyController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|inactive|trash)$');
        });

        // ---------------------------------------------------------------------
        // Websites (Scaffold + custom lifecycle/provisioning)
        // ---------------------------------------------------------------------
        Route::prefix('websites')->name('websites.')->middleware(['crud.exceptions'])->group(function (): void {
            // Static routes first
            Route::get('/data', [WebsiteController::class, 'data'])->name('data');
            Route::post('/bulk-action', [WebsiteController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [WebsiteController::class, 'create'])->name('create');
            Route::post('/', [WebsiteController::class, 'store'])->name('store');

            // Custom routes (must be before catch-all)
            Route::get('/order/{order}/create', [WebsiteController::class, 'createFromOrder'])
                ->middleware('can:add_websites')
                ->whereNumber('order')
                ->name('order.create');

            Route::post('/{website}/update-status/{status}', [WebsiteController::class, 'updateStatus'])->name('update-status')->whereNumber('website');
            Route::post('/{website}/update-version', [WebsiteController::class, 'updateVersion'])->name('update-version')->whereNumber('website');
            Route::post('/{website}/sync-website', [WebsiteController::class, 'syncWebsite'])->name('sync-website')->whereNumber('website');
            Route::post('/{website}/recache-application', [WebsiteController::class, 'recacheApplication'])->name('recache-application')->whereNumber('website');
            Route::post('/{website}/retry-provision', [WebsiteController::class, 'retryProvision'])->name('retry-provision')->whereNumber('website');
            Route::post('/{website}/update-setup-status', [WebsiteController::class, 'updateSetupStatus'])->name('update-setup-status')->whereNumber('website');
            Route::post('/{website}/setup-queue-worker', [WebsiteController::class, 'setupQueueWorker'])->name('setup-queue-worker')->whereNumber('website');
            Route::post('/{website}/scale-queue-worker/{count}', [WebsiteController::class, 'scaleQueueWorker'])->name('scale-queue-worker')->whereNumber('website')->whereNumber('count');
            Route::post('/{website}/remove-from-server', [WebsiteController::class, 'removeFromServer'])->name('remove-from-server')->whereNumber('website');
            Route::post('/{website}/reprovision', [WebsiteController::class, 'reprovision'])->name('reprovision')->whereNumber('website');
            Route::get('/{website}/provisioning-status', [WebsiteController::class, 'provisioningStatus'])->name('provisioning-status')->whereNumber('website');
            Route::post('/{website}/confirm-dns', [WebsiteController::class, 'confirmDns'])->name('confirm-dns')->whereNumber('website');
            Route::post('/{website}/stop-dns-validation', [WebsiteController::class, 'stopDnsValidation'])->name('stop-dns-validation')->whereNumber('website');

            Route::post('/{website}/{step}/execute', [WebsiteController::class, 'executeStep'])->name('execute.step')->whereNumber('website');
            Route::post('/{website}/{step}/revert', [WebsiteController::class, 'revertStep'])->name('revert.step')->whereNumber('website');

            // Secrets (on-demand reveal)
            Route::post('/{website}/secrets/{secret}/reveal', [WebsiteController::class, 'revealSecret'])
                ->middleware('throttle:20,1')
                ->name('secrets.reveal')
                ->whereNumber('website')
                ->whereNumber('secret');

            // Parameterized CRUD
            Route::get('/{website}', [WebsiteController::class, 'show'])->name('show')->whereNumber('website');
            Route::get('/{website}/edit', [WebsiteController::class, 'edit'])->name('edit')->whereNumber('website');
            Route::put('/{website}', [WebsiteController::class, 'update'])->name('update')->whereNumber('website');
            Route::delete('/{website}', [WebsiteController::class, 'destroy'])->name('destroy')->whereNumber('website');
            Route::delete('/{website}/force-delete', [WebsiteController::class, 'forceDelete'])->name('force-delete')->whereNumber('website');
            Route::patch('/{website}/restore', [WebsiteController::class, 'restore'])->name('restore')->whereNumber('website');

            // Catch-all last (must match status tabs)
            Route::get('/{status?}', [WebsiteController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|failed|active|suspended|expired|trash)$');
        });

        // ---------------------------------------------------------------------
        // TLDs (Scaffold)
        // ---------------------------------------------------------------------
        Route::prefix('tlds')->name('tlds.')->middleware(['crud.exceptions'])->group(function (): void {
            Route::get('/data', [TldController::class, 'data'])->name('data');
            Route::post('/bulk-action', [TldController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [TldController::class, 'create'])->name('create');
            Route::post('/', [TldController::class, 'store'])->name('store');

            Route::get('/{tld}', [TldController::class, 'show'])->name('show')->whereNumber('tld');
            Route::get('/{tld}/edit', [TldController::class, 'edit'])->name('edit')->whereNumber('tld');
            Route::put('/{tld}', [TldController::class, 'update'])->name('update')->whereNumber('tld');
            Route::delete('/{tld}', [TldController::class, 'destroy'])->name('destroy')->whereNumber('tld');
            Route::delete('/{tld}/force-delete', [TldController::class, 'forceDelete'])->name('force-delete')->whereNumber('tld');
            Route::patch('/{tld}/restore', [TldController::class, 'restore'])->name('restore')->whereNumber('tld');

            Route::get('/{status?}', [TldController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|inactive|trash)$');
        });

        // ---------------------------------------------------------------------
        // Settings
        // ---------------------------------------------------------------------
        Route::prefix('settings')->name('settings.')->group(function (): void {
            Route::get('/', [SettingsController::class, 'settings'])
                ->middleware('can:manage_platform_settings')
                ->name('index');
            Route::post('/update', [SettingsController::class, 'updateSettings'])
                ->middleware('can:manage_platform_settings')
                ->name('update');
        });

        // ---------------------------------------------------------------------
        // Providers (Scaffold)
        // ---------------------------------------------------------------------
        Route::prefix('providers')->name('providers.')->middleware(['crud.exceptions'])->group(function (): void {
            // Static routes first
            Route::get('/data', [ProviderController::class, 'data'])->name('data');
            Route::post('/bulk-action', [ProviderController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [ProviderController::class, 'create'])->name('create');
            Route::post('/', [ProviderController::class, 'store'])->name('store');

            // API endpoints for AJAX (static routes first)
            Route::get('/api/vendors', [ProviderController::class, 'getVendorsForType'])->name('api.vendors');
            Route::get('/api/list', [ProviderController::class, 'getProvidersForType'])->name('api.list');

            // Parameterized
            Route::post('/{provider}/sync', [ProviderController::class, 'sync'])->name('sync')->whereNumber('provider');
            Route::get('/{provider}', [ProviderController::class, 'show'])->name('show')->whereNumber('provider');
            Route::get('/{provider}/edit', [ProviderController::class, 'edit'])->name('edit')->whereNumber('provider');
            Route::put('/{provider}', [ProviderController::class, 'update'])->name('update')->whereNumber('provider');
            Route::delete('/{provider}', [ProviderController::class, 'destroy'])->name('destroy')->whereNumber('provider');
            Route::delete('/{provider}/force-delete', [ProviderController::class, 'forceDelete'])->name('force-delete')->whereNumber('provider');
            Route::patch('/{provider}/restore', [ProviderController::class, 'restore'])->name('restore')->whereNumber('provider');

            // Catch-all last
            Route::get('/{status?}', [ProviderController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|inactive|suspended|trash)$');
        });

        // ---------------------------------------------------------------------
        // Secrets (Scaffold)
        // ---------------------------------------------------------------------
        Route::prefix('secrets')->name('secrets.')->middleware(['crud.exceptions'])->group(function (): void {
            Route::get('/data', [SecretController::class, 'data'])->name('data');
            Route::post('/bulk-action', [SecretController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [SecretController::class, 'create'])->name('create');
            Route::post('/', [SecretController::class, 'store'])->name('store');

            Route::get('/{secret}', [SecretController::class, 'show'])->name('show')->whereNumber('secret');
            Route::get('/{secret}/edit', [SecretController::class, 'edit'])->name('edit')->whereNumber('secret');
            Route::put('/{secret}', [SecretController::class, 'update'])->name('update')->whereNumber('secret');
            Route::delete('/{secret}', [SecretController::class, 'destroy'])->name('destroy')->whereNumber('secret');
            Route::delete('/{secret}/force-delete', [SecretController::class, 'forceDelete'])->name('force-delete')->whereNumber('secret');
            Route::patch('/{secret}/restore', [SecretController::class, 'restore'])->name('restore')->whereNumber('secret');

            Route::get('/{status?}', [SecretController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|inactive|trash)$');
        });

        // ---------------------------------------------------------------------
        // Global SSL Certificates (legacy controller)
        // ---------------------------------------------------------------------
        Route::prefix('ssl-certificates')->name('ssl-certificates.')->middleware(['crud.exceptions'])->group(function (): void {
            Route::get('/{status?}', [DomainSslCertificateController::class, 'globalIndex'])
                ->name('index')
                ->where('status', '^(all|active|expiring|expired)$');
        });

        // ---------------------------------------------------------------------
        // Domains (Scaffold + custom WHOIS/SSL)
        // ---------------------------------------------------------------------
        Route::prefix('domains')->name('domains.')->middleware(['crud.exceptions'])->group(function (): void {
            // Static routes first
            Route::get('/data', [DomainController::class, 'data'])->name('data');
            Route::post('/bulk-action', [DomainController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [DomainController::class, 'create'])->name('create');
            Route::post('/', [DomainController::class, 'store'])->name('store');

            // WHOIS helpers
            Route::post('/get-whois-data', [DomainController::class, 'getWhoisData'])->name('getWhoisData');
            Route::post('/lookup-domain', [DomainController::class, 'lookupDomain'])->name('lookupDomain');

            // Parameterized CRUD + custom
            Route::post('/{domain}/refresh-whois', [DomainController::class, 'refreshWhois'])->name('refresh-whois')->whereNumber('domain');

            Route::get('/{domain}', [DomainController::class, 'show'])->name('show')->whereNumber('domain');
            Route::get('/{domain}/edit', [DomainController::class, 'edit'])->name('edit')->whereNumber('domain');
            Route::put('/{domain}', [DomainController::class, 'update'])->name('update')->whereNumber('domain');
            Route::delete('/{domain}', [DomainController::class, 'destroy'])->name('destroy')->whereNumber('domain');
            Route::delete('/{domain}/force-delete', [DomainController::class, 'forceDelete'])->name('force-delete')->whereNumber('domain');
            Route::patch('/{domain}/restore', [DomainController::class, 'restore'])->name('restore')->whereNumber('domain');

            // Domain Accounts Sub-routes
            Route::prefix('{domain}/accounts')->name('accounts.')->whereNumber('domain')->group(function (): void {
                Route::get('/create', [DomainController::class, 'createAccount'])
                    ->middleware('can:add_domain_accounts')
                    ->name('create');
                Route::get('/{id}/edit', [DomainController::class, 'editAccount'])
                    ->middleware('can:edit_domain_accounts')
                    ->name('edit')
                    ->whereNumber('id');
            });

            // SSL Certificates Sub-routes (legacy controller)
            Route::prefix('{domain}/ssl-certificates')->name('ssl-certificates.')->whereNumber('domain')->group(function (): void {
                Route::get('/generate-self-signed', [DomainSslCertificateController::class, 'generateSelfSignedForm'])
                    ->middleware('can:edit_domains')
                    ->name('generate-self-signed');
                Route::post('/generate-self-signed', [DomainSslCertificateController::class, 'generateSelfSigned'])
                    ->middleware('can:edit_domains')
                    ->name('generate-self-signed.store');
                Route::get('/create', [DomainSslCertificateController::class, 'create'])
                    ->middleware('can:edit_domains')
                    ->name('create');
                Route::post('/', [DomainSslCertificateController::class, 'store'])
                    ->middleware('can:edit_domains')
                    ->name('store');
                Route::get('/{certificate}', [DomainSslCertificateController::class, 'show'])
                    ->middleware('can:view_domains')
                    ->name('show')
                    ->whereNumber('certificate');
                Route::get('/{certificate}/edit', [DomainSslCertificateController::class, 'edit'])
                    ->middleware('can:edit_domains')
                    ->name('edit')
                    ->whereNumber('certificate');
                Route::put('/{certificate}', [DomainSslCertificateController::class, 'update'])
                    ->middleware('can:edit_domains')
                    ->name('update')
                    ->whereNumber('certificate');
                Route::delete('/{certificate}', [DomainSslCertificateController::class, 'destroy'])
                    ->middleware('can:edit_domains')
                    ->name('destroy')
                    ->whereNumber('certificate');
                Route::get('/{certificate}/download-key', [DomainSslCertificateController::class, 'downloadPrivateKey'])
                    ->middleware('can:edit_domains')
                    ->name('download-key')
                    ->whereNumber('certificate');
                Route::get('/{certificate}/download-cert', [DomainSslCertificateController::class, 'downloadCertificate'])
                    ->middleware('can:view_domains')
                    ->name('download-cert')
                    ->whereNumber('certificate');
            });

            // Catch-all last
            Route::get('/{status?}', [DomainController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|active|inactive|expired|pending|trash)$');
        });

        // ---------------------------------------------------------------------
        // DNS Records (Scaffold)
        // ---------------------------------------------------------------------
        Route::prefix('dns')->name('dns.')->middleware(['crud.exceptions'])->group(function (): void {
            Route::post('/bulk-action', [DomainDnsController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [DomainDnsController::class, 'create'])->name('create');
            Route::post('/', [DomainDnsController::class, 'store'])->name('store');

            Route::get('/{domainDnsRecord}', [DomainDnsController::class, 'show'])->name('show')->whereNumber('domainDnsRecord');
            Route::get('/{domainDnsRecord}/edit', [DomainDnsController::class, 'edit'])->name('edit')->whereNumber('domainDnsRecord');
            Route::put('/{domainDnsRecord}', [DomainDnsController::class, 'update'])->name('update')->whereNumber('domainDnsRecord');
            Route::delete('/{domainDnsRecord}', [DomainDnsController::class, 'destroy'])->name('destroy')->whereNumber('domainDnsRecord');
            Route::delete('/{domainDnsRecord}/force-delete', [DomainDnsController::class, 'forceDelete'])->name('force-delete')->whereNumber('domainDnsRecord');
            Route::patch('/{domainDnsRecord}/restore', [DomainDnsController::class, 'restore'])->name('restore')->whereNumber('domainDnsRecord');

            Route::get('/{status?}', [DomainDnsController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|trash)$');
        });
    });
});
