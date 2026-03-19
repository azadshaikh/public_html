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

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_categories', 'add_categories', 'edit_categories', 'delete_categories', 'restore_categories'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'categories',
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
            'view_categories',
            'add_categories',
            'edit_categories',
            'delete_categories',
            'restore_categories',
        ]);
    }

    public function test_guests_are_redirected_from_categories_create_page(): void
    {
        $this->get(route('cms.categories.create'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_access_categories_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.categories.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/categories/create')
                ->has('initialValues')
                ->where('initialValues.status', 'draft')
                ->has('statusOptions')
                ->has('parentCategoryOptions')
                ->has('metaRobotsOptions')
            );
    }

    public function test_admin_can_access_categories_index_with_thumbnail_and_permalink_fields(): void
    {
        $category = $this->createCategory('Product Updates');

        $this->actingAs($this->admin)
            ->get(route('cms.categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/categories/index')
                ->has('rows.data', 1)
                ->where('config.columns.1.key', 'title_with_meta')
                ->where('config.columns.2.key', 'posts_count')
                ->where('config.columns.3.key', 'status')
                ->where('config.columns.4.key', 'display_date')
                ->where('rows.data.0.title', $category->title)
                ->where('rows.data.0.slug', $category->slug)
                ->where('rows.data.0.permalink_url', $category->permalink_url)
                ->where('rows.data.0.featured_image_url', null)
            );
    }

    public function test_categories_index_exposes_filter_options_and_round_trips_filter_state(): void
    {
        $author = User::factory()->create([
            'first_name' => 'Category',
            'last_name' => 'Author',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $parent = $this->createCategory('Parent Category');
        $matchingCategory = $this->createCategory('Filtered Category', $parent->id, null, $author);
        $this->createCategory('Non Matching Category');
        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $matchingCategory->forceFill([
            'status' => 'published',
            'created_at' => now(),
        ])->save();

        $this->actingAs($this->admin)
            ->get(route('cms.categories.index', [
                'statuses' => ['published'],
                'parent_id' => $parent->id,
                'author_id' => $author->id,
                'created_at_from' => $from,
                'created_at_to' => $to,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/categories/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.title', $matchingCategory->title)
                ->where('config.filters.0.options.published', 'Published')
                ->where('config.filters.1.options.'.$parent->id, $parent->title)
                ->where('config.filters.2.options.'.$author->id, $author->name)
                ->where('filters.statuses.0', 'published')
                ->where('filters.parent_id', (string) $parent->id)
                ->where('filters.author_id', (string) $author->id)
                ->where('filters.created_at', $from.','.$to)
            );
    }

    public function test_admin_can_access_categories_edit_page_with_initial_values(): void
    {
        $parent = $this->createCategory('Parent Category');
        $category = $this->createCategory('Child Category', $parent->id);

        $this->actingAs($this->admin);
        $category->forceFill(['excerpt' => 'Updated category excerpt'])->save();

        $this->get(route('cms.categories.edit', $category))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/categories/edit')
                ->where('category.id', $category->id)
                ->where('category.title', $category->title)
                ->where('category.permalink_url', url($category->permalink_url))
                ->where('category.revisions_count', 1)
                ->where('category.revisions.0.changes.0.field', 'Excerpt')
                ->where('category.revisions.0.changes.0.new_value', 'Updated category excerpt')
                ->where('initialValues.title', $category->title)
                ->where('initialValues.slug', $category->slug)
                ->where('initialValues.status', $category->status)
                ->where('initialValues.parent_id', (string) $parent->id)
            );
    }

    public function test_admin_can_store_a_category(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cms.categories.store'), [
                'title' => 'New Category',
                'slug' => 'new-category',
                'content' => '<p>Category description.</p>',
                'excerpt' => 'Short excerpt.',
                'feature_image' => '',
                'status' => 'published',
                'parent_id' => '',
                'template' => '',
                'meta_title' => 'Meta Title',
                'meta_description' => 'Meta Description',
                'meta_robots' => '',
            ]);

        $category = CmsPost::query()->where('slug', 'new-category')->firstOrFail();

        $response->assertRedirect(route('cms.categories.edit', $category));

        $this->assertSame(CmsPostType::CATEGORY->value, $category->type);
        $this->assertSame('New Category', $category->title);
        $this->assertSame('published', $category->status);
    }

    public function test_admin_can_update_a_category(): void
    {
        $category = $this->createCategory('Original Category');

        $response = $this->actingAs($this->admin)
            ->put(route('cms.categories.update', $category), [
                'title' => 'Updated Category',
                'slug' => 'updated-category',
                'content' => '<p>Updated description.</p>',
                'excerpt' => 'Updated excerpt.',
                'feature_image' => '',
                'status' => 'published',
                'parent_id' => '',
                'template' => '',
                'meta_title' => 'Updated Meta Title',
                'meta_description' => 'Updated Meta Description',
                'meta_robots' => '',
            ]);

        $response->assertRedirect(route('cms.categories.edit', $category));

        $category->refresh();

        $this->assertSame('Updated Category', $category->title);
        $this->assertSame('updated-category', $category->slug);
        $this->assertSame('published', $category->status);
    }

    public function test_admin_cannot_store_category_with_duplicate_slug(): void
    {
        $this->createCategory('Existing Category', null, 'existing-slug');

        $response = $this->actingAs($this->admin)
            ->post(route('cms.categories.store'), [
                'title' => 'Another Category',
                'slug' => 'existing-slug',
                'content' => '',
                'excerpt' => '',
                'feature_image' => '',
                'status' => 'draft',
                'parent_id' => '',
                'template' => '',
                'meta_title' => '',
                'meta_description' => '',
                'meta_robots' => '',
            ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_admin_can_soft_delete_a_category(): void
    {
        $category = $this->createCategory('Category to Delete');

        $response = $this->actingAs($this->admin)
            ->delete(route('cms.categories.destroy', $category));

        $response->assertRedirect();

        $this->assertSoftDeleted('cms_posts', ['id' => $category->id]);
    }

    private function createCategory(string $title, ?int $parentId = null, ?string $slug = null, ?User $author = null): CmsPost
    {
        $author ??= $this->admin;

        return CmsPost::query()->create([
            'title' => $title,
            'slug' => $slug ?? Str::slug($title).'-'.Str::random(6),
            'type' => CmsPostType::CATEGORY->value,
            'status' => 'published',
            'visibility' => 'public',
            'author_id' => $author->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'parent_id' => $parentId,
        ]);
    }
}
