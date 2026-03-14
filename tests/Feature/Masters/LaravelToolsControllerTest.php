<?php

declare(strict_types=1);

namespace Tests\Feature\Masters;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaravelToolsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superUser = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $this->superUser->assignRole(Role::findByName('super_user', 'web'));

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $this->admin->assignRole(Role::findByName('administrator', 'web'));

        $this->regularUser = User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);

        File::deleteDirectory(storage_path('backups/env'));
    }

    public function test_guests_are_redirected_from_laravel_tools_dashboard(): void
    {
        $this->get(route('app.masters.laravel-tools.index'))
            ->assertRedirect(route('login'));
    }

    public function test_regular_users_cannot_access_laravel_tools_dashboard(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.masters.laravel-tools.index'))
            ->assertForbidden();
    }

    public function test_admins_cannot_access_laravel_tools_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.masters.laravel-tools.index'))
            ->assertForbidden();
    }

    public function test_super_user_can_view_laravel_tools_dashboard(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.laravel-tools.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/laravel-tools/index')
                ->has('stats')
                ->where('stats.environment', app()->environment())
                ->where('stats.debug_mode', (bool) config('app.debug'))
                ->missing('stats.queue_driver')
            );
    }

    public function test_super_user_can_view_env_editor_and_recent_backups(): void
    {
        File::ensureDirectoryExists(storage_path('backups/env'));
        File::put(
            storage_path('backups/env/.env.backup.2026-03-14_10-00-00'),
            "APP_NAME=Test\n",
        );

        $this->actingAs($this->superUser)
            ->get(route('app.masters.laravel-tools.env'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/laravel-tools/env')
                ->where('envContent', fn (string $content): bool => $content !== '')
                ->has('protectedKeys', 2)
                ->has('backups', 1)
            );
    }

    public function test_env_backups_endpoint_returns_json(): void
    {
        File::ensureDirectoryExists(storage_path('backups/env'));
        File::put(
            storage_path('backups/env/.env.backup.2026-03-14_11-00-00'),
            "APP_NAME=Backup\n",
        );

        $this->actingAs($this->superUser)
            ->get(route('app.masters.laravel-tools.env.backups'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'backups');
    }

    public function test_super_user_can_view_artisan_runner(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.laravel-tools.artisan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/laravel-tools/artisan')
                ->has('commands', 13)
                ->where('commands.0.name', 'astero:recache')
            );
    }

    public function test_artisan_runner_page_includes_the_csrf_meta_tag(): void
    {
        $response = $this->actingAs($this->superUser)
            ->get(route('app.masters.laravel-tools.artisan'));

        $response->assertOk();
        $response->assertSee('<meta name="csrf-token" content="', false);
        $response->assertSee(csrf_token(), false);
    }

    public function test_super_user_can_view_config_browser_with_selected_file(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.laravel-tools.config', ['file' => 'app']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/laravel-tools/config')
                ->has('configFiles')
                ->where('selectedFile', 'app')
                ->has('selectedConfig')
            );
    }

    public function test_super_user_can_view_route_list_page(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.laravel-tools.routes'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/laravel-tools/routes')
                ->has('routes.data')
                ->has('total')
                ->where('filters.sort', 'uri')
                ->where('filters.direction', 'asc')
                ->where('filters.per_page', 25)
                ->where('filters.method', 'all')
            );
    }

    public function test_super_user_can_view_php_diagnostics_page(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.laravel-tools.php'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/laravel-tools/php')
                ->has('summary')
                ->has('settingGroups')
                ->has('extensions')
                ->has('pdoDrivers')
            );
    }

    public function test_laravel_tools_queue_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('app.masters.laravel-tools.queue'));
        $this->assertFalse(Route::has('app.masters.laravel-tools.queue.data'));
        $this->assertFalse(Route::has('app.masters.laravel-tools.queue.retry'));
        $this->assertFalse(Route::has('app.masters.laravel-tools.queue.delete'));
        $this->assertFalse(Route::has('app.masters.laravel-tools.queue.purge'));
        $this->assertTrue(Route::has('app.masters.queue-monitor.index'));
    }

    public function test_laravel_tools_logs_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('app.masters.laravel-tools.logs'));
        $this->assertFalse(Route::has('app.masters.laravel-tools.logs.entries'));
        $this->assertFalse(Route::has('app.masters.laravel-tools.logs.delete'));
    }
}
