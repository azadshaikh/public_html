<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\CMS\Services\FrontendFaviconService;
use Tests\TestCase;

class ThemeCustomizerFaviconGenerationTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_theme_customizer_update_syncs_generated_assets_when_favicon_related_branding_changes(): void
    {
        $service = Mockery::mock(FrontendFaviconService::class);
        $service->shouldReceive('syncGeneratedAssets')->once();
        $this->app->instance(FrontendFaviconService::class, $service);

        $this->actingAs($this->admin)
            ->postJson(route('cms.appearance.themes.customizer.update'), [
                'favicon' => '/test-favicon.svg',
                'site_title' => 'Updated Site Title',
                'primary_color' => '#007cba',
                'secondary_color' => '#6c757d',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_theme_customizer_update_does_not_sync_generated_assets_for_unrelated_theme_settings(): void
    {
        $service = Mockery::mock(FrontendFaviconService::class);
        $service->shouldNotReceive('syncGeneratedAssets');
        $this->app->instance(FrontendFaviconService::class, $service);

        $this->actingAs($this->admin)
            ->postJson(route('cms.appearance.themes.customizer.update'), [
                'logo_width' => 180,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }
}
