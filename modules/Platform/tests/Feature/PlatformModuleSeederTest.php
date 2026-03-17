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

        User::factory()->create([
            'first_name' => 'Platform',
            'last_name' => 'Seeder',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

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

        $this->assertInstanceOf(Role::class, $administratorRole);
        $this->assertTrue($administratorRole->permissions()->where('name', 'view_websites')->exists());
        $this->assertTrue(Server::query()->where('uid', 'SVR0001')->exists());
        $this->assertTrue(Agency::query()->where('uid', 'AGY0001')->exists());
        $this->assertTrue(Tld::query()->where('tld', '.com')->exists());

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($firstProviderCount, Provider::query()->count());
        $this->assertSame($firstTldCount, Tld::query()->count());
        $this->assertSame($firstServerCount, Server::query()->count());
        $this->assertSame($firstAgencyCount, Agency::query()->count());
        $this->assertSame($platformPermissionCount, Permission::query()->where('module_slug', 'platform')->count());
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
