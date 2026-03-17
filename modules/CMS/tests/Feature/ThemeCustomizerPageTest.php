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

class ThemeCustomizerPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['theme.active' => 'default']);

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_themes', 'edit_themes'] as $permission) {
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
        $this->admin->givePermissionTo(['view_themes', 'edit_themes']);
    }

    public function test_admin_can_view_react_theme_customizer_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.appearance.themes.customizer.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/themes/customizer/index')
                ->where('activeTheme.directory', 'default')
                ->where('previewUrl', url('/'))
                ->has('sections.site_identity')
                ->has('sections.custom_code')
                ->has('initialValues')
                ->where('pickerMedia', null)
                ->where('pickerFilters', null)
                ->where('uploadSettings', null)
            );
    }
}