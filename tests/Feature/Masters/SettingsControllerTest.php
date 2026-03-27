<?php

declare(strict_types=1);

namespace Tests\Feature\Masters;

use App\Enums\Status;
use App\Http\Middleware\EnableDebugbarForSuperUser;
use App\Http\Middleware\EnsureSuperUserAccess;
use App\Models\Role;
use App\Models\User;
use App\Support\Debugbar\OpenStorageResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $originalEnvironmentContents;

    private User $superUser;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnvironmentContents = (string) file_get_contents(
            app()->environmentFilePath(),
        );

        Queue::fake();

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

    protected function tearDown(): void
    {
        file_put_contents(
            app()->environmentFilePath(),
            $this->originalEnvironmentContents,
        );

        parent::tearDown();
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

    public function test_super_user_can_request_branding_media_picker_props(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.branding', ['picker' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('master-settings/branding')
                ->has('pickerMedia.data')
                ->where('pickerFilters.picker', '1')
                ->has('uploadSettings', fn (Assert $uploadSettings): Assert => $uploadSettings
                    ->has('upload_route')
                    ->has('max_size_mb')
                    ->has('friendly_file_types')
                    ->etc()
                )
                ->has('pickerStatistics', fn (Assert $pickerStatistics): Assert => $pickerStatistics
                    ->has('total')
                    ->has('trash')
                )
            );
    }

    // =========================================================================
    // THEME — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_theme_settings(): void
    {
        $this->get(route('app.masters.settings.theme'))
            ->assertRedirect(route('login'));
    }

    public function test_admins_cannot_access_theme_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.masters.settings.theme'))
            ->assertForbidden();
    }

    public function test_super_user_can_access_theme_settings(): void
    {
        $this->actingAs($this->superUser)
            ->get(route('app.masters.settings.theme'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('master-settings/theme')
                ->where('settings.admin_theme', 'default')
                ->has('options.themes', 8)
                ->where('options.themes.1.value', 'swiss')
                ->where('options.themes.2.value', 'green')
                ->where('options.themes.3.value', 'zen')
                ->where('options.themes.4.value', 'vista')
                ->where('options.themes.5.value', 'violet')
                ->where('options.themes.6.value', 'blue')
                ->where('options.themes.7.value', 'claude')
                ->has('settingsNav')
                ->where('ui.adminTheme', 'default')
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
                    ->has('max_files_per_upload')
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

    public function test_super_user_can_update_media_settings(): void
    {
        $this->actingAs($this->superUser)
            ->put(route('app.masters.settings.update', 'media'), [
                'max_file_name_length' => '100',
                'max_files_per_upload' => '12',
                'max_upload_size' => '20',
                'allowed_file_types' => 'image/png,image/jpg,image/jpeg,image/gif,image/webp,image/svg+xml,image/x-icon,image/bmp,video/mp4,video/webm,video/x-webm,video/avi,video/mov,video/wmv,video/x-matroska,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,text/csv',
                'image_optimization' => true,
                'image_quality' => '70',
                'thumbnail_width' => '300',
                'small_width' => '500',
                'medium_width' => '700',
                'large_width' => '1200',
                'xlarge_width' => '1920',
                'delete_trashed' => true,
                'delete_trashed_days' => '7',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('100', get_env_value('MEDIA_MAX_FILE_NAME_LENGTH'));
        $this->assertSame('12', get_env_value('MEDIA_MAX_FILES_PER_UPLOAD'));
        $this->assertSame('20', get_env_value('MEDIA_MAX_SIZE_IN_MB'));
        $this->assertSame('70', get_env_value('MEDIA_IMAGE_QUALITY'));
        $this->assertSame('300', get_env_value('MEDIA_THUMBNAIL_WIDTH'));
        $this->assertSame('500', get_env_value('MEDIA_SMALL_WIDTH'));
        $this->assertSame('700', get_env_value('MEDIA_MEDIUM_WIDTH'));
        $this->assertSame('true', get_env_value('MEDIA_AUTO_DELETE_TRASHED'));
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

    public function test_super_user_can_update_theme_settings(): void
    {
        $this->actingAs($this->superUser)
            ->put(route('app.masters.settings.update', 'theme'), [
                'admin_theme' => 'claude',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'key' => 'theme_admin_theme',
            'value' => 'claude',
        ]);
    }

    public function test_theme_settings_update_requires_valid_theme(): void
    {
        $this->actingAs($this->superUser)
            ->put(route('app.masters.settings.update', 'theme'), [
                'admin_theme' => 'midnight-neon',
            ])
            ->assertSessionHasErrors('admin_theme');
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

    public function test_super_user_can_disable_storage_boolean_settings(): void
    {
        $this->actingAs($this->superUser)
            ->put(route('app.masters.settings.update', 'storage'), [
                'storage_driver' => 'ftp',
                'root_folder' => 'uploads',
                'max_storage_size' => '1024',
                'storage_cdn_url' => '',
                'ftp_host' => 'ftp.example.test',
                'ftp_username' => 'ftp-user',
                'ftp_password' => 'ftp-secret',
                'ftp_root' => '/',
                'ftp_port' => '21',
                'ftp_passive' => false,
                'ftp_timeout' => '30',
                'ftp_ssl' => false,
                'ftp_ssl_mode' => 'explicit',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('false', get_env_value('FTP_PASSIVE'));
        $this->assertSame('false', get_env_value('FTP_SSL'));

        $this->actingAs($this->superUser)
            ->put(route('app.masters.settings.update', 'storage'), [
                'storage_driver' => 's3',
                'root_folder' => 'uploads',
                'max_storage_size' => '1024',
                'storage_cdn_url' => 'https://cdn.example.test',
                'access_key' => 'example-key',
                'secret_key' => 'example-secret',
                'bucket' => 'example-bucket',
                'region' => 'ap-south-1',
                'endpoint' => 'https://s3.example.test',
                'use_path_style_endpoint' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('FALSE', get_env_value('AWS_USE_PATH_STYLE_ENDPOINT'));
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

        $debugbar = app(LaravelDebugbar::class);
        $debugbar->disable();

        $request = Request::create('/admin');
        $request->setUserResolver(fn (): User => $this->superUser);

        $response = app(EnableDebugbarForSuperUser::class)->handle(
            $request,
            fn () => response('ok'),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($debugbar->isEnabled());
    }

    public function test_debugbar_middleware_disables_debugbar_for_non_super_users(): void
    {
        config(['debugbar.enabled' => true]);

        $debugbar = app(LaravelDebugbar::class);
        $debugbar->enable();

        $request = Request::create('/admin');
        $request->setUserResolver(fn (): User => $this->admin);

        $response = app(EnableDebugbarForSuperUser::class)->handle(
            $request,
            fn () => response('ok'),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($debugbar->isEnabled());
    }

    public function test_debugbar_open_storage_callback_only_allows_super_users(): void
    {
        config([
            'app.debug' => true,
            'debugbar.enabled' => true,
            'debugbar.storage_open' => true,
        ]);

        $callback = config('debugbar.storage.open');

        $this->assertSame(OpenStorageResolver::class, $callback);

        $superUserRequest = Request::create('/_debugbar/open');
        $superUserRequest->setUserResolver(fn (): User => $this->superUser);
        $this->assertTrue($callback::resolve($superUserRequest));

        $adminRequest = Request::create('/_debugbar/open');
        $adminRequest->setUserResolver(fn (): User => $this->admin);
        $this->assertFalse($callback::resolve($adminRequest));
    }

    public function test_debugbar_routes_use_common_super_user_middleware(): void
    {
        $this->assertContains(
            EnsureSuperUserAccess::class,
            config('debugbar.route_middleware', []),
        );
    }
}
