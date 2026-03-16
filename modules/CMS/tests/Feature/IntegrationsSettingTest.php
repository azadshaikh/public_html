<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Settings;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IntegrationsSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $adsTxtPath;

    private bool $adsTxtExisted;

    private string $adsTxtOriginalContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Permission::query()->firstOrCreate(
            ['name' => 'manage_integrations_seo_settings', 'guard_name' => 'web'],
            [
                'display_name' => 'Manage Integrations SEO Settings',
                'group' => 'seo',
                'module_slug' => 'cms',
            ],
        );

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole(Role::findByName('administrator', 'web'));
        $this->admin->givePermissionTo('manage_integrations_seo_settings');

        $this->adsTxtPath = public_path('ads.txt');
        $this->adsTxtExisted = File::exists($this->adsTxtPath);
        $this->adsTxtOriginalContent = $this->adsTxtExisted ? (string) File::get($this->adsTxtPath) : '';
    }

    protected function tearDown(): void
    {
        if ($this->adsTxtExisted) {
            File::put($this->adsTxtPath, $this->adsTxtOriginalContent);
        } elseif (File::exists($this->adsTxtPath)) {
            File::delete($this->adsTxtPath);
        }

        parent::tearDown();
    }

    public function test_guests_are_redirected_from_integrations_settings(): void
    {
        $this->get(route('cms.integrations.index'))
            ->assertRedirect(route('login'));
    }

    public function test_integrations_index_redirects_to_webmaster_tools_route(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.integrations.index'))
            ->assertRedirect(route('cms.integrations.webmastertools'));
    }

    public function test_admin_can_view_integrations_settings(): void
    {
        $this->saveSetting('google_analytics', '<script>window.dataLayer = [];</script>');
        $this->saveSetting('google_adsense_enabled', 'true', 'boolean');

        $this->actingAs($this->admin)
            ->get(route('cms.integrations.googleadsense'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/integrations/index')
                ->where('activeSection', 'google_adsense')
                ->where('statuses.google_analytics', true)
                ->where('statuses.google_adsense', true)
                ->where('settings.google_analytics.google_analytics', '<script>window.dataLayer = [];</script>')
                ->where('settings.google_adsense.google_adsense_enabled', true)
            );
    }

    public function test_admin_can_update_google_analytics_integration(): void
    {
        $snippet = '<script async src="https://www.googletagmanager.com/gtag/js?id=G-123456"></script>';

        $response = $this->actingAs($this->admin)
            ->from(route('cms.integrations.googleanalytics'))
            ->post(route('cms.integrations.googleanalytics.update'), [
                'google_analytics' => $snippet,
                'section' => 'google_analytics',
            ]);

        $response->assertRedirect(route('cms.integrations.googleanalytics'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'group' => 'seo_integrations',
            'key' => 'google_analytics',
            'value' => $snippet,
        ]);
    }

    public function test_admin_can_update_google_adsense_settings_and_ads_txt(): void
    {
        $adsTxt = 'google.com, pub-1234567890, DIRECT, f08c47fec0942fa0';
        $snippet = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890"></script>';

        $response = $this->actingAs($this->admin)
            ->from(route('cms.integrations.googleadsense'))
            ->post(route('cms.integrations.googleadsense.update'), [
                'google_adsense_enabled' => true,
                'google_adsense_code' => $snippet,
                'google_adsense_ads_txt' => $adsTxt,
                'google_adsense_hide_for_logged_in' => true,
                'google_adsense_hide_on_homepage' => false,
                'section' => 'google_adsense',
            ]);

        $response->assertRedirect(route('cms.integrations.googleadsense'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'group' => 'seo_integrations',
            'key' => 'google_adsense_enabled',
            'value' => 'true',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'seo_integrations',
            'key' => 'google_adsense_code',
            'value' => $snippet,
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'seo_integrations',
            'key' => 'google_adsense_hide_for_logged_in',
            'value' => 'true',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'seo_integrations',
            'key' => 'google_adsense_hide_on_homepage',
            'value' => 'false',
        ]);

        $this->assertTrue(File::exists($this->adsTxtPath));
        $this->assertSame($adsTxt, File::get($this->adsTxtPath));
    }

    public function test_user_without_permission_cannot_access_integrations_settings(): void
    {
        $user = User::factory()->create([
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('cms.integrations.webmastertools'))
            ->assertForbidden();
    }

    private function saveSetting(string $key, string $value, string $type = 'string'): void
    {
        Settings::query()->updateOrCreate([
            'group' => 'seo_integrations',
            'key' => $key,
        ], [
            'value' => $value,
            'type' => $type,
        ]);
    }
}
