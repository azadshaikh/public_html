<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_are_redirected_from_the_roles_index(): void
    {
        $this->get(route('app.roles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_role_permissions_cannot_view_the_roles_index(): void
    {
        $user = User::factory()->create([
            'status' => Status::ACTIVE,
            'first_name' => 'Viewer',
            'last_name' => 'User',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('app.roles.index'))
            ->assertForbidden();
    }

    public function test_administrators_can_view_the_roles_index(): void
    {
        $user = $this->administrator();

        $this->actingAs($user)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/index')
                ->has('config.columns', 7)
                ->where('config.columns.1.width', '250px')
                ->has('statistics')
                ->where('filters.sort', 'display_name')
                ->where('filters.direction', 'asc')
                ->where('filters.view', 'table')
                ->has('roles.data'));
    }

    public function test_administrators_can_view_the_role_create_screen(): void
    {
        $user = $this->administrator();

        $this->actingAs($user)
            ->get(route('app.roles.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/create')
                ->where('initialValues.status', Status::ACTIVE->value)
                ->has('statusOptions')
                ->has('permissionGroups'));
    }

    public function test_administrators_can_create_a_custom_role_with_permissions(): void
    {
        $user = $this->administrator();
        $permissions = Permission::query()
            ->whereIn('name', ['view_roles', 'edit_roles'])
            ->pluck('id')
            ->all();

        $this->actingAs($user)
            ->post(route('app.roles.store'), [
                'name' => 'content_manager',
                'display_name' => 'Content Manager',
                'status' => Status::INACTIVE->value,
                'permissions' => $permissions,
            ])
            ->assertRedirect();

        $role = Role::query()->where('name', 'content_manager')->firstOrFail();

        $this->assertSame('Content Manager', $role->display_name);
        $this->assertSame(Status::INACTIVE, $role->status);
        $this->assertTrue($role->hasPermissionTo('view_roles', 'web'));
        $this->assertTrue($role->hasPermissionTo('edit_roles', 'web'));
    }

    public function test_administrators_can_update_a_custom_role_and_keep_its_status(): void
    {
        $user = $this->administrator();
        $role = Role::query()->create([
            'name' => 'support_agent',
            'guard_name' => 'web',
            'display_name' => 'Support Agent',
            'status' => Status::INACTIVE,
        ]);
        $role->syncPermissions([
            Permission::findByName('view_dashboard', 'web'),
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', ['view_roles', 'delete_roles'])
            ->pluck('id')
            ->all();

        $this->actingAs($user)
            ->put(route('app.roles.update', $role), [
                'name' => 'support_lead',
                'display_name' => 'Support Lead',
                'status' => Status::INACTIVE->value,
                'permissions' => $permissionIds,
            ])
            ->assertRedirect();

        $role->refresh();

        $this->assertSame('support_lead', $role->name);
        $this->assertSame('Support Lead', $role->display_name);
        $this->assertSame(Status::INACTIVE, $role->status);
        $this->assertTrue($role->hasPermissionTo('view_roles', 'web'));
        $this->assertTrue($role->hasPermissionTo('delete_roles', 'web'));
        $this->assertFalse($role->hasPermissionTo('view_dashboard', 'web'));
    }

    public function test_administrators_can_bulk_delete_eligible_roles(): void
    {
        $user = $this->administrator();

        $deletableRole = Role::query()->create([
            'name' => 'aaa_bulk_delete_role',
            'guard_name' => 'web',
            'display_name' => 'A Bulk Delete Role',
            'status' => Status::ACTIVE,
        ]);

        $assignedRole = Role::query()->create([
            'name' => 'zzz_bulk_assigned_role',
            'guard_name' => 'web',
            'display_name' => 'Z Bulk Assigned Role',
            'status' => Status::ACTIVE,
        ]);

        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($assignedRole);

        $systemRole = Role::query()->where('name', 'super_user')->firstOrFail();

        $this->actingAs($user)
            ->post(route('app.roles.bulk-action'), [
                'action' => 'delete',
                'ids' => [$deletableRole->id, $assignedRole->id, $systemRole->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Cannot delete this role because it has 1 user. Please reassign or remove the users first.');

        $this->assertDatabaseHas('roles', [
            'id' => $deletableRole->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $assignedRole->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $systemRole->id,
            'deleted_at' => null,
        ]);
    }

    protected function administrator(): User
    {
        $user = User::factory()->create([
            'status' => Status::ACTIVE,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::findByName('administrator', 'web'));

        return $user;
    }
}
