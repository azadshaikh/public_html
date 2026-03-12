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
                ->where('filters.sort', 'role')
                ->where('filters.direction', 'asc')
                ->where('filters.per_page', 10)
                ->where('filters.view', 'table')
                ->where('roles.current_page', 1)
                ->where('roles.total', 6)
                ->has('roles.data', 6)
                ->where('roles.data.0.is_system', true));
    }

    public function test_administrators_can_sort_paginate_and_change_role_views(): void
    {
        $user = $this->administrator();

        foreach (range(1, 11) as $index) {
            Role::query()->create([
                'name' => sprintf('custom_role_%d', $index),
                'guard_name' => 'web',
                'display_name' => sprintf('Custom Role %d', $index),
                'description' => sprintf('Custom role description %d', $index),
                'is_system' => false,
            ]);
        }

        $this->actingAs($user)
            ->get(route('roles.index', [
                'scope' => 'custom',
                'sort' => 'role',
                'direction' => 'desc',
                'per_page' => 10,
                'view' => 'cards',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/index')
                ->where('filters.scope', 'custom')
                ->where('filters.sort', 'role')
                ->where('filters.direction', 'desc')
                ->where('filters.per_page', 10)
                ->where('filters.view', 'cards')
                ->where('roles.current_page', 1)
                ->where('roles.last_page', 2)
                ->where('roles.total', 11)
                ->has('roles.data', 10)
                ->where('roles.data.0.name', 'custom_role_9'));
    }

    public function test_administrators_can_bulk_delete_eligible_roles(): void
    {
        $user = $this->administrator();

        $deletableRole = Role::query()->create([
            'name' => 'bulk_delete_role',
            'guard_name' => 'web',
            'display_name' => 'Bulk Delete Role',
            'description' => 'Can be removed in bulk.',
            'is_system' => false,
        ]);

        $assignedRole = Role::query()->create([
            'name' => 'bulk_assigned_role',
            'guard_name' => 'web',
            'display_name' => 'Bulk Assigned Role',
            'description' => 'Should remain assigned.',
            'is_system' => false,
        ]);

        $assignedUser = User::factory()->create();
        $assignedUser->assignRole($assignedRole);

        $systemRole = Role::query()->where('name', Role::SUPER_USER)->firstOrFail();

        $this->actingAs($user)
            ->delete(route('roles.bulk-destroy'), [
                'role_ids' => [$deletableRole->id, $assignedRole->id, $systemRole->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('roles', [
            'id' => $deletableRole->id,
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $assignedRole->id,
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $systemRole->id,
        ]);
    }

    public function test_users_without_delete_permissions_cannot_bulk_delete_roles(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create([
            'name' => 'forbidden_bulk_role',
            'guard_name' => 'web',
            'display_name' => 'Forbidden Bulk Role',
            'description' => 'Used for authorization testing.',
            'is_system' => false,
        ]);

        $this->actingAs($user)
            ->delete(route('roles.bulk-destroy'), [
                'role_ids' => [$role->id],
            ])
            ->assertForbidden();
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
