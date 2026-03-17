<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use App\Enums\Status;
use App\Models\CustomMedia;
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

class PostCrudMigrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_posts', 'add_posts', 'edit_posts', 'delete_posts', 'restore_posts'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'posts',
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
            'view_posts',
            'add_posts',
            'edit_posts',
            'delete_posts',
            'restore_posts',
        ]);
    }

    public function test_guests_are_redirected_from_posts_create_page(): void
    {
        $this->get(route('cms.posts.create'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_access_posts_create_page(): void
    {
        $category = $this->createTerm(CmsPostType::CATEGORY, 'News');
        $tag = $this->createTerm(CmsPostType::TAG, 'Featured');

        $this->actingAs($this->admin)
            ->get(route('cms.posts.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/posts/create')
                ->has('initialValues')
                ->where('initialValues.status', 'draft')
                ->has('categoryOptions')
                ->has('tagOptions')
                ->has('authorOptions')
                ->has('statusOptions')
                ->has('visibilityOptions')
                ->has('metaRobotsOptions')
                ->has('templateOptions')
                ->where('categoryOptions.0.value', $category->id)
                ->where('tagOptions.0.value', $tag->id)
            );
    }

    public function test_admin_can_access_posts_index_page_with_listing_data_for_the_refactored_ui(): void
    {
        $category = $this->createTerm(CmsPostType::CATEGORY, 'Announcements');
        $post = $this->createPost('Index Ready Post');
        $featuredImage = $this->createMediaForPost($post, 'index-ready.jpg');

        $post->categories()->attach($category->id, ['term_type' => CmsPostType::CATEGORY->value]);
        $post->forceFill([
            'feature_image_id' => $featuredImage->id,
            'status' => 'published',
            'published_at' => now(),
        ])->save();

        $this->actingAs($this->admin)
            ->get(route('cms.posts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/posts/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.title', $post->title)
                ->where('rows.data.0.author_name', $this->admin->name)
                ->where('rows.data.0.featured_image_url', get_media_url($featuredImage, 'thumbnail', usePlaceholder: false))
                ->where('rows.data.0.status', 'published')
                ->where('rows.data.0.status_label', 'Published')
                ->where('rows.data.0.categories.0.title', $category->title)
                ->where('rows.data.0.categories_display', $category->title)
                ->has('rows.data.0.permalink_url')
            );
    }

    public function test_admin_can_access_posts_edit_page_with_initial_values(): void
    {
        $category = $this->createTerm(CmsPostType::CATEGORY, 'Guides');
        $tag = $this->createTerm(CmsPostType::TAG, 'Laravel');
        $post = $this->createPost('Migration Ready Post');
        $featuredImage = $this->createMediaForPost($post, 'featured-image.jpg');
        $post->categories()->attach($category->id, ['term_type' => CmsPostType::CATEGORY->value]);
        $post->tags()->attach($tag->id, ['term_type' => CmsPostType::TAG->value]);
        $post->forceFill(['feature_image_id' => $featuredImage->id])->save();

        $this->actingAs($this->admin)
            ->get(route('cms.posts.edit', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/posts/edit')
                ->where('post.id', $post->id)
                ->where('post.title', $post->title)
                ->where(
                    'post.featured_image_url',
                    get_media_url($featuredImage, 'thumbnail', usePlaceholder: false),
                )
                ->where('initialValues.title', $post->title)
                ->where('initialValues.slug', $post->slug)
                ->where('initialValues.content', $post->content)
                ->where('initialValues.excerpt', $post->excerpt)
                ->where('initialValues.meta_title', $post->meta_title)
                ->where('initialValues.meta_description', $post->meta_description)
                ->where('initialValues.og_title', $post->og_title)
                ->where('initialValues.schema', $post->schema)
                ->has('categoryOptions')
                ->has('tagOptions')
                ->has('authorOptions')
                ->where('authorOptions.0.value', $this->admin->id)
                ->has('statusOptions')
                ->has('visibilityOptions')
                ->has('metaRobotsOptions')
                ->has('templateOptions')
                ->where('initialValues.categories.0', $category->id)
                ->where('initialValues.tags.0', $tag->id)
                ->where('initialValues.author_id', $this->admin->id)
            );
    }

    public function test_post_edit_picker_request_includes_upload_settings(): void
    {
        $post = $this->createPost('Picker Ready Post');

        $this->actingAs($this->admin)
            ->get(route('cms.posts.edit', $post).'?picker=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/posts/edit')
                ->where('pickerFilters.picker', '1')
                ->where('uploadSettings.max_files_per_upload', (int) config('media.max_files_per_upload', 10))
                ->where('uploadSettings.upload_route', route('app.media.upload-media'))
            );
    }

    public function test_admin_can_store_a_post_from_the_migrated_form(): void
    {
        $category = $this->createTerm(CmsPostType::CATEGORY, 'Development');
        $tag = $this->createTerm(CmsPostType::TAG, 'Release');

        $response = $this->actingAs($this->admin)
            ->post(route('cms.posts.store'), [
                'title' => 'Migrated Post Create',
                'slug' => 'migrated-post-create',
                'content' => '<p>Created from migrated React form.</p>',
                'excerpt' => 'A concise excerpt.',
                'feature_image' => '',
                'is_featured' => false,
                'status' => 'draft',
                'visibility' => 'public',
                'post_password' => '',
                'password_hint' => '',
                'author_id' => $this->admin->id,
                'published_at' => '',
                'meta_title' => 'Meta Title',
                'meta_description' => 'Meta Description',
                'meta_robots' => '',
                'og_title' => 'OG Title',
                'og_description' => 'OG Description',
                'og_image' => 'https://example.com/social.jpg',
                'og_url' => 'https://example.com/migrated-post-create',
                'schema' => '<script type="application/ld+json">{"@context":"https://schema.org"}</script>',
                'template' => '',
                'categories' => [$category->id],
                'tags' => [$tag->id],
            ]);

        $createdPost = CmsPost::query()->where('slug', 'migrated-post-create')->firstOrFail();

        $response->assertRedirect(route('cms.posts.edit', $createdPost));

        $this->assertSame(CmsPostType::POST->value, $createdPost->type);
        $this->assertSame($category->id, $createdPost->category_id);
        $this->assertTrue($createdPost->categories()->whereKey($category->id)->exists());
        $this->assertTrue($createdPost->tags()->whereKey($tag->id)->exists());
    }

    public function test_admin_can_update_a_post_from_the_migrated_form(): void
    {
        $oldCategory = $this->createTerm(CmsPostType::CATEGORY, 'Old Category');
        $newCategory = $this->createTerm(CmsPostType::CATEGORY, 'New Category');
        $newTag = $this->createTerm(CmsPostType::TAG, 'Updated Tag');
        $post = $this->createPost('Original Post');
        $post->categories()->attach($oldCategory->id, ['term_type' => CmsPostType::CATEGORY->value]);

        $response = $this->actingAs($this->admin)
            ->put(route('cms.posts.update', $post), [
                'title' => 'Updated Post Title',
                'slug' => 'updated-post-title',
                'content' => '<p>Updated content.</p>',
                'excerpt' => 'Updated excerpt.',
                'feature_image' => '',
                'is_featured' => true,
                'status' => 'published',
                'visibility' => 'public',
                'post_password' => '',
                'password_hint' => '',
                'author_id' => $this->admin->id,
                'published_at' => '',
                'meta_title' => 'Updated Meta Title',
                'meta_description' => 'Updated Meta Description',
                'meta_robots' => '',
                'og_title' => 'Updated OG Title',
                'og_description' => 'Updated OG Description',
                'og_image' => 'https://example.com/updated-social.jpg',
                'og_url' => 'https://example.com/updated-post-title',
                'schema' => '<div>updated schema</div>',
                'template' => '',
                'categories' => [$newCategory->id],
                'tags' => [$newTag->id],
            ]);

        $response->assertRedirect(route('cms.posts.edit', $post));

        $post->refresh();

        $this->assertSame('Updated Post Title', $post->title);
        $this->assertSame('updated-post-title', $post->slug);
        $this->assertSame('published', $post->status);
        $this->assertTrue((bool) $post->is_featured);
        $this->assertSame($newCategory->id, $post->category_id);
        $this->assertTrue($post->categories()->whereKey($newCategory->id)->exists());
        $this->assertFalse($post->categories()->whereKey($oldCategory->id)->exists());
        $this->assertTrue($post->tags()->whereKey($newTag->id)->exists());
    }

    private function createTerm(CmsPostType $type, string $title): CmsPost
    {
        return CmsPost::query()->create([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'type' => $type->value,
            'status' => 'published',
            'visibility' => 'public',
            'author_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }

    private function createPost(string $title): CmsPost
    {
        return CmsPost::query()->create([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'type' => CmsPostType::POST->value,
            'status' => 'draft',
            'visibility' => 'public',
            'author_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'content' => '<p>Original content</p>',
            'excerpt' => 'Original excerpt',
            'meta_title' => 'Original meta title',
            'meta_description' => 'Original meta description',
            'og_title' => 'Original OG title',
            'schema' => '<script type="application/ld+json">{"@type":"Article"}</script>',
        ]);
    }

    private function createMediaForPost(CmsPost $post, string $fileName): CustomMedia
    {
        return CustomMedia::query()->create([
            'model_type' => CmsPost::class,
            'model_id' => $post->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'default',
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'status' => 'active',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }
}
