<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\CMS\Enums\CmsPostType;
use Modules\CMS\Models\CmsPost;
use Tests\TestCase;

class PageCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_pages', 'add_pages', 'edit_pages', 'delete_pages', 'restore_pages'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'pages',
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
        $this->admin->givePermissionTo([
            'view_pages',
            'add_pages',
            'edit_pages',
            'delete_pages',
            'restore_pages',
        ]);
    }

    public function test_guests_are_redirected_from_pages_create_page(): void
    {
        $this->get(route('cms.pages.create'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_access_pages_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.pages.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/pages/create')
                ->has('initialValues')
                ->where('initialValues.status', 'draft')
                ->has('statusOptions')
                ->has('visibilityOptions')
                ->has('parentPageOptions')
                ->has('authorOptions')
                ->has('metaRobotsOptions')
                ->has('baseUrl')
            );
    }

    public function test_admin_can_access_pages_index_with_thumbnail_and_permalink_fields(): void
    {
        $page = $this->createPage('About Us');

        $this->actingAs($this->admin)
            ->get(route('cms.pages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->component('cms/pages/index')
                ->has('rows.data', 1)
                ->where('config.columns.1.key', 'title_with_meta')
                ->where('config.columns.2.key', 'status')
                ->where('config.columns.3.key', 'display_date')
                ->where('rows.data.0.title', $page->title)
                ->where('rows.data.0.permalink_url', $page->permalink_url)
                ->where('rows.data.0.featured_image_url', null)
            );
    }

    public function test_pages_index_exposes_filter_options_and_round_trips_filter_state(): void
    {
        $author = User::factory()->create([
            'first_name' => 'Page',
            'last_name' => 'Author',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $parent = $this->createPage('Parent Filter Page');
        $matchingPage = $this->createPage('Filtered Page', $parent->id, null, $author);
        $this->createPage('Non Matching Page');
        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $matchingPage->forceFill([
            'status' => 'published',
            'published_at' => now(),
        ])->save();

        $this->actingAs($this->admin)
            ->get(route('cms.pages.index', [
                'statuses' => ['published'],
                'author_id' => $author->id,
                'parent_id' => $parent->id,
                'published_at_from' => $from,
                'published_at_to' => $to,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->component('cms/pages/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.title', $matchingPage->title)
                ->where('config.filters.0.options.published', 'Published')
                ->where('config.filters.1.options.'.$author->id, $author->name)
                ->where('config.filters.2.options.'.$parent->id, $parent->title)
                ->where('filters.statuses.0', 'published')
                ->where('filters.author_id', (string) $author->id)
                ->where('filters.parent_id', (string) $parent->id)
                ->where('filters.published_at', $from.','.$to)
            );
    }

    public function test_admin_can_access_pages_edit_page_with_initial_values(): void
    {
        $parent = $this->createPage('Parent Page');
        $page = $this->createPage('Child Page', $parent->id);

        $this->actingAs($this->admin);
        $page->forceFill(['excerpt' => 'Updated page excerpt'])->save();

        $this->get(route('cms.pages.edit', $page))
            ->assertOk()
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->component('cms/pages/edit')
                ->where('page.id', $page->id)
                ->where('page.title', $page->title)
                ->where('page.permalink_url', url($page->permalink_url))
                ->where('page.revisions_count', 1)
                ->where('page.revisions.0.changes.0.field', 'Excerpt')
                ->where('page.revisions.0.changes.0.new_value', 'Updated page excerpt')
                ->where('initialValues.title', $page->title)
                ->where('initialValues.slug', $page->slug)
                ->where('initialValues.status', $page->status)
                ->where('initialValues.parent_id', (string) $parent->id)
            );
    }

    public function test_admin_can_store_a_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cms.pages.store'), [
                'title' => 'New Page',
                'slug' => 'new-page',
                'content' => '<p>Page content.</p>',
                'excerpt' => 'Short excerpt.',
                'feature_image' => '',
                'status' => 'published',
                'visibility' => 'public',
                'post_password' => '',
                'password_hint' => '',
                'author_id' => $this->admin->id,
                'published_at' => '',
                'parent_id' => '',
                'template' => '',
                'meta_title' => 'Meta Title',
                'meta_description' => 'Meta Description',
                'meta_robots' => '',
                'og_title' => '',
                'og_description' => '',
                'og_image' => '',
                'og_url' => '',
                'schema' => '',
            ]);

        $page = CmsPost::query()->where('slug', 'new-page')->firstOrFail();

        $response->assertRedirect(route('cms.pages.edit', $page));

        $this->assertSame(CmsPostType::PAGE->value, $page->type);
        $this->assertSame('New Page', $page->title);
        $this->assertSame('published', $page->status);
        $this->assertSame($this->admin->id, $page->author_id);
    }

    public function test_admin_can_update_a_page(): void
    {
        $page = $this->createPage('Original Page');

        $response = $this->actingAs($this->admin)
            ->put(route('cms.pages.update', $page), [
                'title' => 'Updated Page',
                'slug' => 'updated-page',
                'content' => '<p>Updated content.</p>',
                'excerpt' => 'Updated excerpt.',
                'feature_image' => '',
                'status' => 'published',
                'visibility' => 'public',
                'post_password' => '',
                'password_hint' => '',
                'author_id' => $this->admin->id,
                'published_at' => '',
                'parent_id' => '',
                'template' => '',
                'meta_title' => 'Updated Meta Title',
                'meta_description' => 'Updated Meta Description',
                'meta_robots' => '',
                'og_title' => 'OG Title',
                'og_description' => 'OG Description',
                'og_image' => '',
                'og_url' => '',
                'schema' => '',
            ]);

        $response->assertRedirect(route('cms.pages.edit', $page));

        $page->refresh();

        $this->assertSame('Updated Page', $page->title);
        $this->assertSame('updated-page', $page->slug);
        $this->assertSame('published', $page->status);
        $this->assertSame('OG Title', $page->og_title);
    }

    public function test_admin_cannot_store_page_with_duplicate_title(): void
    {
        $this->createPage('Existing Page');

        $response = $this->actingAs($this->admin)
            ->post(route('cms.pages.store'), [
                'title' => 'Existing Page',
                'slug' => 'another-page-'.Str::random(6),
                'content' => '',
                'excerpt' => '',
                'feature_image' => '',
                'status' => 'draft',
                'visibility' => 'public',
                'post_password' => '',
                'password_hint' => '',
                'author_id' => $this->admin->id,
                'published_at' => '',
                'parent_id' => '',
                'template' => '',
                'meta_title' => '',
                'meta_description' => '',
                'meta_robots' => '',
                'og_title' => '',
                'og_description' => '',
                'og_image' => '',
                'og_url' => '',
                'schema' => '',
            ]);

        $response->assertSessionHasErrors('title');
    }

    public function test_admin_can_soft_delete_a_page(): void
    {
        $page = $this->createPage('Page to Delete');

        $response = $this->actingAs($this->admin)
            ->delete(route('cms.pages.destroy', $page));

        $response->assertRedirect();

        $this->assertSoftDeleted('cms_posts', ['id' => $page->id]);
    }

    private function createPage(string $title, ?int $parentId = null, ?string $slug = null, ?User $author = null): CmsPost
    {
        $author ??= $this->admin;

        return CmsPost::query()->create([
            'title' => $title,
            'slug' => $slug ?? Str::slug($title).'-'.Str::random(6),
            'type' => CmsPostType::PAGE->value,
            'status' => 'published',
            'visibility' => 'public',
            'author_id' => $author->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'parent_id' => $parentId,
        ]);
    }
}
