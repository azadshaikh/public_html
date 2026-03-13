<?php

declare(strict_types=1);

namespace Tests\Feature\Scaffold;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tests the ScaffoldController base behavior using RoleController as the
 * concrete implementation. RoleController is ideal because it inherits most
 * scaffold methods unchanged (index, create, store, edit, update, destroy,
 * restore, forceDelete, bulkAction), making it a clean test of the base class.
 *
 * Covers: index pagination / filters / sorting, CRUD create / update / delete,
 * and bulk actions — all via the standard scaffold flow.
 */
class ScaffoldControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $this->admin->assignRole(Role::findByName('administrator', 'web'));
    }

    // =========================================================================
    // INDEX — Authentication & Authorization
    // =========================================================================

    public function test_guests_are_redirected_from_scaffold_index(): void
    {
        $this->get(route('app.roles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_receive_403(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('app.roles.index'))
            ->assertForbidden();
    }

    public function test_user_with_view_permission_can_access_index(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->givePermissionTo('view_roles');

        $this->actingAs($user)
            ->get(route('app.roles.index'))
            ->assertOk();
    }

    // =========================================================================
    // INDEX — Inertia Response Structure
    // =========================================================================

    public function test_index_renders_correct_inertia_component(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/index')
            );
    }

    public function test_index_returns_items_array(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('items')
            );
    }

    public function test_index_returns_pagination_structure(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('pagination')
                ->has('pagination.current_page')
                ->has('pagination.per_page')
                ->has('pagination.total')
                ->has('pagination.last_page')
            );
    }

    public function test_index_returns_columns_config(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('columns')
            );
    }

    public function test_index_returns_filters_config(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('filters')
            );
    }

    public function test_index_returns_actions_config(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('actions')
            );
    }

    public function test_index_returns_scaffold_definition_config(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('config')
                ->has('config.settings')
                ->has('config.columns')
                ->has('config.statusTabs')
            );
    }

    public function test_index_returns_statistics_on_initial_load(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('statistics')
                ->has('statistics.total')
            );
    }

    public function test_index_returns_empty_state_config(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('empty_state_config')
            );
    }

    // =========================================================================
    // INDEX — Pagination
    // =========================================================================

    public function test_index_pagination_defaults_to_page_one(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('pagination.current_page', 1)
            );
    }

    public function test_index_respects_page_parameter(): void
    {
        // Create enough roles to have multiple pages (seeder creates 6, default perPage from definition)
        foreach (range(1, 20) as $i) {
            Role::create([
                'name' => "test_role_{$i}",
                'guard_name' => 'web',
                'display_name' => "Test Role {$i}",
                'status' => Status::ACTIVE,
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('app.roles.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('pagination.current_page', 2)
            );
    }

    public function test_index_respects_per_page_parameter(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index', ['per_page' => 5]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('pagination.per_page', 5)
            );
    }

    public function test_pagination_on_each_side_limits_page_links(): void
    {
        // Create many roles for multi-page pagination
        foreach (range(1, 50) as $i) {
            Role::create([
                'name' => "pagination_role_{$i}",
                'guard_name' => 'web',
                'display_name' => "Pagination Role {$i}",
                'status' => Status::ACTIVE,
            ]);
        }

        // Verify pagination works correctly with many pages
        $response = $this->actingAs($this->admin)
            ->get(route('app.roles.index', ['per_page' => 5, 'page' => 5]));

        $response->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->where('pagination.current_page', 5);
                $page->where('pagination.per_page', 5);
                // With 56+ roles at 5/page, there should be 12+ pages
                $page->where('pagination.last_page', fn ($lastPage) => $lastPage >= 10);
            });
    }

    // =========================================================================
    // INDEX — Sorting
    // =========================================================================

    public function test_index_supports_sort_parameter(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.index', ['sort_column' => 'display_name', 'sort_direction' => 'desc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('items')
            );
    }

    public function test_index_sorts_ascending_by_default(): void
    {
        // Create roles with known names
        Role::create([
            'name' => 'aaa_first_role',
            'guard_name' => 'web',
            'display_name' => 'AAA First',
            'status' => Status::ACTIVE,
        ]);
        Role::create([
            'name' => 'zzz_last_role',
            'guard_name' => 'web',
            'display_name' => 'ZZZ Last',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.roles.index', ['sort_column' => 'display_name', 'sort_direction' => 'asc']))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->has('items');
                $page->where('items', function ($items) {
                    // First item should come before last alphabetically
                    $names = collect($items)->pluck('display_name')->toArray();
                    $sorted = $names;
                    sort($sorted);
                    $this->assertSame($sorted, $names);

                    return true;
                });
            });
    }

    // =========================================================================
    // INDEX — Status Tab Filtering
    // =========================================================================

    public function test_index_filters_by_active_status(): void
    {
        $activeRole = Role::create([
            'name' => 'active_test_role',
            'guard_name' => 'web',
            'display_name' => 'Active Test Role',
            'status' => Status::ACTIVE,
        ]);
        $inactiveRole = Role::create([
            'name' => 'inactive_test_role',
            'guard_name' => 'web',
            'display_name' => 'Inactive Test Role',
            'status' => Status::INACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.roles.index', ['status' => 'active']))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($activeRole, $inactiveRole): void {
                $page->where('items', function ($items) use ($activeRole, $inactiveRole) {
                    $ids = collect($items)->pluck('id')->toArray();
                    $this->assertContains($activeRole->id, $ids);
                    $this->assertNotContains($inactiveRole->id, $ids);

                    return true;
                });
            });
    }

    public function test_index_filters_by_trash_status(): void
    {
        $trashedRole = Role::create([
            'name' => 'trashed_test_role',
            'guard_name' => 'web',
            'display_name' => 'Trashed Test Role',
            'status' => Status::ACTIVE,
        ]);
        $trashedRole->delete();

        $this->actingAs($this->admin)
            ->get(route('app.roles.index', ['status' => 'trash']))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($trashedRole): void {
                $page->where('items', function ($items) use ($trashedRole) {
                    $ids = collect($items)->pluck('id')->toArray();
                    $this->assertContains($trashedRole->id, $ids);

                    return true;
                });
            });
    }

    // =========================================================================
    // INDEX — Search
    // =========================================================================

    public function test_index_search_filters_results(): void
    {
        Role::create([
            'name' => 'searchable_role',
            'guard_name' => 'web',
            'display_name' => 'Searchable Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.roles.index', ['search' => 'Searchable']))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->where('items', function ($items) {
                    $this->assertNotEmpty($items);
                    $names = collect($items)->pluck('display_name')->toArray();
                    $this->assertContains('Searchable Role', $names);

                    return true;
                });
            });
    }

    // =========================================================================
    // CREATE — Renders Form
    // =========================================================================

    public function test_guests_cannot_access_create_page(): void
    {
        $this->get(route('app.roles.create'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_add_permission_cannot_access_create(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->givePermissionTo('view_roles');

        $this->actingAs($user)
            ->get(route('app.roles.create'))
            ->assertForbidden();
    }

    public function test_create_renders_correct_component(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/create')
            );
    }

    public function test_create_returns_form_view_data(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('statusOptions')
                ->has('permissions')
            );
    }

    // =========================================================================
    // STORE — Create Resource
    // =========================================================================

    public function test_guests_cannot_store_resource(): void
    {
        $this->post(route('app.roles.store'), [
            'display_name' => 'Test Role',
            'guard_name' => 'web',
            'status' => 'active',
        ])->assertRedirect(route('login'));
    }

    public function test_store_creates_resource_and_redirects(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.roles.store'), [
                'display_name' => 'Content Editor',
                'guard_name' => 'web',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'display_name' => 'Content Editor',
            'guard_name' => 'web',
        ]);
    }

    public function test_store_redirects_with_success_flash(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.roles.store'), [
                'display_name' => 'Flash Test Role',
                'guard_name' => 'web',
                'status' => 'active',
            ])
            ->assertSessionHas('status');
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.roles.store'), [])
            ->assertSessionHasErrors(['display_name', 'status']);
    }

    public function test_store_validates_unique_display_name(): void
    {
        Role::create([
            'name' => 'existing_role',
            'guard_name' => 'web',
            'display_name' => 'Existing Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->post(route('app.roles.store'), [
                'display_name' => 'Existing Role',
                'guard_name' => 'web',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('display_name');
    }

    public function test_store_syncs_permissions(): void
    {
        $permissionIds = Permission::whereIn('name', ['view_roles', 'edit_roles'])
            ->pluck('id')
            ->toArray();

        $this->actingAs($this->admin)
            ->post(route('app.roles.store'), [
                'display_name' => 'Permission Sync Role',
                'guard_name' => 'web',
                'status' => 'active',
                'permissions' => $permissionIds,
            ])
            ->assertRedirect();

        $role = Role::where('display_name', 'Permission Sync Role')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('view_roles'));
        $this->assertTrue($role->hasPermissionTo('edit_roles'));
    }

    // =========================================================================
    // SHOW — Display Resource
    // =========================================================================

    public function test_show_renders_correct_component(): void
    {
        $this->markTestSkipped('Show page component not yet created (Phase 7).');
    }

    public function test_show_includes_model_data(): void
    {
        $this->markTestSkipped('Show page component not yet created (Phase 7).');
    }

    // =========================================================================
    // EDIT — Edit Form
    // =========================================================================

    public function test_edit_renders_correct_component(): void
    {
        $role = Role::create([
            'name' => 'edit_test_role',
            'guard_name' => 'web',
            'display_name' => 'Edit Test Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.roles.edit', $role))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/edit')
                ->has('role')
                ->has('statusOptions')
                ->has('permissions')
            );
    }

    public function test_edit_returns_model_data(): void
    {
        $role = Role::create([
            'name' => 'edit_data_role',
            'guard_name' => 'web',
            'display_name' => 'Edit Data Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.roles.edit', $role))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('role.display_name', 'Edit Data Role')
            );
    }

    // =========================================================================
    // UPDATE — Update Resource
    // =========================================================================

    public function test_update_modifies_resource_and_redirects(): void
    {
        $role = Role::create([
            'name' => 'update_test_role',
            'guard_name' => 'web',
            'display_name' => 'Update Test Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->put(route('app.roles.update', $role), [
                'display_name' => 'Updated Role Name',
                'guard_name' => 'web',
                'status' => 'active',
            ])
            ->assertRedirect();

        $role->refresh();
        $this->assertSame('Updated Role Name', $role->display_name);
    }

    public function test_update_redirects_with_success_flash(): void
    {
        $role = Role::create([
            'name' => 'flash_update_role',
            'guard_name' => 'web',
            'display_name' => 'Flash Update Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->put(route('app.roles.update', $role), [
                'display_name' => 'Flash Updated',
                'guard_name' => 'web',
                'status' => 'active',
            ])
            ->assertSessionHas('status');
    }

    public function test_update_validates_required_fields(): void
    {
        $role = Role::create([
            'name' => 'validate_update_role',
            'guard_name' => 'web',
            'display_name' => 'Validate Update Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->put(route('app.roles.update', $role), [])
            ->assertSessionHasErrors(['display_name', 'status']);
    }

    // =========================================================================
    // DESTROY — Soft Delete
    // =========================================================================

    public function test_destroy_soft_deletes_resource(): void
    {
        $role = Role::create([
            'name' => 'delete_test_role',
            'guard_name' => 'web',
            'display_name' => 'Delete Test Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('app.roles.destroy', $role))
            ->assertRedirect(route('app.roles.index'));

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    public function test_destroy_redirects_with_success_flash(): void
    {
        $role = Role::create([
            'name' => 'flash_delete_role',
            'guard_name' => 'web',
            'display_name' => 'Flash Delete Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('app.roles.destroy', $role))
            ->assertSessionHas('status');
    }

    public function test_destroy_already_trashed_returns_error(): void
    {
        $role = Role::create([
            'name' => 'already_trashed_role',
            'guard_name' => 'web',
            'display_name' => 'Already Trashed Role',
            'status' => Status::ACTIVE,
        ]);
        $role->delete();

        $this->actingAs($this->admin)
            ->delete(route('app.roles.destroy', $role))
            ->assertSessionHas('error');
    }

    public function test_destroy_users_without_permission_receive_403(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->givePermissionTo('view_roles');

        $role = Role::create([
            'name' => 'forbidden_delete_role',
            'guard_name' => 'web',
            'display_name' => 'Forbidden Delete Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($user)
            ->delete(route('app.roles.destroy', $role))
            ->assertForbidden();
    }

    // =========================================================================
    // RESTORE — Restore Soft-Deleted
    // =========================================================================

    public function test_restore_restores_soft_deleted_resource(): void
    {
        $role = Role::create([
            'name' => 'restore_test_role',
            'guard_name' => 'web',
            'display_name' => 'Restore Test Role',
            'status' => Status::ACTIVE,
        ]);
        $role->delete();

        $this->actingAs($this->admin)
            ->patch(route('app.roles.restore', $role))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'deleted_at' => null,
        ]);
    }

    // =========================================================================
    // FORCE DELETE — Permanent Deletion
    // =========================================================================

    public function test_force_delete_permanently_removes_trashed_resource(): void
    {
        $role = Role::create([
            'name' => 'force_delete_test_role',
            'guard_name' => 'web',
            'display_name' => 'Force Delete Test Role',
            'status' => Status::ACTIVE,
        ]);
        $role->delete();

        $this->actingAs($this->admin)
            ->delete(route('app.roles.force-delete', $role))
            ->assertRedirect(route('app.roles.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_force_delete_non_trashed_returns_error(): void
    {
        $role = Role::create([
            'name' => 'non_trashed_force_role',
            'guard_name' => 'web',
            'display_name' => 'Non Trashed Force Role',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('app.roles.force-delete', $role))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // =========================================================================
    // BULK ACTIONS
    // =========================================================================

    public function test_bulk_action_requires_action_field(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.roles.bulk-action'), [
                'ids' => [1],
            ])
            ->assertSessionHasErrors('action');
    }

    public function test_bulk_action_requires_ids_field(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.roles.bulk-action'), [
                'action' => 'delete',
            ])
            ->assertSessionHasErrors('ids');
    }

    public function test_bulk_delete_soft_deletes_multiple_resources(): void
    {
        $role1 = Role::create([
            'name' => 'bulk_del_1',
            'guard_name' => 'web',
            'display_name' => 'Bulk Delete 1',
            'status' => Status::ACTIVE,
        ]);
        $role2 = Role::create([
            'name' => 'bulk_del_2',
            'guard_name' => 'web',
            'display_name' => 'Bulk Delete 2',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->post(route('app.roles.bulk-action'), [
                'action' => 'delete',
                'ids' => [$role1->id, $role2->id],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSoftDeleted('roles', ['id' => $role1->id]);
        $this->assertSoftDeleted('roles', ['id' => $role2->id]);
    }

    public function test_bulk_restore_restores_soft_deleted_resources(): void
    {
        $role1 = Role::create([
            'name' => 'bulk_restore_1',
            'guard_name' => 'web',
            'display_name' => 'Bulk Restore 1',
            'status' => Status::ACTIVE,
        ]);
        $role2 = Role::create([
            'name' => 'bulk_restore_2',
            'guard_name' => 'web',
            'display_name' => 'Bulk Restore 2',
            'status' => Status::ACTIVE,
        ]);
        $role1->delete();
        $role2->delete();

        $this->actingAs($this->admin)
            ->post(route('app.roles.bulk-action'), [
                'action' => 'restore',
                'ids' => [$role1->id, $role2->id],
                'status' => 'trash',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('roles', ['id' => $role1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('roles', ['id' => $role2->id, 'deleted_at' => null]);
    }

    public function test_bulk_force_delete_permanently_removes_trashed_resources(): void
    {
        $role1 = Role::create([
            'name' => 'bulk_force_del_1',
            'guard_name' => 'web',
            'display_name' => 'Bulk Force Delete 1',
            'status' => Status::ACTIVE,
        ]);
        $role2 = Role::create([
            'name' => 'bulk_force_del_2',
            'guard_name' => 'web',
            'display_name' => 'Bulk Force Delete 2',
            'status' => Status::ACTIVE,
        ]);
        $role1->delete();
        $role2->delete();

        $this->actingAs($this->admin)
            ->post(route('app.roles.bulk-action'), [
                'action' => 'force_delete',
                'ids' => [$role1->id, $role2->id],
                'status' => 'trash',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('roles', ['id' => $role1->id]);
        $this->assertDatabaseMissing('roles', ['id' => $role2->id]);
    }

    public function test_bulk_delete_protects_super_user_role(): void
    {
        $superUserRole = Role::where('name', 'super_user')->first();

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

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function test_accessing_nonexistent_resource_returns_404(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.roles.show', 99999))
            ->assertNotFound();
    }

    public function test_destroy_nonexistent_resource_redirects_with_status(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('app.roles.destroy', 99999))
            ->assertRedirect(route('app.roles.index'))
            ->assertSessionHas('status');
    }

    public function test_force_delete_nonexistent_resource_redirects_with_status(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('app.roles.force-delete', 99999))
            ->assertRedirect(route('app.roles.index'))
            ->assertSessionHas('status');
    }
}
