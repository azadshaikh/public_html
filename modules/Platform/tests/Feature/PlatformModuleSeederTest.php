<?php

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
use Illuminate\Support\Facades\Schema;
use Modules\Platform\Database\Seeders\DatabaseSeeder;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Tld;
use Tests\TestCase;

class PlatformModuleSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePlatformSeederEnvironment();
    }

    public function test_platform_module_database_seeder_is_discoverable(): void
    {
        $this->assertTrue(class_exists(DatabaseSeeder::class));
    }

    public function test_platform_module_database_seeder_is_idempotent_and_seeds_core_records(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'first_name' => 'Platform',
            'last_name' => 'Seeder',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $this->seed(DatabaseSeeder::class);

        $firstProviderCount = Provider::query()->count();
        $firstTldCount = Tld::query()->count();
        $firstServerCount = Server::query()->count();
        $firstAgencyCount = Agency::query()->count();
        $platformPermissionCount = Permission::query()->where('module_slug', 'platform')->count();

        $this->assertGreaterThan(0, $firstProviderCount);
        $this->assertGreaterThan(0, $firstTldCount);
        $this->assertGreaterThan(0, $firstServerCount);
        $this->assertGreaterThan(0, $firstAgencyCount);
        $this->assertGreaterThan(0, $platformPermissionCount);

        $administratorRole = Role::query()->where('name', 'administrator')->where('guard_name', 'web')->first();
        $seededServer = Server::query()->orderBy('id')->first();
        $seededAgency = Agency::query()->where('email', 'platform-demo-agency@example.test')->first();
        $legacyAgency = Agency::query()->where('email', 'contact@breederspot.com')->first();
        $legacyServer = Server::query()->where('ip', '192.168.0.123')->first();
        $secondaryLegacyServer = Server::query()->where('ip', '192.168.0.150')->first();

        $this->assertInstanceOf(Role::class, $administratorRole);
        $this->assertInstanceOf(Server::class, $seededServer);
        $this->assertInstanceOf(Agency::class, $seededAgency);
        $this->assertInstanceOf(Agency::class, $legacyAgency);
        $this->assertInstanceOf(Server::class, $legacyServer);
        $this->assertInstanceOf(Server::class, $secondaryLegacyServer);
        $this->assertTrue($administratorRole->permissions()->where('name', 'view_websites')->exists());
        $this->assertSame('Platform Demo Server One', $seededServer->name);
        $this->assertStringStartsWith('SVR', (string) $seededServer->uid);
        $this->assertSame('Platform Demo Agency', $seededAgency->name);
        $this->assertStringStartsWith('AGY', (string) $seededAgency->uid);
        $this->assertSame('Dev One - Local', $legacyServer->name);
        $this->assertSame('Dev Two - Local', $secondaryLegacyServer->name);
        $this->assertSame('Breeder Spot LLC', $legacyAgency->name);
        $this->assertSame('BreederSpot', $legacyAgency->metadata['branding_name'] ?? null);
        $this->assertSame(
            'UaXom83IHmbr85LQ1DoRRNGH6Lq4Zj1sfMzSp1g3wjHxWTeJlXe3zS9IwaEnCYaQ',
            $legacyAgency->plain_secret_key,
        );
        $this->assertTrue($legacyAgency->servers()->whereKey($legacyServer->getKey())->exists());
        $this->assertSame('US', $legacyAgency->getPrimaryAddress()?->country_code);
        $this->assertSame('work', $legacyAgency->getPrimaryAddress()?->type);
        $this->assertSame(
            'rJ7vQ9nM2xK8pW4tYcL6sF3uH1dN5bZ8eA0kV7qT2mP9wX4gC6yR1uJ8nD3fS5h',
            $legacyServer->getSecretValue('release_api_key'),
        );
        $this->assertTrue(
            Provider::query()->where('type', Provider::TYPE_DNS)->where('vendor', 'custom')->exists(),
        );
        $this->assertTrue(
            Provider::query()->where('type', Provider::TYPE_CDN)->where('vendor', 'keycdn')->exists(),
        );
        $this->assertTrue(
            Provider::query()->where('type', Provider::TYPE_SERVER)->where('vendor', 'gcp')->exists(),
        );
        $this->assertTrue(
            Provider::query()->where('type', Provider::TYPE_DOMAIN_REGISTRAR)->where('vendor', 'ionos')->exists(),
        );
        $this->assertTrue(Tld::query()->where('tld', '.com')->exists());

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($firstProviderCount, Provider::query()->count());
        $this->assertSame($firstTldCount, Tld::query()->count());
        $this->assertSame($firstServerCount, Server::query()->count());
        $this->assertSame($firstAgencyCount, Agency::query()->count());
        $this->assertSame($platformPermissionCount, Permission::query()->where('module_slug', 'platform')->count());
    }

    public function test_platform_tlds_table_includes_audit_columns_required_by_the_model(): void
    {
        $this->assertTrue(Schema::hasColumns('platform_tlds', [
            'created_by',
            'updated_by',
            'deleted_by',
        ]));
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->ensurePlatformSeederEnvironment();
    }

    private function ensurePlatformSeederEnvironment(): void
    {
        /** @var ModuleManager $moduleManager */
        $moduleManager = app(ModuleManager::class);

        ModuleAutoloader::register($moduleManager->all()->all());

        if (! Schema::hasTable('platform_providers')) {
            Artisan::call('migrate', [
                '--path' => base_path('modules/Platform/database/migrations'),
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }
}
