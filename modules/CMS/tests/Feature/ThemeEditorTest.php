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
use Tests\TestCase;

class ThemeEditorTest extends TestCase
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

    public function test_guests_are_redirected_from_theme_editor(): void
    {
        $this->get(route('cms.appearance.themes.editor.index', 'default'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_view_theme_editor(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.appearance.themes.editor.index', 'default'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/themes/editor/index')
                ->where('theme.directory', 'default')
                ->where('themeDirectory', 'default')
                ->where('isChildTheme', false)
                ->where('parentTheme', null)
                ->has('files')
            );
    }

    public function test_missing_theme_redirects_back_to_themes_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.appearance.themes.editor.index', 'missing-theme'))
            ->assertRedirect(route('cms.appearance.themes.index'))
            ->assertSessionHas('error', 'Theme not found.');
    }

    public function test_user_without_view_permission_cannot_access_theme_editor(): void
    {
        $user = User::factory()->create([
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('cms.appearance.themes.editor.index', 'default'))
            ->assertForbidden();
    }
}
