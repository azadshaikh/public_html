<?php

declare(strict_types=1);

namespace Modules\Platform\Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
use Modules\Platform\Models\Website;
use Modules\Platform\Providers\PlatformServiceProvider;
use Tests\TestCase;

class PlatformInertiaPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePlatformModuleBooted();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach ([
            'view_agencies',
            'add_agencies',
            'edit_agencies',
            'delete_agencies',
            'restore_agencies',
            'view_servers',
            'add_servers',
            'edit_servers',
            'delete_servers',
            'restore_servers',
            'view_websites',
            'add_websites',
            'edit_websites',
            'delete_websites',
            'restore_websites',
            'view_domains',
            'add_domains',
            'edit_domains',
            'delete_domains',
            'restore_domains',
            'view_domain_dns_records',
            'add_domain_dns_records',
            'edit_domain_dns_records',
            'delete_domain_dns_records',
            'restore_domain_dns_records',
        ] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'platform',
                    'module_slug' => 'platform',
                ],
            );
        }

        $this->admin = User::factory()->create([
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->admin->assignRole(Role::findByName('administrator', 'web'));
        $this->admin->givePermissionTo([
            'view_agencies',
            'add_agencies',
            'edit_agencies',
            'delete_agencies',
            'restore_agencies',
            'view_servers',
            'add_servers',
            'edit_servers',
            'delete_servers',
            'restore_servers',
            'view_websites',
            'add_websites',
            'edit_websites',
            'delete_websites',
            'restore_websites',
            'view_domains',
            'add_domains',
            'edit_domains',
            'delete_domains',
            'restore_domains',
            'view_domain_dns_records',
            'add_domain_dns_records',
            'edit_domain_dns_records',
            'delete_domain_dns_records',
            'restore_domain_dns_records',
        ]);
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->ensurePlatformModuleBooted();
    }

    private function ensurePlatformModuleBooted(): void
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

    private function platformTablesExist(): bool
    {
        return Schema::hasTable('platform_agencies')
            && Schema::hasTable('platform_servers')
            && Schema::hasTable('platform_websites');
    }

    public function test_platform_create_pages_render_with_inertia(): void
    {
        $this->actingAs($this->admin)
            ->get(route('platform.agencies.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/agencies/create')
                ->has('initialValues')
                ->has('typeOptions')
                ->has('ownerOptions')
                ->has('planOptions')
                ->has('statusOptions'));

        $this->actingAs($this->admin)
            ->get(route('platform.servers.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/servers/create')
                ->has('initialValues')
                ->has('typeOptions')
                ->has('providerOptions')
                ->has('statusOptions')
                ->where('initialValues.creation_mode', 'provision'));

        $this->actingAs($this->admin)
            ->get(route('platform.websites.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/websites/create')
                ->has('initialValues')
                ->has('serverOptions')
                ->has('agencyOptions')
                ->has('typeOptions')
                ->has('planOptions'));

        $domain = $this->createDomain();

        $this->actingAs($this->admin)
            ->get(route('platform.dns.create', ['domain_id' => $domain->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/dns/create')
                ->where('domain.id', $domain->id)
                ->where('domain.name', $domain->name)
                ->has('initialValues')
                ->has('typeOptions')
                ->has('ttlOptions'));
    }

    public function test_platform_edit_and_show_pages_render_with_inertia(): void
    {
        $agency = $this->createAgency();
        $serverProvider = $this->createProvider(Provider::TYPE_SERVER, 'Server Provider');
        $server = $this->createServer($serverProvider, $agency);
        $dnsProvider = $this->createProvider(Provider::TYPE_DNS, 'DNS Provider');
        $cdnProvider = $this->createProvider(Provider::TYPE_CDN, 'CDN Provider');
        $website = $this->createWebsite($agency, $server, $dnsProvider, $cdnProvider);
        $domain = $this->createDomain($agency);
        $dnsRecord = $this->createDnsRecord($domain);

        $this->actingAs($this->admin)
            ->get(route('platform.agencies.edit', $agency))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/agencies/edit')
                ->where('agency.id', $agency->id)
                ->where('initialValues.name', $agency->name));

        $this->actingAs($this->admin)
            ->get(route('platform.agencies.show', $agency))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/agencies/show')
                ->where('agency.id', $agency->id)
                ->has('websites')
                ->has('servers'));

        $this->actingAs($this->admin)
            ->get(route('platform.servers.edit', $server))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/servers/edit')
                ->where('server.id', $server->id)
                ->where('initialValues.name', $server->name));

        $this->actingAs($this->admin)
            ->get(route('platform.servers.show', $server))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/servers/show')
                ->where('server.id', $server->id)
                ->has('provisioningSteps')
                ->has('websiteCounts'));

        $this->actingAs($this->admin)
            ->get(route('platform.websites.edit', $website))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/websites/edit')
                ->where('website.id', $website->id)
                ->where('initialValues.name', $website->name));

        $this->actingAs($this->admin)
            ->get(route('platform.websites.show', $website))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/websites/show')
                ->where('website.id', $website->id)
                ->has('provisioningSteps')
                ->has('activities'));

        $this->actingAs($this->admin)
            ->get(route('platform.dns.edit', $dnsRecord))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/dns/edit')
                ->where('domainDnsRecord.id', $dnsRecord->id)
                ->where('domain.id', $domain->id)
                ->where('initialValues.name', $dnsRecord->name));

        $this->actingAs($this->admin)
            ->get(route('platform.dns.show', $dnsRecord))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/dns/show')
                ->where('domainDnsRecord.id', $dnsRecord->id)
                ->where('domainDnsRecord.domain_id', $domain->id)
                ->where('domainDnsRecord.name', $dnsRecord->name));
    }

    private function createDomain(?Agency $agency = null): Domain
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

    private function createDnsRecord(Domain $domain): DomainDnsRecord
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

    private function createAgency(): Agency
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

    private function createProvider(string $type, string $name): Provider
    {
        return Provider::query()->create([
            'name' => $name,
            'type' => $type,
            'vendor' => 'demo',
            'status' => 'active',
        ]);
    }

    private function createServer(Provider $provider, Agency $agency): Server
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

    private function createWebsite(Agency $agency, Server $server, Provider $dnsProvider, Provider $cdnProvider): Website
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
}
