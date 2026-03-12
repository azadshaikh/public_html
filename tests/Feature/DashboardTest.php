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
                ->has('recentUsers')
                ->has('recentActivities')
                ->where('summary.totalUsers', 1));
    }
}
