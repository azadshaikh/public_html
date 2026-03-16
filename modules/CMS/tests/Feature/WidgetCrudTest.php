<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WidgetCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ?string $optionsFilePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_widgets', 'edit_widgets'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'widgets',
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
        $this->admin->givePermissionTo(['view_widgets', 'edit_widgets']);

        // Fake queues to prevent RecacheApplication from running
        Queue::fake();
    }

    protected function tearDown(): void
    {
        // Reset the theme override so tests don't bleed config into each other
        config(['theme.active' => null]);

        // Clean up any options.json written during tests
        if ($this->optionsFilePath && file_exists($this->optionsFilePath)) {
            unlink($this->optionsFilePath);
        }

        parent::tearDown();
    }

    // =========================================================================
    // Guest redirects
    // =========================================================================

    public function test_guests_are_redirected_from_widgets_index(): void
    {
        $this->get(route('cms.appearance.widgets.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_widgets_edit(): void
    {
        $this->get(route('cms.appearance.widgets.edit', ['area_id' => 'sidebar-main']))
            ->assertRedirect(route('login'));
    }

    // =========================================================================
    // Index page
    // =========================================================================

    public function test_admin_can_view_widgets_index_without_active_theme(): void
    {
        // Without an active theme, widget areas and available widgets are empty arrays
        $this->actingAs($this->admin)
            ->get(route('cms.appearance.widgets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/widgets/index')
                ->has('widgetAreas')
                ->has('currentWidgets')
                ->has('availableWidgets')
            );
    }

    public function test_admin_can_view_widgets_index_with_active_theme(): void
    {
        config(['theme.active' => 'default']);

        $this->actingAs($this->admin)
            ->get(route('cms.appearance.widgets.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/widgets/index')
                ->has('widgetAreas')
                ->has('currentWidgets')
                ->has('availableWidgets')
                ->where('widgetAreas.0.id', 'sidebar-main')
                ->where('widgetAreas.0.name', 'Main Sidebar')
            );
    }

    // =========================================================================
    // Edit page
    // =========================================================================

    public function test_admin_can_view_widget_edit_page(): void
    {
        config(['theme.active' => 'default']);

        $this->actingAs($this->admin)
            ->get(route('cms.appearance.widgets.edit', ['area_id' => 'sidebar-main']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/widgets/edit')
                ->has('widgetArea')
                ->has('currentWidgets')
                ->has('availableWidgets')
                ->where('widgetArea.id', 'sidebar-main')
                ->where('widgetArea.name', 'Main Sidebar')
            );
    }

    public function test_edit_page_returns_404_for_invalid_area_id(): void
    {
        config(['theme.active' => 'default']);

        $this->actingAs($this->admin)
            ->get(route('cms.appearance.widgets.edit', ['area_id' => 'nonexistent-area']))
            ->assertNotFound();
    }

    // =========================================================================
    // Save all widgets
    // =========================================================================

    public function test_admin_can_save_widgets(): void
    {
        config(['theme.active' => 'default']);

        $themesPath = base_path('themes');
        $this->optionsFilePath = $themesPath.'/default/config/options.json';

        $response = $this->actingAs($this->admin)
            ->postJson(route('cms.appearance.widgets.save-all'), [
                'widgets' => [
                    'sidebar-main' => [
                        [
                            'id' => 'widget-test-001',
                            'type' => 'text',
                            'title' => 'My Text Widget',
                            'settings' => ['content' => 'Hello World'],
                            'position' => 0,
                        ],
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_save_widgets_fails_with_missing_widgets_key(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('cms.appearance.widgets.save-all'), [])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_save_widgets_returns_400_for_invalid_area_id(): void
    {
        config(['theme.active' => 'default']);

        $this->actingAs($this->admin)
            ->postJson(route('cms.appearance.widgets.save-all'), [
                'widgets' => [
                    'nonexistent-area' => [],
                ],
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_save_widgets_returns_400_for_invalid_widget_type(): void
    {
        config(['theme.active' => 'default']);

        $this->actingAs($this->admin)
            ->postJson(route('cms.appearance.widgets.save-all'), [
                'widgets' => [
                    'sidebar-main' => [
                        [
                            'id' => 'widget-test-001',
                            'type' => 'nonexistent-widget-type',
                            'title' => 'Bad Widget',
                            'settings' => [],
                            'position' => 0,
                        ],
                    ],
                ],
            ])
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    // =========================================================================
    // Authorization
    // =========================================================================

    public function test_user_without_view_permission_cannot_access_widgets_index(): void
    {
        $viewer = User::factory()->create([
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('cms.appearance.widgets.index'))
            ->assertForbidden();
    }

    public function test_user_without_edit_permission_cannot_save_widgets(): void
    {
        $viewer = User::factory()->create([
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $viewer->givePermissionTo('view_widgets');

        $this->actingAs($viewer)
            ->postJson(route('cms.appearance.widgets.save-all'), [
                'widgets' => ['sidebar-main' => []],
            ])
            ->assertForbidden();
    }
}
