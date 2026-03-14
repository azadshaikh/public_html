<?php

declare(strict_types=1);

namespace Tests\Feature\Scaffold;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScaffoldControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'status' => Status::ACTIVE,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole(Role::findByName('administrator', 'web'));
    }

    public function test_guests_are_redirected_from_scaffold_index(): void
    {
        $this->get(route('app.roles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_receive_403(): void
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

    public function test_index_renders_current_role_crud_props(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/index')
                ->has('roles.data')
                ->has('statistics')
                ->has('filters'));
    }

    public function test_create_returns_form_view_data(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/create')
                ->where('initialValues.status', Status::ACTIVE->value)
                ->has('statusOptions')
                ->has('permissionGroups'));
    }

    public function test_store_creates_resource_and_redirects(): void
    {
        $permissionIds = Permission::query()
            ->whereIn('name', ['view_roles', 'edit_roles'])
            ->pluck('id')
            ->all();

        $this->actingAs($this->admin)
            ->post(route('app.roles.store'), [
                'display_name' => 'Content Editor',
                'status' => Status::ACTIVE->value,
                'permissions' => $permissionIds,
            ])
            ->assertRedirect();

        $role = Role::query()->where('display_name', 'Content Editor')->firstOrFail();

        $this->assertSame('content_editor', $role->name);
        $this->assertTrue($role->hasPermissionTo('view_roles'));
        $this->assertTrue($role->hasPermissionTo('edit_roles'));
    }

    public function test_edit_returns_model_and_form_view_data(): void
    {
        $role = Role::query()->create([
            'name' => 'edit_test_role',
            'guard_name' => 'web',
            'display_name' => 'Edit Test Role',
            'status' => Status::INACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.roles.edit', $role))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/edit')
                ->where('role.id', $role->id)
                ->where('role.status', Status::INACTIVE->value)
                ->where('initialValues.status', Status::INACTIVE->value)
                ->has('statusOptions')
                ->has('permissionGroups'));
    }

    public function test_update_modifies_resource_and_redirects(): void
    {
        $role = Role::query()->create([
            'name' => 'update_test_role',
            'guard_name' => 'web',
            'display_name' => 'Update Test Role',
            'status' => Status::INACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->put(route('app.roles.update', $role), [
                'name' => 'updated_role',
                'display_name' => 'Updated Role',
                'status' => Status::INACTIVE->value,
            ])
            ->assertRedirect();

        $role->refresh();

        $this->assertSame('updated_role', $role->name);
        $this->assertSame('Updated Role', $role->display_name);
        $this->assertSame(Status::INACTIVE, $role->status);
    }

    public function test_destroy_restore_and_force_delete_follow_soft_delete_flow(): void
    {
        $role = Role::query()->create([
            'name' => 'temporary_role',
            'guard_name' => 'web',
            'display_name' => 'Temporary Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('app.roles.destroy', $role))
            ->assertRedirect(route('app.roles.index'));

        $this->assertSoftDeleted('roles', ['id' => $role->id]);

        $this->actingAs($this->admin)
            ->patch(route('app.roles.restore', $role))
            ->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'deleted_at' => null,
        ]);

        $role->delete();

        $this->actingAs($this->admin)
            ->delete(route('app.roles.force-delete', $role))
            ->assertRedirect(route('app.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_bulk_action_delete_and_restore_work_for_roles(): void
    {
        $role = Role::query()->create([
            'name' => 'bulk_role',
            'guard_name' => 'web',
            'display_name' => 'Bulk Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->post(route('app.roles.bulk-action'), [
                'action' => 'delete',
                'ids' => [$role->id],
            ])
            ->assertRedirect();

        $this->assertSoftDeleted('roles', ['id' => $role->id]);

        $this->actingAs($this->admin)
            ->post(route('app.roles.bulk-action'), [
                'action' => 'restore',
                'ids' => [$role->id],
                'status' => 'trash',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'deleted_at' => null,
        ]);
    }

    public function test_bulk_delete_protects_super_user_role(): void
    {
        $superUserRole = Role::query()->where('name', 'super_user')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('app.roles.bulk-action'), [
                'action' => 'delete',
                'ids' => [$superUserRole->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', [
            'id' => $superUserRole->id,
            'deleted_at' => null,
        ]);
    }
}
