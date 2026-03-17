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

class TagCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_tags', 'add_tags', 'edit_tags', 'delete_tags', 'restore_tags'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'tags',
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
            'view_tags',
            'add_tags',
            'edit_tags',
            'delete_tags',
            'restore_tags',
        ]);
    }

    public function test_guests_are_redirected_from_tags_create_page(): void
    {
        $this->get(route('cms.tags.create'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_access_tags_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.tags.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/tags/create')
                ->has('initialValues')
                ->where('initialValues.status', 'draft')
                ->has('statusOptions')
                ->has('metaRobotsOptions')
            );
    }

    public function test_admin_can_access_tags_index_with_thumbnail_and_permalink_fields(): void
    {
        $tag = $this->createTag('Announcements');

        $this->actingAs($this->admin)
            ->get(route('cms.tags.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/tags/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.title', $tag->title)
                ->where('rows.data.0.slug', $tag->slug)
                ->where('rows.data.0.permalink_url', $tag->permalink_url)
                ->where('rows.data.0.featured_image_url', null)
            );
    }

    public function test_admin_can_access_tags_edit_page_with_initial_values(): void
    {
        $tag = $this->createTag('Laravel');

        $this->actingAs($this->admin)
            ->get(route('cms.tags.edit', $tag))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/tags/edit')
                ->where('tag.id', $tag->id)
                ->where('tag.title', $tag->title)
                ->where('initialValues.title', $tag->title)
                ->where('initialValues.slug', $tag->slug)
                ->where('initialValues.status', $tag->status)
            );
    }

    public function test_admin_can_store_a_tag(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cms.tags.store'), [
                'title' => 'New Tag',
                'slug' => 'new-tag',
                'content' => '<p>Tag description.</p>',
                'excerpt' => 'Short excerpt.',
                'feature_image' => '',
                'status' => 'published',
                'template' => '',
                'meta_title' => 'Meta Title',
                'meta_description' => 'Meta Description',
                'meta_robots' => '',
            ]);

        $tag = CmsPost::query()->where('slug', 'new-tag')->firstOrFail();

        $response->assertRedirect(route('cms.tags.edit', $tag));

        $this->assertSame(CmsPostType::TAG->value, $tag->type);
        $this->assertSame('New Tag', $tag->title);
        $this->assertSame('published', $tag->status);
    }

    public function test_admin_can_update_a_tag(): void
    {
        $tag = $this->createTag('Original Tag');

        $response = $this->actingAs($this->admin)
            ->put(route('cms.tags.update', $tag), [
                'title' => 'Updated Tag',
                'slug' => 'updated-tag',
                'content' => '<p>Updated description.</p>',
                'excerpt' => 'Updated excerpt.',
                'feature_image' => '',
                'status' => 'published',
                'template' => '',
                'meta_title' => 'Updated Meta',
                'meta_description' => 'Updated Description',
                'meta_robots' => '',
            ]);

        $response->assertRedirect(route('cms.tags.edit', $tag));

        $tag->refresh();

        $this->assertSame('Updated Tag', $tag->title);
        $this->assertSame('updated-tag', $tag->slug);
        $this->assertSame('published', $tag->status);
    }

    public function test_admin_cannot_store_tag_with_duplicate_slug(): void
    {
        $this->createTag('Existing Tag', 'existing-slug');

        $response = $this->actingAs($this->admin)
            ->post(route('cms.tags.store'), [
                'title' => 'Another Tag',
                'slug' => 'existing-slug',
                'content' => '',
                'excerpt' => '',
                'feature_image' => '',
                'status' => 'draft',
                'template' => '',
                'meta_title' => '',
                'meta_description' => '',
                'meta_robots' => '',
            ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_admin_can_soft_delete_a_tag(): void
    {
        $tag = $this->createTag('Tag to Delete');

        $response = $this->actingAs($this->admin)
            ->delete(route('cms.tags.destroy', $tag));

        $response->assertRedirect();

        $this->assertSoftDeleted('cms_posts', ['id' => $tag->id]);
    }

    private function createTag(string $title, ?string $slug = null): CmsPost
    {
        return CmsPost::query()->create([
            'title' => $title,
            'slug' => $slug ?? Str::slug($title).'-'.Str::random(6),
            'type' => CmsPostType::TAG->value,
            'status' => 'published',
            'visibility' => 'public',
            'author_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
    }
}
