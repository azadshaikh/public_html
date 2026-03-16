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
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CMS\Enums\CmsPostType;
use Modules\CMS\Models\CmsPost;
use Tests\TestCase;

class DefaultPagesSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Permission::query()->firstOrCreate(
            ['name' => 'manage_default_pages', 'guard_name' => 'web'],
            [
                'display_name' => 'Manage Default Pages',
                'group' => 'settings',
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
        $this->admin->givePermissionTo('manage_default_pages');
    }

    public function test_guests_are_redirected_from_default_pages_settings(): void
    {
        $this->get(route('cms.settings.default-pages'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_view_default_pages_settings(): void
    {
        $homePage = $this->createPage('Home Page');
        $blogPage = $this->createPage('Blog Page');

        $this->saveSetting('home_page', (string) $homePage->id);
        $this->saveSetting('blogs_page', (string) $blogPage->id);
        $this->saveSetting('blog_base_url', 'journal');
        $this->saveSetting('blog_same_as_home', 'false', 'boolean');

        $this->actingAs($this->admin)
            ->get(route('cms.settings.default-pages'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/settings/default-pages')
                ->has('pageOptions', 3)
                ->where('settings.home_page', (string) $homePage->id)
                ->where('settings.blogs_page', (string) $blogPage->id)
                ->where('settings.blog_base_url', 'journal')
                ->where('settings.blog_same_as_home', false)
                ->where('publishedPageCount', 2)
            );
    }

    public function test_admin_can_update_default_pages_settings(): void
    {
        $homePage = $this->createPage('Home Page');
        $blogPage = $this->createPage('Blog Page');
        $contactPage = $this->createPage('Contact Page');

        $response = $this->actingAs($this->admin)
            ->put(route('cms.settings.default-pages.update'), [
                'home_page' => (string) $homePage->id,
                'blogs_page' => (string) $blogPage->id,
                'blog_base_url' => 'newsroom',
                'contact_page' => (string) $contactPage->id,
                'about_page' => '',
                'privacy_policy_page' => '',
                'terms_of_service_page' => '',
                'blog_same_as_home' => false,
            ]);

        $response->assertRedirect(route('cms.settings.default-pages'));
        $response->assertSessionHas('success', 'Default pages settings updated successfully.');

        $this->assertDatabaseHas('settings', [
            'group' => 'cms_default_pages',
            'key' => 'home_page',
            'value' => (string) $homePage->id,
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'cms_default_pages',
            'key' => 'blogs_page',
            'value' => (string) $blogPage->id,
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'cms_default_pages',
            'key' => 'blog_base_url',
            'value' => 'newsroom',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'cms_default_pages',
            'key' => 'contact_page',
            'value' => (string) $contactPage->id,
        ]);
    }

    public function test_blog_same_as_home_clears_blog_page_and_defaults_empty_slug(): void
    {
        $homePage = $this->createPage('Home Page');
        $blogPage = $this->createPage('Blog Page');

        $response = $this->actingAs($this->admin)
            ->put(route('cms.settings.default-pages.update'), [
                'home_page' => (string) $homePage->id,
                'blogs_page' => (string) $blogPage->id,
                'blog_base_url' => '',
                'contact_page' => '',
                'about_page' => '',
                'privacy_policy_page' => '',
                'terms_of_service_page' => '',
                'blog_same_as_home' => true,
            ]);

        $response->assertRedirect(route('cms.settings.default-pages'));

        $this->assertDatabaseHas('settings', [
            'group' => 'cms_default_pages',
            'key' => 'blogs_page',
            'value' => '',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'cms_default_pages',
            'key' => 'blog_base_url',
            'value' => 'blog',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'cms_default_pages',
            'key' => 'blog_same_as_home',
            'value' => 'true',
            'type' => 'boolean',
        ]);
    }

    public function test_user_without_permission_cannot_access_default_pages_settings(): void
    {
        $user = User::factory()->create([
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('cms.settings.default-pages'))
            ->assertForbidden();
    }

    private function createPage(string $title): CmsPost
    {
        return CmsPost::query()->create([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'type' => CmsPostType::PAGE->value,
            'status' => 'published',
            'visibility' => 'public',
            'author_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function saveSetting(string $key, string $value, string $type = 'string'): void
    {
        Settings::query()->updateOrCreate([
            'group' => 'cms_default_pages',
            'key' => $key,
        ], [
            'value' => $value,
            'type' => $type,
        ]);
    }
}
