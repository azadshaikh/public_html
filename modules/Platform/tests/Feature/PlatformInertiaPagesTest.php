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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Jobs\WebsiteUpdatePrimaryHostname;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\DomainDnsRecord;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Tld;
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
            'view_tlds',
            'add_tlds',
            'edit_tlds',
            'delete_tlds',
            'restore_tlds',
            'view_providers',
            'add_providers',
            'edit_providers',
            'delete_providers',
            'restore_providers',
            'view_secrets',
            'add_secrets',
            'edit_secrets',
            'delete_secrets',
            'restore_secrets',
            'manage_platform_settings',
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
            'view_tlds',
            'add_tlds',
            'edit_tlds',
            'delete_tlds',
            'restore_tlds',
            'view_providers',
            'add_providers',
            'edit_providers',
            'delete_providers',
            'restore_providers',
            'view_secrets',
            'add_secrets',
            'edit_secrets',
            'delete_secrets',
            'restore_secrets',
            'manage_platform_settings',
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
                ->has('statusOptions')
                ->has('typeOptions')
                ->has('planOptions')
                ->has('dnsProviderOptions')
                ->has('cdnProviderOptions'));

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

    public function test_platform_agency_create_page_can_request_media_picker_props(): void
    {
        $this->actingAs($this->admin)
            ->get(route('platform.agencies.create', ['picker' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/agencies/create')
                ->has('pickerMedia.data')
                ->where('pickerFilters.picker', '1')
                ->has('uploadSettings', fn (Assert $uploadSettings): Assert => $uploadSettings
                    ->has('upload_route')
                    ->has('max_size_mb')
                    ->etc()
                )
                ->has('pickerStatistics', fn (Assert $pickerStatistics): Assert => $pickerStatistics
                    ->has('total')
                    ->has('trash')
                )
            );
    }

    public function test_platform_settings_page_renders_with_inertia(): void
    {
        $this->actingAs($this->admin)
            ->get(route('platform.settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/settings/index')
                ->has('initialValues')
                ->has('serverOptions')
                ->has('settingsNav')
                ->where('settingsNav.0.slug', 'general'));
    }

    public function test_platform_standard_scaffold_index_pages_use_backend_action_and_empty_state_contracts(): void
    {
        $agency = $this->createAgency();
        $serverProvider = $this->createProvider(Provider::TYPE_SERVER, 'Server Provider');
        $server = $this->createServer($serverProvider, $agency);
        $dnsProvider = $this->createProvider(Provider::TYPE_DNS, 'DNS Provider');
        $cdnProvider = $this->createProvider(Provider::TYPE_CDN, 'CDN Provider');
        $website = $this->createWebsite($agency, $server, $dnsProvider, $cdnProvider);
        $domain = $this->createDomain($agency);
        $dnsRecord = $this->createDnsRecord($domain);
        $tld = $this->createTld();

        Secret::query()->create([
            'secretable_type' => Agency::class,
            'secretable_id' => $agency->id,
            'key' => 'platform_api_key',
            'username' => 'deploy-user',
            'type' => 'password',
            'value' => encrypt('secret-value'),
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('platform.agencies.index', ['status' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/agencies/index')
                ->has('config.actions')
                ->where('empty_state_config.title', 'No Agencies Found')
                ->where('empty_state_config.action.label', 'Create Agency')
                ->where('empty_state_config.action.url', route('platform.agencies.create'))
                ->has('rows.data.0.actions', 3));

        $this->actingAs($this->admin)
            ->get(route('platform.servers.index', ['status' => 'all']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/servers/index')
                ->has('config.actions')
                ->where('empty_state_config.title', 'No Servers Found')
                ->where('empty_state_config.action.label', 'Create Server')
                ->where('empty_state_config.action.url', route('platform.servers.create'))
                ->where('rows.data.0.name', $server->name)
                ->has('rows.data.0.actions', 3));

        $server->delete();

        $this->actingAs($this->admin)
            ->get(route('platform.servers.index', ['status' => 'trash']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/servers/index')
                ->where('filters.status', 'trash')
                ->where('config.actions', fn ($actions): bool => collect($actions)
                    ->pluck('key')
                    ->contains('restore')
                    && collect($actions)->pluck('key')->contains('force_delete')));

        $this->assertPlatformStandardIndexContract(
            route('platform.providers.index', ['status' => 'all']),
            'platform/providers/index',
            'rows.data.0.name',
            $serverProvider->name,
        );

        $this->assertPlatformStandardIndexContract(
            route('platform.secrets.index', ['status' => 'all']),
            'platform/secrets/index',
            'rows.data.0.key',
            'platform_api_key',
        );

        $this->assertPlatformStandardIndexContract(
            route('platform.websites.index', ['status' => 'all']),
            'platform/websites/index',
            'rows.data.0.name',
            $website->name,
        );

        $this->assertPlatformStandardIndexContract(
            route('platform.tlds.index', ['status' => 'all']),
            'platform/tlds/index',
            'rows.data.0.tld',
            $tld->tld,
        );

        $this->assertPlatformStandardIndexContract(
            route('platform.domains.index', ['status' => 'all']),
            'platform/domains/index',
            'rows.data.0.name',
            $domain->name,
        );

        $this->assertPlatformStandardIndexContract(
            route('platform.dns.index', ['status' => 'all', 'domain_id' => $domain->id]),
            'platform/dns/index',
            'rows.data.0.name',
            $dnsRecord->name,
        );
    }

    public function test_platform_standard_scaffold_index_pages_stay_within_the_backend_driven_payload_budget(): void
    {
        $agency = $this->createAgency();
        $serverProvider = $this->createProvider(Provider::TYPE_SERVER, 'Server Provider');
        $this->createServer($serverProvider, $agency);

        Secret::query()->create([
            'secretable_type' => Agency::class,
            'secretable_id' => $agency->id,
            'key' => 'platform_api_key',
            'username' => 'deploy-user',
            'type' => 'password',
            'value' => encrypt('secret-value'),
            'is_active' => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $agenciesResponse = $this->actingAs($this->admin)
            ->get(route('platform.agencies.index', ['status' => 'all']))
            ->assertOk();

        $serversResponse = $this->actingAs($this->admin)
            ->get(route('platform.servers.index', ['status' => 'all']))
            ->assertOk();

        $agenciesFeaturePayloadSize = $this->jsonPayloadSize([
            'config' => $agenciesResponse->inertiaProps('config'),
            'rows' => $agenciesResponse->inertiaProps('rows'),
            'filters' => $agenciesResponse->inertiaProps('filters'),
            'statistics' => $agenciesResponse->inertiaProps('statistics'),
            'empty_state_config' => $agenciesResponse->inertiaProps('empty_state_config'),
        ]);

        $serversFeaturePayloadSize = $this->jsonPayloadSize([
            'config' => $serversResponse->inertiaProps('config'),
            'rows' => $serversResponse->inertiaProps('rows'),
            'filters' => $serversResponse->inertiaProps('filters'),
            'statistics' => $serversResponse->inertiaProps('statistics'),
            'empty_state_config' => $serversResponse->inertiaProps('empty_state_config'),
        ]);

        $agencyRowPayloadSize = $this->jsonPayloadSize($agenciesResponse->inertiaProps('rows.data.0'));
        $serverRowPayloadSize = $this->jsonPayloadSize($serversResponse->inertiaProps('rows.data.0'));

        $this->assertLessThan(
            18000,
            $agenciesFeaturePayloadSize,
            sprintf('Expected the Platform agencies index feature payload to stay within budget; received %d bytes.', $agenciesFeaturePayloadSize),
        );

        $this->assertLessThan(
            18000,
            $serversFeaturePayloadSize,
            sprintf('Expected the Platform servers index feature payload to stay within budget; received %d bytes.', $serversFeaturePayloadSize),
        );

        $this->assertLessThan(
            1800,
            $agencyRowPayloadSize,
            sprintf('Expected the Platform agencies index row payload to stay within budget; received %d bytes.', $agencyRowPayloadSize),
        );

        $this->assertLessThan(
            2200,
            $serverRowPayloadSize,
            sprintf('Expected the Platform servers index row payload to stay within budget; received %d bytes.', $serverRowPayloadSize),
        );
    }

    public function test_platform_edit_and_show_pages_render_with_inertia(): void
    {
        $agency = $this->createAgency();
        $serverProvider = $this->createProvider(Provider::TYPE_SERVER, 'Server Provider');
        $server = $this->createServer($serverProvider, $agency);
        $server->ssh_public_key = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestEditServerKey astero@test';
        $server->setMetadata('location_country_code', 'IN');
        $server->setMetadata('location_country', 'India');
        $server->setMetadata('location_city_code', 'BOM');
        $server->setMetadata('location_city', 'Mumbai');
        $server->setMetadata('server_os', 'Ubuntu 24.04');
        $server->setMetadata('server_cpu', 'Intel(R) Xeon(R)');
        $server->setMetadata('server_ccore', '2');
        $server->setMetadata('server_ram', 3074);
        $server->setMetadata('server_storage', 89);
        $server->setMetadata('astero_version', '1.0.46');
        $server->setMetadata('hestia_version', '1.9.4');
        $server->save();
        $server = $server->fresh();
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
                ->where('initialValues.name', $server->name)
                ->where('initialValues.location_country_code', 'IN')
                ->where('initialValues.location_city', 'Mumbai')
                ->where('initialValues.server_os', 'Ubuntu 24.04')
                ->where('initialValues.astero_version', '1.0.46')
                ->where('sshCommand', fn ($value): bool => is_string($value) && str_contains($value, 'ssh-ed25519')));

        $server->update([
            'provisioning_status' => Server::PROVISIONING_STATUS_FAILED,
            'status' => 'failed',
            'metadata' => [
                'creation_mode' => 'provision',
                'provisioning_started_at' => now()->subMinutes(20)->toISOString(),
                'provisioning_completed_at' => now()->subMinutes(5)->toISOString(),
                'provisioning_steps' => [
                    'ssh_connection' => [
                        'status' => 'completed',
                        'message' => 'SSH connectivity confirmed.',
                        'started_at' => now()->subMinutes(19)->toISOString(),
                        'completed_at' => now()->subMinutes(18)->toISOString(),
                    ],
                ],
            ],
        ]);
        $serverProgressPercent = (int) round(100 / 13);

        $this->actingAs($this->admin)
            ->get(route('platform.servers.show', $server))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/servers/show')
                ->where('server.id', $server->id)
                ->where('server.access_key_id', $server->access_key_id)
                ->has('provisioningSteps')
                ->where('provisioningSteps.0.key', 'ssh_connection')
                ->where('provisioningSteps.0.status', 'done')
                ->where('provisioningSteps.0.message', 'SSH connectivity confirmed.')
                ->where('provisioningSteps.0.started_at', app_date_time_format($server->getMetadata('provisioning_steps.ssh_connection.started_at'), 'datetime'))
                ->where('provisioningSteps.0.completed_at', app_date_time_format($server->getMetadata('provisioning_steps.ssh_connection.completed_at'), 'datetime'))
                ->where('provisioningRun.started_at', app_date_time_format($server->getMetadata('provisioning_started_at'), 'datetime'))
                ->where('provisioningRun.completed_at', app_date_time_format($server->getMetadata('provisioning_completed_at'), 'datetime'))
                ->has('websiteCounts')
                ->has('agencies')
                ->has('metadataItems')
                ->where('canRevealSecrets', true)
                ->where('canRevealSshKeyPair', false)
                ->where('canManageScriptLog', true));

        $this->actingAs($this->admin)
            ->get(sprintf('/%s/platform/servers/%d/provisioning-status', config('app.admin_slug'), $server->id))
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'current_status' => Server::PROVISIONING_STATUS_FAILED,
                'progress_percent' => $serverProgressPercent,
                'provisioning_run' => [
                    'started_at' => app_date_time_format($server->getMetadata('provisioning_started_at'), 'datetime'),
                    'completed_at' => app_date_time_format($server->getMetadata('provisioning_completed_at'), 'datetime'),
                ],
            ])
            ->assertJsonPath('provisioning_steps.0.key', 'ssh_connection')
            ->assertJsonPath('provisioning_steps.0.status', 'done')
            ->assertJsonPath('provisioning_steps.0.message', 'SSH connectivity confirmed.')
            ->assertJsonPath('provisioning_steps.0.started_at', app_date_time_format($server->getMetadata('provisioning_steps.ssh_connection.started_at'), 'datetime'))
            ->assertJsonPath('provisioning_steps.0.completed_at', app_date_time_format($server->getMetadata('provisioning_steps.ssh_connection.completed_at'), 'datetime'));

        $this->actingAs($this->admin)
            ->get(route('platform.websites.edit', $website))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/websites/edit')
                ->where('website.id', $website->id)
                ->where('initialValues.name', $website->name));

        $stepKeys = array_keys(config('platform.website.steps', []));
        $this->assertNotEmpty($stepKeys);
        $websiteProgressPercent = (int) round(100 / count($stepKeys));

        $website->update([
            'metadata' => [
                'provisioning_started_at' => now()->subMinutes(40)->toISOString(),
                'provisioning_completed_at' => now()->subMinutes(1)->toISOString(),
                'provisioning_steps' => [
                    $stepKeys[0] => [
                        'status' => 'done',
                        'message' => 'Initial provisioning completed.',
                        'started_at' => now()->subMinutes(39)->toISOString(),
                        'completed_at' => now()->subMinutes(38)->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('platform.websites.show', $website))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/websites/show')
                ->where('website.id', $website->id)
                ->where('website.primary_hostname', 'demo.example.com')
                ->where('website.alternate_hostname', null)
                ->where('website.supports_www_feature', false)
                ->has('provisioningSteps')
                ->where('provisioningSteps.0.key', $stepKeys[0])
                ->where('provisioningSteps.0.status', 'done')
                ->where('provisioningSteps.0.message', 'Initial provisioning completed.')
                ->where('provisioningSteps.0.started_at', app_date_time_format($website->getMetadata('provisioning_steps.'.$stepKeys[0].'.started_at'), 'datetime'))
                ->where('provisioningSteps.0.completed_at', app_date_time_format($website->getMetadata('provisioning_steps.'.$stepKeys[0].'.completed_at'), 'datetime'))
                ->where('provisioningRun.started_at', app_date_time_format($website->getMetadata('provisioning_started_at'), 'datetime'))
                ->where('provisioningRun.completed_at', app_date_time_format($website->getMetadata('provisioning_completed_at'), 'datetime'))
                ->where('canManageLaravelLog', true)
                ->where('canManageWebsiteEnv', true)
                ->has('activities'));

        $this->actingAs($this->admin)
            ->get(route('platform.websites.provisioning-status', ['website' => $website]))
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'percentage' => $websiteProgressPercent,
                'provisioning_run' => [
                    'started_at' => app_date_time_format($website->getMetadata('provisioning_started_at'), 'datetime'),
                    'completed_at' => app_date_time_format($website->getMetadata('provisioning_completed_at'), 'datetime'),
                ],
                'current_status' => $website->status instanceof WebsiteStatus ? $website->status->value : $website->status,
            ])
            ->assertJsonPath('provisioning_steps.0.key', $stepKeys[0])
            ->assertJsonPath('provisioning_steps.0.status', 'done')
            ->assertJsonPath('provisioning_steps.0.message', 'Initial provisioning completed.')
            ->assertJsonPath('provisioning_steps.0.started_at', app_date_time_format($website->getMetadata('provisioning_steps.'.$stepKeys[0].'.started_at'), 'datetime'))
            ->assertJsonPath('provisioning_steps.0.completed_at', app_date_time_format($website->getMetadata('provisioning_steps.'.$stepKeys[0].'.completed_at'), 'datetime'));

        $website->update([
            'domain' => 'astero.in',
            'metadata' => [
                'provisioning' => ['is_www' => true],
                'primary_hostname_sync' => [
                    'status' => 'failed',
                    'target' => 'www',
                    'message' => 'Bunny API timeout',
                    'failed_at' => now()->subMinute()->toISOString(),
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->get(route('platform.websites.show', $website))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/websites/show')
                ->where('website.id', $website->id)
                ->where('website.primary_hostname', 'www.astero.in')
                ->where('website.alternate_hostname', 'astero.in')
                ->where('website.supports_www_feature', true)
                ->where('website.primary_hostname_sync.status', 'failed')
                ->where('website.primary_hostname_sync.target', 'www')
                ->where('website.primary_hostname_sync.message', 'Bunny API timeout'));

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

    public function test_website_provisioning_status_includes_dns_instructions_for_verify_dns_step(): void
    {
        $agency = $this->createAgency();
        $serverProvider = $this->createProvider(Provider::TYPE_SERVER, 'Server Provider');
        $server = $this->createServer($serverProvider, $agency);
        $dnsProvider = $this->createProvider(Provider::TYPE_DNS, 'DNS Provider');
        $cdnProvider = $this->createProvider(Provider::TYPE_CDN, 'CDN Provider');
        $website = $this->createWebsite($agency, $server, $dnsProvider, $cdnProvider);

        $website->update([
            'domain' => 'astero.in',
            'status' => WebsiteStatus::WaitingForDns,
            'metadata' => [
                'provisioning_steps' => [
                    'verify_dns' => [
                        'status' => 'waiting',
                        'message' => 'Waiting for customer to add DNS records.',
                    ],
                ],
            ],
        ]);

        $domain = $this->createDomain($agency);
        $domain->name = 'astero.in';
        $domain->setMetadata('dns_instructions', [
            'mode' => 'external',
            'records' => [
                ['type' => 'CNAME', 'name' => 'astero.in', 'value' => 'ws-demo.b-cdn.net'],
                ['type' => 'CNAME', 'name' => 'www', 'value' => 'ws-demo.b-cdn.net'],
                ['type' => 'CNAME', 'name' => '_acme-challenge', 'value' => '_acme-challenge.astero.in.acme-challenge.in'],
            ],
        ]);
        $domain->save();

        $website->domain_id = $domain->id;
        $website->save();

        $response = $this->actingAs($this->admin)
            ->get(route('platform.websites.provisioning-status', ['website' => $website]))
            ->assertOk();

        $verifyDnsStep = collect($response->json('provisioning_steps'))->firstWhere('key', 'verify_dns');

        $this->assertIsArray($verifyDnsStep);
        $this->assertSame('waiting', $verifyDnsStep['status']);
        $this->assertSame('external', data_get($verifyDnsStep, 'dns_instructions.mode'));
        $this->assertSame('astero.in', data_get($verifyDnsStep, 'dns_instructions.domain'));
        $this->assertSame('@', data_get($verifyDnsStep, 'dns_instructions.records.0.host_label'));
        $this->assertSame('astero.in', data_get($verifyDnsStep, 'dns_instructions.records.0.fqdn'));
        $this->assertSame('www', data_get($verifyDnsStep, 'dns_instructions.records.1.host_label'));
        $this->assertSame('www.astero.in', data_get($verifyDnsStep, 'dns_instructions.records.1.fqdn'));
        $this->assertSame('_acme-challenge', data_get($verifyDnsStep, 'dns_instructions.records.2.host_label'));
        $this->assertFalse((bool) data_get($verifyDnsStep, 'dns_validation.confirmed_by_user'));
        $this->assertSame(0, data_get($verifyDnsStep, 'dns_validation.check_count'));
        $this->assertSame(route('platform.websites.confirm-dns', ['website' => $website]), data_get($verifyDnsStep, 'dns_validation.confirm_url'));
        $this->assertSame(route('platform.websites.stop-dns-validation', ['website' => $website]), data_get($verifyDnsStep, 'dns_validation.stop_url'));
    }

    public function test_platform_show_pages_include_shared_ssl_usage_details(): void
    {
        $agency = $this->createAgency();
        $serverProvider = $this->createProvider(Provider::TYPE_SERVER, 'Server Provider');
        $server = $this->createServer($serverProvider, $agency);
        $dnsProvider = $this->createProvider(Provider::TYPE_DNS, 'DNS Provider');
        $cdnProvider = $this->createProvider(Provider::TYPE_CDN, 'CDN Provider');
        $domain = $this->createDomain($agency);
        $domain->update(['name' => 'astero.in']);

        $rootWebsite = $this->createWebsite($agency, $server, $dnsProvider, $cdnProvider);
        $rootWebsite->update([
            'name' => 'Astero Root',
            'domain' => 'astero.in',
            'domain_id' => $domain->id,
        ]);

        $subWebsiteOne = Website::query()->create([
            'uid' => 'ws-web2',
            'name' => 'Web 2',
            'domain' => 'web2.astero.in',
            'domain_id' => $domain->id,
            'server_id' => $server->id,
            'agency_id' => $agency->id,
            'status' => WebsiteStatus::Active,
            'type' => 'paid',
            'plan_tier' => 'basic',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        $subWebsiteOne->assignProvider($dnsProvider->id, true);
        $subWebsiteOne->assignProvider($cdnProvider->id, true);

        $subWebsiteTwo = Website::query()->create([
            'uid' => 'ws-web3',
            'name' => 'Web 3',
            'domain' => 'web3.astero.in',
            'domain_id' => $domain->id,
            'server_id' => $server->id,
            'agency_id' => $agency->id,
            'status' => WebsiteStatus::Suspended,
            'type' => 'paid',
            'plan_tier' => 'basic',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        $subWebsiteTwo->assignProvider($dnsProvider->id, true);
        $subWebsiteTwo->assignProvider($cdnProvider->id, true);

        $certificate = $domain->secrets()->create([
            'key' => 'domain_ssl_certificate',
            'username' => 'wildcard.astero.in',
            'type' => 'ssl_certificate',
            'value' => encrypt('private-key'),
            'metadata' => [
                'certificate' => 'cert-pem',
                'certificate_authority' => 'letsencrypt',
                'domains' => ['astero.in', '*.astero.in'],
                'is_wildcard' => true,
            ],
            'is_active' => true,
            'expires_at' => now()->addDays(90),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $rootWebsite->update(['ssl_secret_id' => $certificate->id]);
        $subWebsiteOne->update(['ssl_secret_id' => $certificate->id]);
        $subWebsiteTwo->update(['ssl_secret_id' => $certificate->id]);

        $this->actingAs($this->admin)
            ->get(route('platform.websites.show', $rootWebsite))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/websites/show')
                ->where('website.ssl_summary.certificate_name', 'wildcard.astero.in')
                ->where('website.ssl_summary.domain_name', 'astero.in')
                ->where('website.ssl_summary.websites_count', 3)
                ->has('website.ssl_summary.websites', 3)
                ->where('website.ssl_summary.websites.0.domain', 'astero.in')
                ->where('website.ssl_summary.websites.1.domain', 'web2.astero.in')
                ->where('website.ssl_summary.websites.2.domain', 'web3.astero.in'));

        $this->actingAs($this->admin)
            ->get(route('platform.domains.show', $domain))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('platform/domains/show')
                ->where('domain.websites_count', 3)
                ->where('domain.latest_certificate_websites_count', 3)
                ->has('websites', 3)
                ->where('websites.0.domain', 'astero.in')
                ->where('websites.0.uses_latest_ssl', true)
                ->where('sslCertificates.0.websites_count', 3));
    }

    public function test_platform_website_dns_validation_actions_update_waiting_dns_state(): void
    {
        $agency = $this->createAgency();
        $serverProvider = $this->createProvider(Provider::TYPE_SERVER, 'Server Provider');
        $server = $this->createServer($serverProvider, $agency);
        $dnsProvider = $this->createProvider(Provider::TYPE_DNS, 'DNS Provider');
        $cdnProvider = $this->createProvider(Provider::TYPE_CDN, 'CDN Provider');
        $website = $this->createWebsite($agency, $server, $dnsProvider, $cdnProvider);

        $website->update([
            'domain' => 'astero.in',
            'status' => WebsiteStatus::WaitingForDns,
            'metadata' => [
                'provisioning_steps' => [
                    'verify_dns' => [
                        'status' => 'waiting',
                        'message' => 'Waiting for customer to add DNS records.',
                    ],
                ],
            ],
        ]);

        $domain = $this->createDomain($agency);
        $domain->name = 'astero.in';
        $domain->setMetadata('dns_instructions', [
            'mode' => 'external',
            'records' => [
                ['type' => 'CNAME', 'name' => 'astero.in', 'value' => 'ws-demo.b-cdn.net'],
                ['type' => 'CNAME', 'name' => 'www', 'value' => 'ws-demo.b-cdn.net'],
                ['type' => 'CNAME', 'name' => '_acme-challenge', 'value' => '_acme-challenge.astero.in.acme-challenge.in'],
            ],
        ]);
        $domain->save();

        $website->domain_id = $domain->id;
        $website->save();

        $this->actingAs($this->admin)
            ->post(route('platform.websites.confirm-dns', ['website' => $website]))
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'DNS validation started. Verification checks will begin shortly.',
            ]);

        $website->refresh();

        $this->assertTrue((bool) $website->getMetadata('dns_confirmed_by_user'));
        $this->assertNotNull($website->getMetadata('dns_confirmed_at'));
        $this->assertSame(0, $website->getMetadata('dns_check_count'));
        $this->assertSame(
            'User confirmed DNS update. Verification checks starting.',
            $website->getMetadata('provisioning_steps.verify_dns.message')
        );

        $this->actingAs($this->admin)
            ->post(route('platform.websites.stop-dns-validation', ['website' => $website]))
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'DNS validation stopped. Automatic checks are paused until you start validation again.',
            ]);

        $website->refresh();

        $this->assertFalse((bool) $website->getMetadata('dns_confirmed_by_user'));
        $this->assertNull($website->getMetadata('dns_confirmed_at'));
        $this->assertSame(0, $website->getMetadata('dns_check_count'));
        $this->assertNull($website->getMetadata('dns_check_result'));
        $this->assertSame(
            'Waiting for customer DNS records to propagate.',
            $website->getMetadata('provisioning_steps.verify_dns.message')
        );
    }

    public function test_platform_website_primary_host_action_uses_provisioning_service_response(): void
    {
        Queue::fake();

        $agency = $this->createAgency();
        $serverProvider = $this->createProvider(Provider::TYPE_SERVER, 'Server Provider');
        $server = $this->createServer($serverProvider, $agency);
        $dnsProvider = $this->createProvider(Provider::TYPE_DNS, 'DNS Provider');
        $cdnProvider = $this->createProvider(Provider::TYPE_CDN, 'CDN Provider');
        $website = $this->createWebsite($agency, $server, $dnsProvider, $cdnProvider);
        $website->update(['domain' => 'astero.in']);

        $this->actingAs($this->admin)
            ->postJson(route('platform.websites.update-primary-host', [
                'website' => $website,
                'hostnameType' => 'www',
            ]))
            ->assertStatus(202)
            ->assertJson([
                'status' => 'info',
                'message' => 'Primary hostname update queued. Refresh in a few moments to see the final state.',
            ]);

        Queue::assertPushed(WebsiteUpdatePrimaryHostname::class, function (WebsiteUpdatePrimaryHostname $job) use ($website): bool {
            return $job->websiteId === $website->id
                && $job->useWww === true
                && $job->requestedByUserId === $this->admin->id;
        });
    }

    public function test_server_update_persists_restored_edit_fields(): void
    {
        $agency = $this->createAgency();
        $provider = $this->createProvider(Provider::TYPE_SERVER, 'Server Provider');
        $server = $this->createServer($provider, $agency);

        $payload = [
            'creation_mode' => 'manual',
            'name' => 'Edited Server',
            'ip' => '203.0.113.44',
            'fqdn' => 'edited.example.com',
            'type' => 'production',
            'provider_id' => (string) $provider->id,
            'monitor' => true,
            'status' => 'active',
            'location_country_code' => 'IN',
            'location_country' => 'India',
            'location_city_code' => 'BOM',
            'location_city' => 'Mumbai',
            'port' => '8443',
            'access_key_id' => 'updated-key',
            'access_key_secret' => '',
            'release_api_key' => 'release-key-123',
            'max_domains' => '250',
            'ssh_port' => '22',
            'ssh_user' => 'root',
            'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITestPersistedKey astero@test',
            'ssh_private_key' => '',
            'server_cpu' => 'Intel Xeon',
            'server_ccore' => '4',
            'server_ram' => '8192',
            'server_storage' => '160',
            'server_os' => 'Ubuntu 24.04',
            'astero_version' => '1.0.46',
            'hestia_version' => '1.9.4',
        ];

        $this->actingAs($this->admin)
            ->put(route('platform.servers.update', $server), $payload)
            ->assertRedirect();

        $server = $server->fresh();

        $this->assertSame('Edited Server', $server->name);
        $this->assertSame('203.0.113.44', $server->ip);
        $this->assertSame('IN', $server->location_country_code);
        $this->assertSame('Mumbai', $server->location_city);
        $this->assertSame('Intel Xeon', $server->server_cpu);
        $this->assertSame('4', (string) $server->server_ccore);
        $this->assertSame('8192', (string) $server->server_ram);
        $this->assertSame('160', (string) $server->server_storage);
        $this->assertSame('Ubuntu 24.04', $server->server_os);
        $this->assertSame('1.0.46', $server->astero_version);
        $this->assertSame('1.9.4', $server->hestia_version);
        $this->assertSame('release-key-123', $server->release_api_key);
        $this->assertSame(250, $server->max_domains);
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

    private function createTld(): Tld
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

    private function assertPlatformStandardIndexContract(
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

    private function jsonPayloadSize(mixed $value): int
    {
        return strlen(json_encode($value, JSON_THROW_ON_ERROR));
    }
}
