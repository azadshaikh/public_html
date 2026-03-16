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
                ->where('categoryOptions.0.value', $category->id)
                ->where('tagOptions.0.value', $tag->id)
            );
    }

    public function test_admin_can_access_posts_edit_page_with_initial_values(): void
    {
        $category = $this->createTerm(CmsPostType::CATEGORY, 'Guides');
        $tag = $this->createTerm(CmsPostType::TAG, 'Laravel');
        $post = $this->createPost('Migration Ready Post');
        $post->categories()->attach($category->id, ['term_type' => CmsPostType::CATEGORY->value]);
        $post->tags()->attach($tag->id, ['term_type' => CmsPostType::TAG->value]);

        $this->actingAs($this->admin)
            ->get(route('cms.posts.edit', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/posts/edit')
                ->where('post.id', $post->id)
                ->where('post.title', $post->title)
                ->where('initialValues.title', $post->title)
                ->where('initialValues.slug', $post->slug)
                ->where('initialValues.categories.0', $category->id)
                ->where('initialValues.tags.0', $tag->id)
                ->where('initialValues.author_id', $this->admin->id)
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
        ]);
    }
}
