<?php

declare(strict_types=1);

namespace Modules\Platform\Tests\Feature;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\DomainDnsRecord;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Tld;
use Modules\Platform\Models\Website;
use Modules\Platform\Providers\PlatformServiceProvider;

trait PlatformInertiaPagesTestSupport
{
    protected function ensurePlatformModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('platform.agencies.create')) {
            app()->register(PlatformServiceProvider::class);
        }

        if (! Route::has('platform.agencies.create')) {
            Route::middleware('web')->group(base_path('modules/Platform/routes/web.php'));
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }

        if (! $this->platformTablesExist()) {
            Artisan::call('migrate', [
                '--path' => base_path('modules/Platform/database/migrations'),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    protected function platformTablesExist(): bool
    {
        return Schema::hasTable('platform_agencies')
            && Schema::hasTable('platform_servers')
            && Schema::hasTable('platform_websites');
    }

    protected function createDomain(?Agency $agency = null): Domain
    {
        return Domain::query()->create([
            'name' => 'example.com',
            'type' => 'default',
            'agency_id' => $agency?->id,
            'status' => 'active',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    protected function createDnsRecord(Domain $domain): DomainDnsRecord
    {
        return DomainDnsRecord::query()->create([
            'domain_id' => $domain->id,
            'type' => 0,
            'name' => 'www',
            'value' => '203.0.113.11',
            'ttl' => 3600,
            'disabled' => false,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    protected function createAgency(): Agency
    {
        return Agency::query()->create([
            'uid' => 'AGY0001',
            'name' => 'Agency One',
            'email' => 'agency@example.com',
            'type' => 'default',
            'plan' => 'starter',
            'website_id_prefix' => 'WS',
            'website_id_zero_padding' => 5,
            'owner_id' => $this->admin->id,
            'status' => 'active',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    protected function createProvider(string $type, string $name): Provider
    {
        return Provider::query()->create([
            'name' => $name,
            'type' => $type,
            'vendor' => 'demo',
            'status' => 'active',
        ]);
    }

    protected function createTld(): Tld
    {
        return Tld::query()->create([
            'tld' => '.com',
            'whois_server' => 'whois.verisign-grs.com',
            'is_main' => true,
            'is_suggested' => true,
            'price' => '12.99',
            'sale_price' => '9.99',
            'status' => true,
            'tld_order' => 1,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    protected function createServer(Provider $provider, Agency $agency): Server
    {
        $server = Server::query()->create([
            'uid' => 'SVR0001',
            'name' => 'Main Server',
            'monitor' => true,
            'ip' => '203.0.113.10',
            'port' => 8443,
            'access_key_id' => 'demo-key',
            'access_key_secret' => 'demo-secret',
            'type' => 'production',
            'driver' => 'hestia',
            'current_domains' => 1,
            'max_domains' => 100,
            'fqdn' => 'server.example.com',
            'status' => 'active',
            'provisioning_status' => Server::PROVISIONING_STATUS_READY,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $server->assignProvider($provider->id, true);
        $server->agencies()->attach($agency->id, ['is_primary' => true]);

        return $server->fresh();
    }

    protected function createWebsite(Agency $agency, Server $server, Provider $dnsProvider, Provider $cdnProvider): Website
    {
        $website = Website::query()->create([
            'uid' => 'ws-demo',
            'name' => 'Demo Site',
            'domain' => 'demo.example.com',
            'server_id' => $server->id,
            'agency_id' => $agency->id,
            'status' => WebsiteStatus::Active,
            'type' => 'paid',
            'plan_tier' => 'basic',
            'customer_data' => [
                'name' => 'Demo Customer',
                'email' => 'customer@example.com',
            ],
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $website->assignProvider($dnsProvider->id, true);
        $website->assignProvider($cdnProvider->id, true);

        return $website->fresh();
    }

    protected function assertPlatformStandardIndexContract(
        string $url,
        string $component,
        string $rowField,
        mixed $expectedValue,
    ): void {
        $normalizedRowField = str_replace('rows.data.0.', '', $rowField);

        $this->actingAs($this->admin)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component($component)
                ->has('config.actions')
                ->has('empty_state_config.title')
                ->has('empty_state_config.action.label')
                ->has('empty_state_config.action.url')
                ->where('rows.data', fn ($rows): bool => collect($rows)->contains(
                    fn (array $row): bool => data_get($row, $normalizedRowField) === $expectedValue
                        && filled(data_get($row, 'actions')),
                )));
    }

    protected function jsonPayloadSize(mixed $value): int
    {
        return strlen(json_encode($value, JSON_THROW_ON_ERROR));
    }
}
