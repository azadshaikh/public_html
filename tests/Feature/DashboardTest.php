<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
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
                ->has('navigation.modules', 1)
                ->where('navigation.top.0.items.0.label', 'Dashboard')
                ->where('navigation.modules.0.items.0.label', 'Modules')
                ->where('navigation.modules.0.items.0.url', route('app.masters.modules.index'))
                ->where('navigation.top.0.items.0.active', true)
                ->where('navigation.top.0.items.0.icon', fn (string $icon): bool => str_starts_with($icon, '<svg'))
                ->has('recentUsers')
                ->has('recentActivities')
                ->where('summary.totalUsers', 1));
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
                ->has('navigation.bottom.0.items', 3)
                ->where('navigation.top.0.items.0.label', 'Dashboard')
                ->where('navigation.modules', [])
                ->where('navigation.cms', []));
    }
}
