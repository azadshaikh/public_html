<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CMS\Models\Menu;
use Tests\TestCase;

class MenuCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_menus', 'add_menus', 'edit_menus', 'delete_menus', 'restore_menus'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'menus',
                    'module_slug' => 'cms',
                ],
            );
        }

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole(Role::findByName('administrator', 'web'));
        $this->admin->givePermissionTo([
            'view_menus',
            'add_menus',
            'edit_menus',
            'delete_menus',
            'restore_menus',
        ]);
    }

    // =========================================================================
    // Guest redirects
    // =========================================================================

    public function test_guests_are_redirected_from_menus_index(): void
    {
        $this->get(route('cms.appearance.menus.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_menus_create_page(): void
    {
        $this->get(route('cms.appearance.menus.create'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_menus_edit_page(): void
    {
        $menu = $this->createMenu('Guest Menu');

        $this->get(route('cms.appearance.menus.edit', $menu))
            ->assertRedirect(route('login'));
    }

    // =========================================================================
    // Index
    // =========================================================================

    public function test_admin_can_access_menus_index(): void
    {
        $this->createMenu('Primary Nav');

        $this->actingAs($this->admin)
            ->get(route('cms.appearance.menus.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/menus/index')
                ->has('rows')
                ->has('locations')
                ->has('locationAssignments')
            );
    }

    // =========================================================================
    // Create
    // =========================================================================

    public function test_admin_can_access_menus_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.appearance.menus.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/menus/create')
                ->has('locations')
                ->has('locationOptions')
                ->has('statusOptions')
                ->has('assignedMenus')
            );
    }

    // =========================================================================
    // Edit
    // =========================================================================

    public function test_admin_can_access_menus_edit_page(): void
    {
        $menu = $this->createMenu('Test Menu');

        $this->actingAs($this->admin)
            ->get(route('cms.appearance.menus.edit', $menu))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/menus/edit')
                ->where('menu.id', $menu->id)
                ->where('menu.name', 'Test Menu')
                ->has('menu.all_items')
                ->has('pages')
                ->has('categories')
                ->has('tags')
                ->has('itemTypes')
                ->has('itemTargets')
                ->has('locations')
                ->has('locationOptions')
                ->has('statusOptions')
            );
    }

    public function test_edit_page_returns_flat_item_list(): void
    {
        $menu = $this->createMenu('Menu With Items');

        $topItem = Menu::query()->create([
            'type' => 'custom',
            'parent_id' => $menu->id,
            'name' => 'Home',
            'title' => 'Home',
            'url' => '/',
            'target' => '_self',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $childItem = Menu::query()->create([
            'type' => 'custom',
            'parent_id' => $topItem->id,
            'name' => 'Sub Page',
            'title' => 'Sub Page',
            'url' => '/sub',
            'target' => '_self',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('cms.appearance.menus.edit', $menu))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/menus/edit')
                ->count('menu.all_items', 2)
                ->where('menu.all_items.0.id', $topItem->id)
                ->where('menu.all_items.0.parent_id', $menu->id)
                ->where('menu.all_items.1.id', $childItem->id)
                ->where('menu.all_items.1.parent_id', $topItem->id)
            );
    }

    // =========================================================================
    // Store
    // =========================================================================

    public function test_admin_can_store_a_menu(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cms.appearance.menus.store'), [
                'name' => 'Main Navigation',
                'location' => '',
                'description' => 'Primary site navigation.',
                'is_active' => true,
            ]);

        $menu = Menu::query()->containers()->where('name', 'Main Navigation')->firstOrFail();

        $response->assertRedirect(route('cms.appearance.menus.edit', $menu));

        $this->assertSame('Main Navigation', $menu->name);
        $this->assertSame(Menu::TYPE_CONTAINER, $menu->type);
        $this->assertTrue($menu->is_active);
    }

    public function test_admin_cannot_store_menu_without_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cms.appearance.menus.store'), [
                'name' => '',
            ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_admin_cannot_store_menu_with_duplicate_location(): void
    {
        // Create a menu that already occupies a location
        Menu::query()->create([
            'type' => Menu::TYPE_CONTAINER,
            'name' => 'Existing Menu',
            'slug' => 'existing-menu',
            'location' => 'primary',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('cms.appearance.menus.store'), [
                'name' => 'Another Menu',
                'location' => 'primary',
                'is_active' => true,
            ]);

        $response->assertSessionHasErrors(['location']);
    }

    // =========================================================================
    // Save All (menu builder endpoint)
    // =========================================================================

    public function test_admin_can_save_all_menu_changes(): void
    {
        $menu = $this->createMenu('Saveable Menu');

        $response = $this->actingAs($this->admin)
            ->postJson(route('cms.appearance.menus.save-all', $menu), [
                'settings' => [
                    'name' => 'Updated Menu Name',
                    'location' => '',
                    'is_active' => true,
                    'description' => 'Updated description.',
                ],
                'items' => [
                    'new' => [
                        [
                            'id' => -1,
                            'parent_id' => $menu->id,
                            'title' => 'Home',
                            'url' => '/',
                            'type' => 'custom',
                            'target' => '_self',
                            'icon' => '',
                            'css_classes' => '',
                            'link_title' => '',
                            'link_rel' => '',
                            'description' => '',
                            'object_id' => null,
                            'sort_order' => 0,
                            'is_active' => true,
                        ],
                    ],
                    'updated' => [],
                    'deleted' => [],
                    'order' => [
                        ['id' => -1, 'parent_id' => $menu->id, 'sort_order' => 0],
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['newItemIds']);

        $menu->refresh();
        $this->assertSame('Updated Menu Name', $menu->name);
        $this->assertTrue($menu->is_active);

        // New item should have been created
        $this->assertDatabaseHas('cms_menus', [
            'parent_id' => $menu->id,
            'title' => 'Home',
            'url' => '/',
            'type' => 'custom',
        ]);
    }

    public function test_admin_can_save_all_with_nested_new_items(): void
    {
        $menu = $this->createMenu('Nested Menu');

        $response = $this->actingAs($this->admin)
            ->postJson(route('cms.appearance.menus.save-all', $menu), [
                'settings' => [
                    'name' => 'Nested Menu',
                    'location' => '',
                    'is_active' => true,
                    'description' => '',
                ],
                'items' => [
                    'new' => [
                        [
                            'id' => -1,
                            'parent_id' => $menu->id,
                            'title' => 'Parent Item',
                            'url' => '/parent',
                            'type' => 'custom',
                            'target' => '_self',
                            'icon' => '',
                            'css_classes' => '',
                            'link_title' => '',
                            'link_rel' => '',
                            'description' => '',
                            'object_id' => null,
                            'sort_order' => 0,
                            'is_active' => true,
                        ],
                        [
                            'id' => -2,
                            'parent_id' => -1,
                            'title' => 'Child Item',
                            'url' => '/parent/child',
                            'type' => 'custom',
                            'target' => '_self',
                            'icon' => '',
                            'css_classes' => '',
                            'link_title' => '',
                            'link_rel' => '',
                            'description' => '',
                            'object_id' => null,
                            'sort_order' => 0,
                            'is_active' => true,
                        ],
                    ],
                    'updated' => [],
                    'deleted' => [],
                    'order' => [
                        ['id' => -1, 'parent_id' => $menu->id, 'sort_order' => 0],
                        ['id' => -2, 'parent_id' => -1, 'sort_order' => 0],
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $parentItem = Menu::query()->where('title', 'Parent Item')->first();
        $childItem = Menu::query()->where('title', 'Child Item')->first();

        $this->assertNotNull($parentItem);
        $this->assertNotNull($childItem);
        $this->assertSame($parentItem->id, $childItem->parent_id);
    }

    public function test_admin_can_delete_items_via_save_all(): void
    {
        $menu = $this->createMenu('Menu With Deletable Item');

        $item = Menu::query()->create([
            'type' => 'custom',
            'parent_id' => $menu->id,
            'name' => 'To Delete',
            'title' => 'To Delete',
            'url' => '/to-delete',
            'target' => '_self',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('cms.appearance.menus.save-all', $menu), [
                'settings' => [
                    'name' => $menu->name,
                    'location' => '',
                    'is_active' => true,
                    'description' => '',
                ],
                'items' => [
                    'new' => [],
                    'updated' => [],
                    'deleted' => [['id' => $item->id]],
                    'order' => [],
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('cms_menus', [
            'id' => $item->id,
            'deleted_at' => null,
        ]);
    }

    public function test_save_all_fails_validation_when_name_is_missing(): void
    {
        $menu = $this->createMenu('Validation Menu');

        $this->actingAs($this->admin)
            ->postJson(route('cms.appearance.menus.save-all', $menu), [
                'settings' => [
                    'name' => '',
                    'location' => '',
                    'is_active' => true,
                    'description' => '',
                ],
                'items' => [
                    'new' => [],
                    'updated' => [],
                    'deleted' => [],
                    'order' => [],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors']);
    }

    // =========================================================================
    // Soft Delete & Restore
    // =========================================================================

    public function test_admin_can_soft_delete_a_menu(): void
    {
        $menu = $this->createMenu('Deletable Menu');

        $this->actingAs($this->admin)
            ->delete(route('cms.appearance.menus.destroy', $menu))
            ->assertRedirect();

        $this->assertSoftDeleted('cms_menus', ['id' => $menu->id]);
    }

    // =========================================================================
    // Duplicate
    // =========================================================================

    public function test_admin_can_duplicate_a_menu(): void
    {
        $menu = $this->createMenu('Original Menu');

        Menu::query()->create([
            'type' => 'custom',
            'parent_id' => $menu->id,
            'name' => 'Nav Item',
            'title' => 'Nav Item',
            'url' => '/',
            'target' => '_self',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('cms.appearance.menus.duplicate', $menu))
            ->assertRedirect(route('cms.appearance.menus.index'));

        $copy = Menu::query()->containers()->where('name', 'Original Menu (Copy)')->first();

        $this->assertNotNull($copy);
        $this->assertEmpty($copy->location); // copies have no location
        $this->assertDatabaseHas('cms_menus', [
            'parent_id' => $copy->id,
            'title' => 'Nav Item',
        ]);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function createMenu(string $name, string $location = ''): Menu
    {
        return Menu::query()->create([
            'type' => Menu::TYPE_CONTAINER,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'location' => $location,
            'description' => null,
            'is_active' => true,
        ]);
    }
}
