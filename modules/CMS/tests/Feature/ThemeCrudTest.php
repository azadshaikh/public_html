<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CMS\Models\Theme;
use Tests\TestCase;

class ThemeCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_themes', 'add_themes', 'edit_themes', 'delete_themes'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'themes',
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
            'view_themes',
            'add_themes',
            'edit_themes',
            'delete_themes',
        ]);
    }

    protected function tearDown(): void
    {
        config(['theme.active' => null]);

        parent::tearDown();
    }

    public function test_guests_are_redirected_from_themes_index(): void
    {
        $this->get(route('cms.appearance.themes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_view_themes_index(): void
    {
        config(['theme.active' => 'default']);

        $allThemes = Theme::getAllThemes();

        $this->actingAs($this->admin)
            ->get(route('cms.appearance.themes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/themes/index')
                ->has('themes', count($allThemes))
                ->has('activeTheme')
                ->where('activeTheme.directory', 'default')
                ->where('filters.search', '')
                ->where('filters.filter', 'all')
                ->where('filters.supports', [])
                ->where('statistics.total', count($allThemes))
                ->where('statistics.active', 1)
                ->has('availableSupports')
            );
    }

    public function test_admin_can_filter_themes_by_active_state(): void
    {
        config(['theme.active' => 'default']);

        $this->actingAs($this->admin)
            ->get(route('cms.appearance.themes.index', ['filter' => 'active']))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/themes/index')
                ->has('themes', 1)
                ->where('themes.0.directory', 'default')
                ->where('themes.0.is_active', true)
                ->where('filters.filter', 'active')
            );
    }

    public function test_admin_can_search_themes_by_name(): void
    {
        config(['theme.active' => 'default']);

        $defaultTheme = Theme::getThemeInfo('default');
        $search = $defaultTheme['name'] ?? 'Default';

        $this->actingAs($this->admin)
            ->get(route('cms.appearance.themes.index', ['search' => $search]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/themes/index')
                ->has('themes', 1)
                ->where('themes.0.directory', 'default')
                ->where('filters.search', $search)
            );
    }

    public function test_user_without_view_permission_cannot_access_themes_index(): void
    {
        config(['theme.active' => 'default']);

        $user = User::factory()->create([
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('cms.appearance.themes.index'))
            ->assertForbidden();
    }
}
