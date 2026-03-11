<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_are_redirected_from_the_roles_index(): void
    {
        $this->get(route('roles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_role_permissions_cannot_view_the_roles_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_administrators_can_view_the_roles_index(): void
    {
        $user = $this->administrator();

        $this->actingAs($user)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/index')
                ->where('stats.total', 6)
                ->where('stats.system', 6)
                ->where('stats.custom', 0)
                ->has('roles', 6)
                ->where('roles.0.is_system', true));
    }

    public function test_administrators_can_create_a_custom_role_with_permissions(): void
    {
        $user = $this->administrator();
        $permissions = Permission::query()
            ->whereIn('name', ['view_roles', 'edit_roles'])
            ->pluck('id')
            ->all();

        $this->actingAs($user)
            ->post(route('roles.store'), [
                'name' => 'content_manager',
                'display_name' => 'Content Manager',
                'description' => 'Manages editorial operations.',
                'permissions' => $permissions,
            ])
            ->assertRedirect(route('roles.index'));

        $role = Role::query()->where('name', 'content_manager')->firstOrFail();

        $this->assertSame('Content Manager', $role->display_name);
        $this->assertFalse($role->is_system);
        $this->assertTrue($role->hasPermissionTo('view_roles', 'web'));
        $this->assertTrue($role->hasPermissionTo('edit_roles', 'web'));
    }

    public function test_administrators_can_update_a_custom_role_and_resync_permissions(): void
    {
        $user = $this->administrator();
        $role = Role::query()->create([
            'name' => 'support_agent',
            'guard_name' => 'web',
            'display_name' => 'Support Agent',
            'description' => 'Handles customer support.',
            'is_system' => false,
        ]);
        $role->syncPermissions([
            Permission::findByName('view_dashboard', 'web'),
        ]);

        $permissionIds = Permission::query()
            ->whereIn('name', ['view_roles', 'delete_roles'])
            ->pluck('id')
            ->all();

        $this->actingAs($user)
            ->put(route('roles.update', $role), [
                'name' => 'support_lead',
                'display_name' => 'Support Lead',
                'description' => 'Leads escalations and support administration.',
                'permissions' => $permissionIds,
            ])
            ->assertRedirect(route('roles.index'));

        $role->refresh();

        $this->assertSame('support_lead', $role->name);
        $this->assertSame('Support Lead', $role->display_name);
        $this->assertTrue($role->hasPermissionTo('view_roles', 'web'));
        $this->assertTrue($role->hasPermissionTo('delete_roles', 'web'));
        $this->assertFalse($role->hasPermissionTo('view_dashboard', 'web'));
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $user = $this->administrator();
        $role = Role::query()->where('name', Role::SUPER_USER)->firstOrFail();

        $this->actingAs($user)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => Role::SUPER_USER,
        ]);
    }

    public function test_custom_roles_with_assigned_users_cannot_be_deleted(): void
    {
        $user = $this->administrator();
        $role = Role::query()->create([
            'name' => 'qa_reviewer',
            'guard_name' => 'web',
            'display_name' => 'QA Reviewer',
            'description' => 'Reviews releases before rollout.',
            'is_system' => false,
        ]);

        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($role);

        $this->actingAs($user)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'qa_reviewer',
        ]);
    }

    public function test_custom_roles_without_assigned_users_can_be_deleted(): void
    {
        $user = $this->administrator();
        $role = Role::query()->create([
            'name' => 'temporary_role',
            'guard_name' => 'web',
            'display_name' => 'Temporary Role',
            'description' => 'Used for temporary migration checks.',
            'is_system' => false,
        ]);

        $this->actingAs($user)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    protected function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('administrator', 'web'));

        return $user;
    }
}
