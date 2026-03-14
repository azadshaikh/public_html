<?php

declare(strict_types=1);

namespace Tests\Feature\Masters;

use App\Enums\Status;
use App\Http\Middleware\EnableDebugbarForSuperUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Fruitcake\LaravelDebugbar\Facades\Debugbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superUser = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $superUserRole = Role::findByName('super_user', 'web');
        $this->superUser->assignRole($superUserRole);

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $adminRole = Role::findByName('administrator', 'web');
        $this->admin->assignRole($adminRole);

        $this->regularUser = User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
    }

    // =========================================================================
    // INDEX — Authentication & Authorization
    // =========================================================================

    public function test_guests_are_redirected_to_login_from_master_settings_index(): void
    {
        $this->get(route('app.masters.settings.index'))
            ->assertRedirect(route('login'));
    }

    public function test_regular_users_cannot_access_master_settings_index(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.masters.settings.index'))
            ->assertForbidden();
    }

    public function test_admins_cannot_access_master_settings_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.masters.settings.index'))
            ->assertForbidden();
    }

    public function test_super_user_is_redirected_from_master_settings_index(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.index'))
            ->assertRedirect(route('app.masters.settings.app'));
    }

    // =========================================================================
    // APP — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_app_settings(): void
    {
        $this->get(route('app.masters.settings.app'))
            ->assertRedirect(route('login'));
    }

    public function test_admins_cannot_access_app_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.masters.settings.app'))
            ->assertForbidden();
    }

    public function test_super_user_can_access_app_settings(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.app'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('master-settings/app')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('homepage_redirect_enabled')
                    ->has('homepage_redirect_slug')
                )
                ->has('cmsEnabled')
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // BRANDING — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_branding_settings(): void
    {
        $this->get(route('app.masters.settings.branding'))
            ->assertRedirect(route('login'));
    }

    public function test_admins_cannot_access_branding_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.masters.settings.branding'))
            ->assertForbidden();
    }

    public function test_super_user_can_access_branding_settings(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.branding'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('master-settings/branding')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('brand_name')
                    ->has('brand_website')
                    ->has('logo')
                    ->has('icon')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // LOGIN SECURITY — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_login_security_settings(): void
    {
        $this->get(route('app.masters.settings.login-security'))
            ->assertRedirect(route('login'));
    }

    public function test_admins_cannot_access_login_security_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.masters.settings.login-security'))
            ->assertForbidden();
    }

    public function test_super_user_can_access_login_security_settings(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.login-security'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('master-settings/login-security')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('admin_login_url_slug')
                    ->has('limit_login_attempts_enabled')
                    ->has('limit_login_attempts')
                    ->has('lockout_time')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // EMAIL — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_email_settings(): void
    {
        $this->get(route('app.masters.settings.email'))
            ->assertRedirect(route('login'));
    }

    public function test_admins_cannot_access_email_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.masters.settings.email'))
            ->assertForbidden();
    }

    public function test_super_user_can_access_email_settings(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.email'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('master-settings/email')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('email_driver')
                    ->has('email_host')
                    ->has('email_port')
                    ->has('email_username')
                    ->has('email_password')
                    ->has('email_encryption')
                    ->has('email_from_address')
                    ->has('email_from_name')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // STORAGE — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_storage_settings(): void
    {
        $this->get(route('app.masters.settings.storage'))
            ->assertRedirect(route('login'));
    }

    public function test_admins_cannot_access_storage_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.masters.settings.storage'))
            ->assertForbidden();
    }

    public function test_super_user_can_access_storage_settings(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.storage'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('master-settings/storage')
                ->has('settings')
                ->where('settings.storage_driver', fn ($value): bool => is_string($value))
                ->has('settings.root_folder')
                ->has('settings.max_storage_size')
                ->has('settings.storage_cdn_url')
                ->has('options.storageDrivers')
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // MEDIA — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_media_settings(): void
    {
        $this->get(route('app.masters.settings.media'))
            ->assertRedirect(route('login'));
    }

    public function test_admins_cannot_access_media_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.masters.settings.media'))
            ->assertForbidden();
    }

    public function test_super_user_can_access_media_settings(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.media'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('master-settings/media')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('max_file_name_length')
                    ->has('max_upload_size')
                    ->has('allowed_file_types')
                    ->has('image_optimization')
                    ->has('image_quality')
                    ->has('thumbnail_width')
                    ->has('small_width')
                    ->has('medium_width')
                    ->has('large_width')
                    ->has('xlarge_width')
                    ->has('delete_trashed')
                    ->has('delete_trashed_days')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // DEBUG — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_debug_settings(): void
    {
        $this->get(route('app.masters.settings.debug'))
            ->assertRedirect(route('login'));
    }

    public function test_admins_cannot_access_debug_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.masters.settings.debug'))
            ->assertForbidden();
    }

    public function test_super_user_can_access_debug_settings(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.debug'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('master-settings/debug')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('enable_debugging')
                    ->has('enable_debugging_bar')
                    ->has('enable_html_minification')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // SETTINGS NAV — Consistent structure
    // =========================================================================

    public function test_master_settings_nav_contains_expected_structure(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.app'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('settingsNav.0', fn (Assert $item): Assert => $item
                    ->has('slug')
                    ->has('label')
                    ->has('href')
                )
            );
    }

    // =========================================================================
    // UPDATE — Authentication & Authorization
    // =========================================================================

    public function test_guests_cannot_update_master_settings(): void
    {
        $this->put(route('app.masters.settings.update', 'branding'), [

            'brand_name' => 'Test',
        ])->assertRedirect(route('login'));
    }

    public function test_admins_cannot_update_master_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('app.masters.settings.update', 'branding'), [

                'brand_name' => 'Test',
                'brand_website' => 'https://example.com',
                'logo' => 'logo.png',
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // UPDATE — Branding settings
    // =========================================================================

    public function test_branding_settings_update_requires_brand_name(): void
    {
        $this->actingAs($this->superUser)
            ->put(route('app.masters.settings.update', 'branding'), [

                'brand_name' => '',
            ])
            ->assertSessionHasErrors('brand_name');
    }

    public function test_super_user_can_update_branding_settings(): void
    {
        $this->actingAs($this->superUser)
            ->put(route('app.masters.settings.update', 'branding'), [

                'brand_name' => 'Test Brand',
                'brand_website' => 'https://example.com',
                'logo' => 'logo.png',
                'icon' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    // =========================================================================
    // UPDATE — Login security settings
    // =========================================================================

    public function test_super_user_can_update_login_security_settings(): void
    {
        $this->actingAs($this->superUser)
            ->put(route('app.masters.settings.update', 'login_security'), [

                'admin_login_url_slug' => 'admin',
                'limit_login_attempts_enabled' => true,
                'limit_login_attempts' => '5',
                'lockout_time' => '60',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    // =========================================================================
    // UPDATE — Debug settings
    // =========================================================================

    public function test_super_user_can_update_debug_settings(): void
    {
        $this->actingAs($this->superUser)
            ->put(route('app.masters.settings.update', 'debug'), [

                'enable_debugging' => false,
                'enable_debugging_bar' => false,
                'enable_html_minification' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    public function test_debugbar_middleware_is_registered_on_web_routes(): void
    {
        $webMiddleware = app('router')->getMiddlewareGroups()['web'] ?? [];

        $this->assertContains(
            EnableDebugbarForSuperUser::class,
            $webMiddleware,
        );
    }

    public function test_debugbar_middleware_enables_debugbar_for_super_users(): void
    {
        config(['debugbar.enabled' => true]);

        Debugbar::shouldReceive('enable')->once();
        Debugbar::shouldReceive('disable')->never();

        $request = Request::create('/admin');
        $request->setUserResolver(fn (): User => $this->superUser);

        $response = app(EnableDebugbarForSuperUser::class)->handle(
            $request,
            fn () => response('ok'),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_debugbar_middleware_disables_debugbar_for_non_super_users(): void
    {
        config(['debugbar.enabled' => true]);

        Debugbar::shouldReceive('disable')->once();
        Debugbar::shouldReceive('enable')->never();

        $request = Request::create('/admin');
        $request->setUserResolver(fn (): User => $this->admin);

        $response = app(EnableDebugbarForSuperUser::class)->handle(
            $request,
            fn () => response('ok'),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_debugbar_open_storage_callback_only_allows_super_users(): void
    {
        putenv('DEBUGBAR_OPEN_STORAGE=true');
        $_ENV['DEBUGBAR_OPEN_STORAGE'] = 'true';
        $_SERVER['DEBUGBAR_OPEN_STORAGE'] = 'true';

        config([
            'app.debug' => true,
            'debugbar.enabled' => true,
        ]);

        $callback = config('debugbar.storage.open');

        $this->assertIsCallable($callback);

        $superUserRequest = Request::create('/_debugbar/open');
        $superUserRequest->setUserResolver(fn (): User => $this->superUser);
        $this->assertTrue($callback($superUserRequest));

        $adminRequest = Request::create('/_debugbar/open');
        $adminRequest->setUserResolver(fn (): User => $this->admin);
        $this->assertFalse($callback($adminRequest));

        putenv('DEBUGBAR_OPEN_STORAGE');
        unset($_ENV['DEBUGBAR_OPEN_STORAGE'], $_SERVER['DEBUGBAR_OPEN_STORAGE']);
    }
}
