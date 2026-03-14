<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use App\Services\ZiggyRouteFilter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
        $this->assertNotContains('broadcast', $groups);
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

    public function test_user_with_manage_modules_permission_gets_modules_group(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->givePermissionTo('manage_modules');

        $groups = $this->filter->resolveGroups($user);

        $this->assertContains('modules', $groups);
    }

    public function test_user_without_permissions_does_not_get_restricted_groups(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $groups = $this->filter->resolveGroups($user);

        $this->assertNotContains('users', $groups);
        $this->assertNotContains('roles', $groups);
        $this->assertNotContains('modules', $groups);
        $this->assertNotContains('masters', $groups);
        $this->assertNotContains('logs', $groups);
        $this->assertNotContains('broadcast', $groups);
    }

    // =========================================================================
    // cacheKey()
    // =========================================================================

    public function test_cache_key_for_guest(): void
    {
        $key = $this->filter->cacheKey(null);

        $this->assertSame('ziggy_routes:guest', $key);
    }

    public function test_cache_key_uses_primary_role_id(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $role = Role::findByName('administrator', 'web');
        $user->assignRole($role);

        $key = $this->filter->cacheKey($user);

        $this->assertSame("ziggy_routes:role:{$role->id}", $key);
    }

    public function test_cache_key_for_user_without_role(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);

        $key = $this->filter->cacheKey($user);

        $this->assertSame('ziggy_routes:role:0', $key);
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
        $output = $this->filter->render(null);

        // Public routes should be present.
        $this->assertStringContainsString('login', $output);

        // Admin-only routes should be absent.
        $this->assertStringNotContainsString('app.masters.settings.index', $output);
        $this->assertStringNotContainsString('app.masters.laravel-tools.index', $output);
        $this->assertStringNotContainsString('app.users.index', $output);
        $this->assertStringNotContainsString('app.roles.index', $output);
        $this->assertStringNotContainsString('app.logs.activity-logs.index', $output);
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
        $this->assertStringContainsString('dashboard', $output);
    }

    public function test_render_for_regular_user_excludes_masters_routes(): void
    {
        $user = User::factory()->create(['status' => Status::ACTIVE]);
        $user->givePermissionTo('view_users');

        $output = $this->filter->render($user);

        // Dashboard should be present (authenticated group).
        $this->assertStringContainsString('dashboard', $output);

        // Users group should be present (has permission).
        $this->assertStringContainsString('app.users.index', $output);

        // Masters/logs should be absent (super user only).
        $this->assertStringNotContainsString('app.masters.settings.index', $output);
        $this->assertStringNotContainsString('app.logs.activity-logs.index', $output);
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
        $this->assertNotNull(Cache::get("ziggy_routes:role:{$role->id}"));
        $this->assertNotNull(Cache::get('ziggy_routes:guest'));

        // Clear.
        ZiggyRouteFilter::clearCache();

        // Verify they are gone.
        $this->assertNull(Cache::get("ziggy_routes:role:{$role->id}"));
        $this->assertNull(Cache::get('ziggy_routes:guest'));
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

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('const Ziggy=', $content);
        $this->assertStringContainsString('dashboard', $content);
        // Administrator is not a super user, so masters/logs are hidden.
        $this->assertStringNotContainsString('app.masters.settings.index', $content);
        $this->assertStringNotContainsString('app.logs.activity-logs.index', $content);
    }
}
