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
        $tag = $this->createTerm(CmsPostType::TAG, 'Launch');
        $post = $this->createPost('Index Ready Post');
        $featuredImage = $this->createMediaForPost($post, 'index-ready.jpg');

        $post->categories()->attach($category->id, ['term_type' => CmsPostType::CATEGORY->value]);
        $post->tags()->attach($tag->id, ['term_type' => CmsPostType::TAG->value]);
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
                ->missing('config.actions')
                ->missing('empty_state_config')
                ->has('rows.data', 1)
                ->where('config.columns.1.key', 'title_with_meta')
                ->where('config.columns.2.key', 'categories_display')
                ->where('config.columns.3.key', 'status')
                ->where('config.columns.4.key', 'display_date')
                ->where('config.filters.0.options.published', 'Published')
                ->where('config.filters.1.options.'.$this->admin->id, $this->admin->name)
                ->where('config.filters.2.options.'.$category->id, $category->title)
                ->where('config.filters.3.options.'.$tag->id, $tag->title)
                ->has('rows.data.0', fn (Assert $row): Assert => $row
                    ->where('title', $post->title)
                    ->where('author_name', $this->admin->name)
                    ->where('featured_image_url', get_media_url($featuredImage, 'thumbnail', usePlaceholder: false))
                    ->where('status', 'published')
                    ->where('status_label', 'Published')
                    ->where('categories.0.title', $category->title)
                    ->has('permalink_url')
                    ->missing('content')
                    ->missing('excerpt')
                    ->missing('slug')
                    ->missing('title_with_meta')
                    ->missing('categories_display')
                    ->missing('published_at')
                    ->missing('updated_at_formatted')
                    ->missing('status_class')
                    ->missing('actions')
                    ->missing('seo_data')
                    ->missing('metadata')
                    ->missing('deleted_at')
                    ->etc()
                )
            );
    }

    public function test_posts_index_payload_budget_stays_within_the_custom_page_contract(): void
    {
        $category = $this->createTerm(CmsPostType::CATEGORY, 'Announcements');
        $post = $this->createPost('Budget Ready Post');
        $featuredImage = $this->createMediaForPost($post, 'budget-ready.jpg');

        $post->categories()->attach($category->id, ['term_type' => CmsPostType::CATEGORY->value]);
        $post->forceFill([
            'feature_image_id' => $featuredImage->id,
            'status' => 'published',
            'published_at' => now(),
        ])->save();

        $response = $this->actingAs($this->admin)
            ->get(route('cms.posts.index'))
            ->assertOk();

        $featurePayloadSize = $this->jsonPayloadSize([
            'config' => $response->inertiaProps('config'),
            'rows' => $response->inertiaProps('rows'),
            'filters' => $response->inertiaProps('filters'),
            'statistics' => $response->inertiaProps('statistics'),
            'empty_state_config' => $response->inertiaProps('empty_state_config'),
        ]);

        $firstRowPayloadSize = $this->jsonPayloadSize($response->inertiaProps('rows.data.0'));

        $this->assertLessThan(
            14000,
            $featurePayloadSize,
            sprintf('Expected the CMS posts index feature payload to stay lean; received %d bytes.', $featurePayloadSize),
        );

        $this->assertLessThan(
            1500,
            $firstRowPayloadSize,
            sprintf('Expected the CMS posts index row payload to stay lean; received %d bytes.', $firstRowPayloadSize),
        );
    }

    public function test_posts_index_applies_relationship_and_date_filters_and_round_trips_filter_state(): void
    {
        $author = User::factory()->create([
            'first_name' => 'Filter',
            'last_name' => 'Author',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $category = $this->createTerm(CmsPostType::CATEGORY, 'Filtering Category');
        $tag = $this->createTerm(CmsPostType::TAG, 'Filtering Tag');
        $matchingPost = $this->createPost('Matching Filtered Post', $author);
        $nonMatchingPost = $this->createPost('Non Matching Post');
        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $matchingPost->categories()->attach($category->id, ['term_type' => CmsPostType::CATEGORY->value]);
        $matchingPost->tags()->attach($tag->id, ['term_type' => CmsPostType::TAG->value]);
        $matchingPost->forceFill([
            'category_id' => $category->id,
            'status' => 'published',
            'published_at' => now(),
        ])->save();

        $nonMatchingPost->forceFill([
            'status' => 'draft',
            'published_at' => null,
        ])->save();

        $this->actingAs($this->admin)
            ->get(route('cms.posts.index', [
                'author_id' => $author->id,
                'category_ids' => [$category->id],
                'tag_ids' => [$tag->id],
                'statuses' => ['published'],
                'published_at_from' => $from,
                'published_at_to' => $to,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/posts/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.title', $matchingPost->title)
                ->where('filters.author_id', (string) $author->id)
                ->where('filters.published_at', $from.','.$to)
                ->where('filters.statuses.0', 'published')
                ->where('filters.category_ids.0', (string) $category->id)
                ->where('filters.tag_ids.0', (string) $tag->id)
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

        $this->actingAs($this->admin);
        $post->forceFill(['excerpt' => 'Revised excerpt copy'])->save();

        $this->get(route('cms.posts.edit', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/posts/edit')
                ->where('post.id', $post->id)
                ->where('post.title', $post->title)
                ->where('post.permalink_url', url($post->permalink_url))
                ->where('post.revisions_count', 2)
                ->where('post.revisions.0.changes.0.field', 'Excerpt')
                ->where('post.revisions.0.changes.0.new_value', 'Revised excerpt copy')
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

    private function createPost(string $title, ?User $author = null): CmsPost
    {
        $author ??= $this->admin;

        return CmsPost::query()->create([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'type' => CmsPostType::POST->value,
            'status' => 'draft',
            'visibility' => 'public',
            'author_id' => $author->id,
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

    private function jsonPayloadSize(mixed $value): int
    {
        return strlen(json_encode($value, JSON_THROW_ON_ERROR));
    }
}
