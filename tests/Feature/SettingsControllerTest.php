<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $adminRole = Role::findByName('administrator', 'web');
        $this->admin->assignRole($adminRole);
        $this->admin->givePermissionTo('manage_system_settings');

        $this->regularUser = User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
    }

    // =========================================================================
    // INDEX — Authentication & Authorization
    // =========================================================================

    public function test_guests_are_redirected_to_login_from_settings_index(): void
    {
        $this->get(route('app.settings.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_settings_index(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.settings.index'))
            ->assertStatus(401);
    }

    public function test_admin_with_permission_is_redirected_from_settings_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.settings.index'))
            ->assertRedirect();
    }

    // =========================================================================
    // GENERAL — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_general_settings(): void
    {
        $this->get(route('app.settings.general'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_general_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.settings.general'))
            ->assertStatus(401);
    }

    public function test_admin_can_access_general_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.settings.general'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/general')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('site_title')
                    ->has('tagline')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // LOCALIZATION — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_localization_settings(): void
    {
        $this->get(route('app.settings.localization'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_localization_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.settings.localization'))
            ->assertStatus(401);
    }

    public function test_admin_can_access_localization_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.settings.localization'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/localization')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('language')
                    ->has('date_format')
                    ->has('time_format')
                    ->has('timezone')
                )
                ->has('options', fn (Assert $options): Assert => $options
                    ->has('languages')
                    ->has('dateFormats')
                    ->has('timeFormats')
                    ->has('timezones')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // REGISTRATION — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_registration_settings(): void
    {
        $this->get(route('app.settings.registration'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_registration_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.settings.registration'))
            ->assertStatus(401);
    }

    public function test_admin_can_access_registration_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.settings.registration'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/registration')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('enable_registration')
                    ->has('default_role')
                    ->has('require_email_verification')
                    ->has('auto_approve')
                )
                ->has('options.roles')
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // SOCIAL AUTHENTICATION — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_social_authentication_settings(): void
    {
        $this->get(route('app.settings.social-authentication'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_social_authentication_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.settings.social-authentication'))
            ->assertStatus(401);
    }

    public function test_admin_can_access_social_authentication_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.settings.social-authentication'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/social-authentication')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('enable_social_authentication')
                    ->has('enable_google_authentication')
                    ->has('google_client_id')
                    ->has('google_client_secret')
                    ->has('enable_github_authentication')
                    ->has('github_client_id')
                    ->has('github_client_secret')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // SITE ACCESS PROTECTION — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_site_access_protection_settings(): void
    {
        $this->get(route('app.settings.site-access-protection'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_site_access_protection_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.settings.site-access-protection'))
            ->assertStatus(401);
    }

    public function test_admin_can_access_site_access_protection_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.settings.site-access-protection'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/site-access-protection')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('mode_enabled')
                    ->has('password')
                    ->has('protection_message')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // MAINTENANCE — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_maintenance_settings(): void
    {
        $this->get(route('app.settings.maintenance'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_maintenance_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.settings.maintenance'))
            ->assertStatus(401);
    }

    public function test_admin_can_access_maintenance_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.settings.maintenance'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/maintenance')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('mode_enabled')
                    ->has('maintenance_mode_type')
                    ->has('title')
                    ->has('message')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // COMING SOON — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_coming_soon_settings(): void
    {
        $this->get(route('app.settings.coming-soon'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_coming_soon_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.settings.coming-soon'))
            ->assertStatus(401);
    }

    public function test_admin_can_access_coming_soon_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.settings.coming-soon'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/coming-soon')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('enabled')
                    ->has('description')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // DEVELOPMENT — Authentication, Authorization & Props
    // =========================================================================

    public function test_guests_cannot_access_development_settings(): void
    {
        $this->get(route('app.settings.development'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_development_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('app.settings.development'))
            ->assertStatus(401);
    }

    public function test_admin_can_access_development_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.settings.development'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('settings/development')
                ->has('settings', fn (Assert $settings): Assert => $settings
                    ->has('mode_enabled')
                )
                ->has('settingsNav')
            );
    }

    // =========================================================================
    // SETTINGS NAV — Consistent across all sections
    // =========================================================================

    public function test_settings_nav_contains_expected_structure(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.settings.localization'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('settingsNav', 8)
                ->has('settingsNav.0', fn (Assert $item): Assert => $item
                    ->where('slug', 'general')
                    ->where('label', 'General')
                    ->has('href')
                )
                ->has('settingsNav.7', fn (Assert $item): Assert => $item
                    ->where('slug', 'development')
                    ->where('label', 'Development Mode')
                    ->has('href')
                )
            );
    }

    // =========================================================================
    // UPDATE — General settings
    // =========================================================================

    public function test_guests_cannot_update_settings(): void
    {
        $this->put(route('app.settings.update', 'general'), [

            'site_title' => 'Test Title',
        ])->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_update_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->put(route('app.settings.update', 'general'), [

                'site_title' => 'Test Title',
            ])
            ->assertStatus(401);
    }

    public function test_general_settings_update_requires_site_title(): void
    {
        $this->actingAs($this->admin)
            ->put(route('app.settings.update', 'general'), [

                'site_title' => '',
            ])
            ->assertSessionHasErrors('site_title');
    }

    public function test_admin_can_update_general_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('app.settings.update', 'general'), [

                'site_title' => 'Updated Site Title',
                'tagline' => 'Updated Tagline',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    // =========================================================================
    // UPDATE — Localization settings
    // =========================================================================

    public function test_admin_can_update_localization_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('app.settings.update', 'localization'), [

                'language' => 'en',
                'date_format' => 'd M Y',
                'time_format' => 'g:i a',
                'timezone' => 'UTC',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    // =========================================================================
    // UPDATE — Registration settings
    // =========================================================================

    public function test_admin_can_update_registration_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('app.settings.update', 'registration'), [

                'enable_registration' => true,
                'default_role' => '2',
                'require_email_verification' => false,
                'auto_approve' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    // =========================================================================
    // UPDATE — Maintenance settings
    // =========================================================================

    public function test_admin_can_update_maintenance_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('app.settings.update', 'maintenance'), [

                'mode_enabled' => false,
                'maintenance_mode_type' => 'frontend',
                'title' => 'Under Maintenance',
                'message' => 'We will be back soon.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    // =========================================================================
    // UPDATE — Coming soon settings
    // =========================================================================

    public function test_admin_can_update_coming_soon_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('app.settings.update', 'coming_soon'), [

                'enabled' => false,
                'description' => 'Coming soon!',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }

    // =========================================================================
    // UPDATE — Development settings
    // =========================================================================

    public function test_admin_can_update_development_settings(): void
    {
        $this->actingAs($this->admin)
            ->put(route('app.settings.update', 'development'), [

                'mode_enabled' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
    }
}
