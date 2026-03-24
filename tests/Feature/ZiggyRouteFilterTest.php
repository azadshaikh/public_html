<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use App\Services\ZiggyRouteFilter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Modules\Agency\Providers\AgencyServiceProvider;
use Tests\TestCase;
use Tighten\Ziggy\BladeRouteGenerator;
use Tighten\Ziggy\Ziggy;

class ZiggyRouteFilterTest extends TestCase
{
    use RefreshDatabase;

    private ZiggyRouteFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['manage_seo_settings', 'manage_integrations_seo_settings', 'manage_cms_seo_settings'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'seo',
                    'module_slug' => 'cms',
                ],
            );
        }

        $this->filter = new ZiggyRouteFilter;

        // Reset Ziggy statics so each test gets a fresh state.
        Ziggy::clearRoutes();
        BladeRouteGenerator::$generated = false;
    }

    // =========================================================================
    // resolveGroups()
    // =========================================================================

    public function test_guest_gets_only_public_group(): void
    {
        $groups = $this->filter->resolveGroups(null);

        $this->assertSame(['public'], $groups);
    }

    public function test_super_user_gets_empty_array_for_unfiltered_access(): void
    {
        $superUser = User::factory()->create(['status' => Status::ACTIVE]);
        $superUser->assignRole(Role::findByName('super_user', 'web'));

        $groups = $this->filter->resolveGroups($superUser);

        $this->assertSame([], $groups);
    }

    public function test_regular_user_gets_public_and_authenticated_groups(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $groups = $this->filter->resolveGroups($user);

        $this->assertContains('public', $groups);
        $this->assertContains('authenticated', $groups);
        $this->assertNotContains('masters', $groups);
        $this->assertNotContains('logs', $groups);
        $this->assertNotContains('log_viewer', $groups);
        $this->assertNotContains('broadcast', $groups);
    }

    public function test_user_with_log_permissions_gets_logs_group_only(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->givePermissionTo('view_activity_logs');

        $groups = $this->filter->resolveGroups($user);

        $this->assertContains('logs', $groups);
        $this->assertNotContains('log_viewer', $groups);
    }

    public function test_user_with_seo_permissions_gets_seo_group(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->givePermissionTo('manage_seo_settings');

        $groups = $this->filter->resolveGroups($user);

        $this->assertContains('seo', $groups);
    }

    public function test_user_with_view_users_permission_gets_users_group(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->givePermissionTo('view_users');

        $groups = $this->filter->resolveGroups($user);

        $this->assertContains('users', $groups);
    }

    public function test_user_with_view_roles_permission_gets_roles_group(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->givePermissionTo('view_roles');

        $groups = $this->filter->resolveGroups($user);

        $this->assertContains('roles', $groups);
    }

    public function test_user_without_permissions_does_not_get_restricted_groups(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $groups = $this->filter->resolveGroups($user);

        $this->assertNotContains('users', $groups);
        $this->assertNotContains('roles', $groups);
        $this->assertNotContains('masters', $groups);
        $this->assertNotContains('logs', $groups);
        $this->assertNotContains('log_viewer', $groups);
        $this->assertNotContains('broadcast', $groups);
    }

    // =========================================================================
    // cacheKey()
    // =========================================================================

    public function test_cache_key_for_guest(): void
    {
        $key = $this->filter->cacheKey(null);

        $this->assertStringStartsWith('ziggy_routes:v', $key);
        $this->assertStringContainsString(':guest:', $key);
    }

    public function test_cache_key_uses_primary_role_id(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $role = Role::findByName('administrator', 'web');
        $user->assignRole($role);

        $key = $this->filter->cacheKey($user);

        $this->assertStringStartsWith('ziggy_routes:v', $key);
        $this->assertStringContainsString(":role:{$role->id}:", $key);
    }

    public function test_cache_key_for_user_without_role(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $key = $this->filter->cacheKey($user);

        $this->assertStringStartsWith('ziggy_routes:v', $key);
        $this->assertStringContainsString(':role:0:', $key);
    }

    public function test_cache_key_changes_when_effective_permissions_change(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $before = $this->filter->cacheKey($user->fresh());

        $user->givePermissionTo('view_activity_logs');

        $after = $this->filter->cacheKey($user->fresh());

        $this->assertNotSame($before, $after);
    }

    // =========================================================================
    // render()
    // =========================================================================

    public function test_render_returns_script_tag(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $output = $this->filter->render($user);

        $this->assertStringContainsString('<script type="text/javascript">', $output);
        $this->assertStringContainsString('const Ziggy=', $output);
        $this->assertStringContainsString('</script>', $output);
    }

    public function test_render_for_guest_only_contains_public_routes(): void
    {
        $this->ensureAgencyModuleBooted();

        $output = $this->filter->render(null);

        // Public routes should be present.
        $this->assertStringContainsString('login', $output);
        $this->assertStringContainsString('agency.sign-in', $output);
        $this->assertStringContainsString('agency.get-started', $output);
        $this->assertStringContainsString('agency.get-started.store', $output);
        $this->assertStringContainsString('sitemap', $output);

        // Admin-only routes should be absent.
        $this->assertStringNotContainsString('app.masters.settings.index', $output);
        $this->assertStringNotContainsString('app.masters.laravel-tools.index', $output);
        $this->assertStringNotContainsString('app.users.index', $output);
        $this->assertStringNotContainsString('app.roles.index', $output);
        $this->assertStringNotContainsString('app.logs.activity-logs.index', $output);
        $this->assertStringNotContainsString('log-viewer.index', $output);
    }

    private function ensureAgencyModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('agency.sign-in')) {
            app()->register(AgencyServiceProvider::class);
        }

        if (! Route::has('agency.sign-in')) {
            Route::middleware('web')->group(base_path('modules/Agency/routes/web.php'));
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }
    }

    public function test_render_for_super_user_contains_all_routes(): void
    {
        $superUser = User::factory()->create(['status' => Status::ACTIVE]);
        $superUser->assignRole(Role::findByName('super_user', 'web'));

        $output = $this->filter->render($superUser);

        $this->assertStringContainsString('app.masters.settings.index', $output);
        $this->assertStringContainsString('app.users.index', $output);
        $this->assertStringContainsString('app.roles.index', $output);
        $this->assertStringContainsString('app.logs.activity-logs.index', $output);
        $this->assertStringContainsString('log-viewer.index', $output);
        $this->assertStringContainsString('dashboard', $output);
    }

    public function test_render_for_regular_user_excludes_masters_routes(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->givePermissionTo('view_users');

        $output = $this->filter->render($user);

        // Dashboard should be present (authenticated group).
        $this->assertStringContainsString('dashboard', $output);
        $this->assertStringContainsString('cms.pages.index', $output);

        // Users group should be present (has permission).
        $this->assertStringContainsString('app.users.index', $output);

        // Masters should be absent (super user only), logs absent without log permissions.
        $this->assertStringNotContainsString('app.masters.settings.index', $output);
        $this->assertStringNotContainsString('app.masters.modules.index', $output);
        $this->assertStringNotContainsString('app.logs.activity-logs.index', $output);
        $this->assertStringNotContainsString('log-viewer.index', $output);
    }

    public function test_render_for_user_with_log_permissions_includes_app_logs_but_not_log_viewer(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->givePermissionTo('view_activity_logs');

        $output = $this->filter->render($user);

        $this->assertStringContainsString('app.logs.activity-logs.index', $output);
        $this->assertStringNotContainsString('log-viewer.index', $output);
    }

    public function test_render_for_user_with_seo_permissions_includes_seo_routes(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->givePermissionTo('manage_cms_seo_settings');

        $output = $this->filter->render($user);

        $this->assertStringContainsString('seo.dashboard', $output);
        $this->assertStringContainsString('seo.settings.titlesmeta', $output);
    }

    // =========================================================================
    // Caching
    // =========================================================================

    public function test_render_result_is_cached(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $cacheKey = $this->filter->cacheKey($user);

        // Ensure cache is empty.
        Cache::forget($cacheKey);
        $this->assertNull(Cache::get($cacheKey));

        // First render should populate cache.
        $this->filter->render($user);

        $this->assertNotNull(Cache::get($cacheKey));
    }

    public function test_super_user_render_is_not_cached(): void
    {
        $superUser = User::factory()->create(['status' => Status::ACTIVE]);
        $superUser->assignRole(Role::findByName('super_user', 'web'));

        $cacheKey = $this->filter->cacheKey($superUser);
        Cache::forget($cacheKey);

        $this->filter->render($superUser);

        // Super user uses unfiltered BladeRouteGenerator, no caching.
        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_clear_cache_removes_cached_entries(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $role = Role::findByName('administrator', 'web');
        $user->assignRole($role);

        // Populate caches.
        $this->filter->render($user);
        $this->filter->render(null);

        // Verify they exist.
        $this->assertNotNull(Cache::get($this->filter->cacheKey($user)));
        $this->assertNotNull(Cache::get($this->filter->cacheKey(null)));

        $previousUserKey = $this->filter->cacheKey($user);
        $previousGuestKey = $this->filter->cacheKey(null);

        // Clear.
        ZiggyRouteFilter::clearCache();

        // Verify new cache keys are generated after invalidation.
        $this->assertNotSame($previousUserKey, $this->filter->cacheKey($user));
        $this->assertNotSame($previousGuestKey, $this->filter->cacheKey(null));
    }

    // =========================================================================
    // Integration — full page load contains filtered Ziggy
    // =========================================================================

    public function test_page_load_as_guest_has_filtered_routes(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('const Ziggy=', $content);
        $this->assertStringNotContainsString('app.masters.settings.index', $content);
        $this->assertStringNotContainsString('app.users.index', $content);
    }

    public function test_page_load_as_super_user_has_all_routes(): void
    {
        $superUser = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'status' => Status::ACTIVE,
        ]);
        $superUser->assignRole(Role::findByName('super_user', 'web'));

        $response = $this->actingAs($superUser)->get(route('dashboard'));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('const Ziggy=', $content);
        $this->assertStringContainsString('app.masters.settings.index', $content);
        $this->assertStringContainsString('app.users.index', $content);
    }

    public function test_page_load_as_regular_user_has_limited_routes(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        // Administrator role grants dashboard access but NOT super user privileges.
        $user->assignRole(Role::findByName('administrator', 'web'));
        $user->givePermissionTo('manage_cms_seo_settings');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('const Ziggy=', $content);
        $this->assertStringContainsString('dashboard', $content);
        // Administrator is not a super user, so masters stay hidden.
        $this->assertStringNotContainsString('app.masters.settings.index', $content);
        $this->assertStringContainsString('app.logs.activity-logs.index', $content);
        $this->assertStringContainsString('seo.dashboard', $content);
        $this->assertStringNotContainsString('log-viewer.index', $content);
    }
}
