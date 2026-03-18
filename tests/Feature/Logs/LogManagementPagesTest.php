<?php

declare(strict_types=1);

namespace Tests\Feature\Logs;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LogManagementPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superUser = User::factory()->create([
            'first_name' => 'Logs',
            'last_name' => 'Super',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->superUser->assignRole(Role::findByName('super_user', 'web'));
    }

    public function test_super_user_can_view_activity_log_index_with_scaffold_config(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.logs.activity-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('logs/activity-logs/index')
                ->has('config.columns', 7)
                ->where('config.columns.2.width', '300px')
                ->has('logs.data')
                ->has('statistics')
                ->has('filterOptions')
                ->has('filters')
            );
    }

    public function test_super_user_can_view_login_attempt_index_with_scaffold_config(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.logs.login-attempts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('logs/login-attempts/index')
                ->has('config.columns', 9)
                ->where('config.columns.2.width', '200px')
                ->has('loginAttempts.data')
                ->has('statistics')
                ->has('filters')
            );
    }

    public function test_super_user_can_view_not_found_log_index_with_scaffold_config(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.logs.not-found-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('logs/not-found-logs/index')
                ->has('config.columns', 9)
                ->where('config.columns.2.width', '300px')
                ->has('notFoundLogs.data')
                ->has('statistics')
                ->has('filters')
            );
    }
}
