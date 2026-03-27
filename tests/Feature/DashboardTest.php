<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Composer\InstalledVersions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_without_dashboard_permission_cannot_visit_the_dashboard(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Taylor',
            'last_name' => 'Viewer',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_administrators_can_visit_the_dashboard_inertia_page(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('administrator', 'web'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('dashboard')
                ->has('summary')
                ->has('navigation.top', 1)
                ->has('navigation.cms', 1)
                ->has('navigation.modules', 1)
                ->where('navigation.top.0.items.0.label', 'Dashboard')
                ->where('navigation.cms.0.label', 'Manage')
                ->where('navigation.cms.0.items.0.label', 'Users')
                ->where('navigation.cms.0.items.0.url', route('app.users.index'))
                ->where('navigation.modules.0.label', 'Todos')
                ->where('navigation.modules.0.items.0.label', 'Tasks')
                ->where('navigation.modules.0.items.0.url', route('app.todos.index'))
                ->where('navigation.top.0.items.0.active', true)
                ->where('navigation.top.0.items.0.icon', fn (string $icon): bool => str_starts_with($icon, '<svg'))
                ->where('navigation.cms.0.items.0.icon', fn (string $icon): bool => str_starts_with($icon, '<svg'))
                ->where('navigation.modules.0.items.0.icon', fn (string $icon): bool => str_starts_with($icon, '<svg'))
                ->has('recentUsers')
                ->has('recentActivities')
                ->where('summary.totalUsers', 1));
    }

    public function test_dashboard_includes_shared_app_and_branding_details(): void
    {
        config()->set('app.name', 'Control Center');
        config()->set('astero.branding', [
            'name' => 'Astero',
            'website' => 'https://astero.test',
            'logo' => 'https://cdn.example.test/logo.svg',
            'icon' => 'https://cdn.example.test/icon.svg',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('administrator', 'web'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('dashboard')
                ->where('appName', 'Control Center')
                ->where('appVersion', InstalledVersions::getRootPackage()['pretty_version'] ?? 'dev-main')
                ->has('branding', fn (Assert $branding): Assert => $branding
                    ->where('name', 'Astero')
                    ->where('website', 'https://astero.test')
                    ->where('logo', 'https://cdn.example.test/logo.svg')
                    ->where('icon', 'https://cdn.example.test/icon.svg')
                ));
    }

    public function test_dashboard_layout_includes_google_sans_font_links(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('administrator', 'web'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('https://fonts.googleapis.com', false)
            ->assertSee('https://fonts.gstatic.com', false)
            ->assertSee('https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap', false);
    }

    public function test_super_users_can_see_master_navigation_on_the_dashboard(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('super_user', 'web'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('dashboard')
                ->has('navigation.modules', 2)
                ->where('navigation.modules.1.label', 'Masters')
                ->where('navigation.modules.1.items.0.label', 'Modules')
                ->where('navigation.modules.1.items.0.url', route('app.masters.modules.index'))
                ->where('navigation.modules.1.items.6.label', 'Laravel Tools')
                ->where('navigation.modules.1.items.6.url', route('app.masters.laravel-tools.index'))
                ->where('navigation.modules.1.items.6.hard_reload', false));
    }

    public function test_dashboard_navigation_is_filtered_by_permission(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Limited',
            'last_name' => 'Viewer',
            'status' => Status::ACTIVE,
        ]);
        $user->givePermissionTo('view_dashboard');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('dashboard')
                ->has('navigation.top', 1)
                ->has('navigation.bottom', 1)
                ->has('navigation.top.0.items', 1)
                ->has('navigation.cms', 0)
                ->has('navigation.modules', 1)
                ->where('navigation.modules.0.label', 'Todos')
                ->where('navigation.modules.0.items.0.label', 'Tasks')
                ->where('navigation.modules.0.items.0.url', route('app.todos.index'))
                ->has('navigation.bottom.0.items', 4)
                ->where('navigation.top.0.items.0.label', 'Dashboard'));
    }
}
