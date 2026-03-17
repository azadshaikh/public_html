<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_user_role_and_permission_relations_use_the_application_models(): void
    {
        $user = new User;

        $this->assertSame(Role::class, $user->roles()->getRelated()::class);
        $this->assertSame(Permission::class, $user->permissions()->getRelated()::class);
    }

    public function test_roles_and_permissions_seeder_creates_the_initial_foundation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseHas('roles', [
            'name' => 'super_user',
            'display_name' => 'Super User',
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'view_users',
            'display_name' => 'View Users',
            'group' => 'users',
            'module_slug' => 'application',
        ]);

        $this->assertDatabaseMissing('permissions', [
            'name' => 'manage_modules',
            'guard_name' => 'web',
        ]);

        $administrator = Role::findByName('administrator');

        $this->assertTrue($administrator->hasPermissionTo('view_users', 'web'));
        $this->assertTrue($administrator->hasPermissionTo('edit_roles', 'web'));
        $this->assertTrue($administrator->hasPermissionTo('add_users', 'web'));
        $this->assertTrue($administrator->hasPermissionTo('delete_users', 'web'));
    }

    public function test_super_user_role_bypasses_permission_checks(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('super_user');

        $this->assertTrue($user->fresh()->can('view_users'));
        $this->assertTrue($user->fresh()->can('delete_roles'));
    }

    public function test_permission_middleware_alias_blocks_unauthorized_users_and_allows_authorized_users(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $unauthorizedUser = User::factory()->create();

        $this->actingAs($unauthorizedUser)
            ->get('/_test/authorization/view-users')
            ->assertForbidden();

        $authorizedUser = User::factory()->create();
        $authorizedUser->assignRole('administrator');

        $this->actingAs($authorizedUser)
            ->get('/_test/authorization/view-users')
            ->assertOk();
    }

    public function test_super_user_only_middleware_blocks_non_super_users_and_allows_super_users(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrator = User::factory()->create();
        $administrator->assignRole('administrator');

        $this->actingAs($administrator)
            ->get('/_test/authorization/super-user-only')
            ->assertForbidden();

        $superUser = User::factory()->create();
        $superUser->assignRole('super_user');

        $this->actingAs($superUser)
            ->get('/_test/authorization/super-user-only')
            ->assertOk();
    }

    public function test_role_middleware_alias_allows_matching_roles_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');

        $this->actingAs($staffUser)
            ->get('/_test/authorization/admin-only')
            ->assertForbidden();

        $administrator = User::factory()->create();
        $administrator->assignRole('administrator');

        $this->actingAs($administrator)
            ->get('/_test/authorization/admin-only')
            ->assertOk();
    }

    public function test_local_user_seeder_assigns_the_super_user_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(UserSeeder::class);

        $user = User::query()->where('email', 'su@astero.in')->firstOrFail();

        $this->assertTrue($user->isSuperUser());
    }

    public function test_user_seeder_only_creates_the_super_user_outside_local_environment(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(UserSeeder::class);

        $users = User::query()->with('roles')->orderBy('email')->get();

        $this->assertCount(1, $users);
        $this->assertSame('su@astero.in', $users->first()?->email);
        $this->assertTrue($users->first()?->isSuperUser() ?? false);
    }
}
