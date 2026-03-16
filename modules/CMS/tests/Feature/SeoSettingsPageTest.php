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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeoSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['manage_seo_settings', 'manage_cms_seo_settings'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'seo',
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
        $this->admin->givePermissionTo(['manage_seo_settings', 'manage_cms_seo_settings']);
    }

    public function test_guests_are_redirected_from_seo_settings_pages(): void
    {
        $this->get(route('seo.dashboard'))
            ->assertRedirect(route('login'));

        $this->get(route('seo.settings.titlesmeta'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_view_seo_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get(route('seo.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('seo/dashboard')
                ->where('titlesMetaHref', route('seo.settings.titlesmeta', ['section' => 'general']))
                ->has('stats.robots_txt_exists')
                ->has('quickLinks', 6)
            );
    }

    public function test_admin_can_view_react_seo_settings_pages(): void
    {
        $this->saveSetting('seo', 'separator_character', '|');
        $this->saveSetting('seo_local_seo', 'name', 'Xip One');
        $this->saveSetting('seo_social_media', 'twitter_username', '@xipone');
        $this->saveSetting('seo', 'enable_article_schema', 'true', 'boolean');
        $this->saveSetting('seo_robots', 'robots_txt', "User-agent: *\nDisallow:");

        $this->actingAs($this->admin)
            ->get(route('seo.settings.titlesmeta'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('seo/settings/titles-meta')
                ->where('activeSection', 'general')
                ->where('generalInitialValues.separator_character', '|')
                ->has('sections', 7)
            );

        $this->actingAs($this->admin)
            ->get(route('seo.settings.localseo'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('seo/settings/local-seo')
                ->where('initialValues.name', 'Xip One')
                ->has('businessTypeOptions')
                ->has('openingDayOptions')
            );

        $this->actingAs($this->admin)
            ->get(route('seo.settings.socialmedia'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('seo/settings/social-media')
                ->where('initialValues.twitter_username', '@xipone')
                ->has('twitterCardOptions', 2)
            );

        $this->actingAs($this->admin)
            ->get(route('seo.settings.schema'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('seo/settings/schema')
                ->where('initialValues.enable_article_schema', true)
            );

        $this->actingAs($this->admin)
            ->get(route('seo.settings.sitemap'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('seo/settings/sitemap')
                ->has('initialValues')
                ->has('sitemapStatus')
            );

        $this->actingAs($this->admin)
            ->get(route('seo.settings.robots'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('seo/settings/robots')
                ->where('initialValues.robots_txt', fn (string $value): bool => str_contains($value, 'User-agent: *'))
                ->has('robotsUrl')
                ->has('sitemapUrl')
            );

        $this->actingAs($this->admin)
            ->get(route('seo.settings.importexport'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('seo/settings/import-export')
                ->has('seoGroups')
            );
    }

    public function test_admin_can_update_local_seo_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('seo.settings.localseo'))
            ->post(route('seo.settings.localseo.update'), [
                'is_schema' => true,
                'type' => 'Organization',
                'business_type' => 'LocalBusiness',
                'name' => 'Xip One Digital',
                'description' => 'Growth-focused SEO and web partner.',
                'street_address' => '123 Market Street',
                'locality' => 'New Delhi',
                'region' => 'Delhi',
                'postal_code' => '110001',
                'country_code' => 'IN',
                'phone' => '+91-9999999999',
                'email' => 'hello@example.com',
                'url' => 'https://one.xip.net.in',
                'price_range' => '$$',
                'facebook_url' => 'https://facebook.com/xipone',
                'twitter_url' => 'https://x.com/xipone',
                'linkedin_url' => 'https://linkedin.com/company/xipone',
                'instagram_url' => 'https://instagram.com/xipone',
                'youtube_url' => 'https://youtube.com/@xipone',
                'founding_date' => '2022-01-01',
                'section' => 'local_seo',
            ]);

        $response->assertRedirect(route('seo.settings.localseo').'?section=local_seo');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'group' => 'seo_local_seo',
            'key' => 'name',
            'value' => 'Xip One Digital',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'seo_local_seo',
            'key' => 'country_code',
            'value' => 'IN',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'seo_local_seo',
            'key' => 'is_schema',
            'value' => '1',
            'type' => 'boolean',
        ]);
    }

    public function test_admin_can_update_titles_meta_post_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('seo.settings.titlesmeta', ['section' => 'posts']))
            ->post(route('seo.settings.update', [
                'master_group' => 'cms',
                'file_name' => 'posts',
            ]), [
                'section' => 'posts',
                'permalink_base' => 'insights',
                'permalink_structure' => '%postname%',
                'title_template' => '%title% %separator% %site_title%',
                'description_template' => '%excerpt%',
                'robots_default' => 'index,follow',
                'enable_multiple_categories' => true,
                'enable_pagination_indexing' => false,
            ]);

        $response->assertRedirect(route('seo.settings.titlesmeta', ['section' => 'posts']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'group' => 'seo_posts',
            'key' => 'permalink_base',
            'value' => 'insights',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'seo_posts',
            'key' => 'title_template',
            'value' => '%title% %separator% %site_title%',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'seo_posts',
            'key' => 'enable_multiple_categories',
            'value' => '1',
            'type' => 'boolean',
        ]);
    }

    public function test_user_without_permissions_cannot_access_seo_settings(): void
    {
        $user = User::factory()->create([
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('seo.settings.localseo'))
            ->assertForbidden();
    }

    private function saveSetting(string $group, string $key, string $value, string $type = 'string'): void
    {
        Settings::query()->updateOrCreate([
            'group' => $group,
            'key' => $key,
        ], [
            'value' => $value,
            'type' => $type,
        ]);
    }
}
