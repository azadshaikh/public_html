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
use Modules\CMS\Models\DesignBlock;
use Tests\TestCase;

class DesignBlockCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (['view_design_blocks', 'add_design_blocks', 'edit_design_blocks', 'delete_design_blocks', 'restore_design_blocks'] as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                [
                    'display_name' => ucwords(str_replace('_', ' ', $permission)),
                    'group' => 'design_blocks',
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
            'view_design_blocks',
            'add_design_blocks',
            'edit_design_blocks',
            'delete_design_blocks',
            'restore_design_blocks',
        ]);
    }

    public function test_guests_are_redirected_from_design_blocks_index(): void
    {
        $this->get(route('cms.designblock.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_redirected_from_design_blocks_create_page(): void
    {
        $this->get(route('cms.designblock.create'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_access_design_blocks_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('cms.designblock.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/design-blocks/create')
                ->has('initialValues')
                ->where('initialValues.status', 'draft')
                ->where('initialValues.design_type', 'section')
                ->has('statusOptions')
                ->has('designTypeOptions')
                ->has('categoryOptions')
                ->has('designSystemOptions')
            );
    }

    public function test_admin_can_access_design_blocks_index_with_merged_preview_title_column(): void
    {
        $block = $this->createDesignBlock('Index Block');

        $this->actingAs($this->admin)
            ->get(route('cms.designblock.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/design-blocks/index')
                ->has('rows.data', 1)
                ->where('config.columns.1.key', 'title')
                ->where('config.columns.2.key', 'design_type')
                ->where('config.columns.3.key', 'category_name')
                ->where('config.columns.4.key', 'status')
                ->where('config.columns.5.key', 'created_at')
                ->where('rows.data.0.title', $block->title)
            );
    }

    public function test_design_blocks_index_exposes_filter_options_and_round_trips_filter_state(): void
    {
        $matchingBlock = $this->createDesignBlock('Filtered Block', overrides: [
            'status' => 'published',
            'created_at' => now(),
            'metadata' => [
                'design_type' => 'section',
                'block_type' => 'static',
                'design_system' => 'bootstrap',
                'category' => 'hero',
            ],
        ]);
        $this->createDesignBlock('Non Matching Block', overrides: [
            'metadata' => [
                'design_type' => 'component',
                'block_type' => 'static',
                'design_system' => 'tailwind',
                'category' => 'content',
            ],
        ]);
        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $this->actingAs($this->admin)
            ->get(route('cms.designblock.index', [
                'design_type' => 'section',
                'category_id' => 'hero',
                'design_system' => 'bootstrap',
                'created_at_from' => $from,
                'created_at_to' => $to,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/design-blocks/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.title', $matchingBlock->title)
                ->where('config.filters.0.options.section', 'Section')
                ->where('config.filters.1.options.hero', 'Hero Section')
                ->where('config.filters.2.options.bootstrap', 'Bootstrap')
                ->where('filters.design_type', 'section')
                ->where('filters.category_id', 'hero')
                ->where('filters.design_system', 'bootstrap')
                ->where('filters.created_at', $from.','.$to)
            );
    }

    public function test_admin_can_access_design_blocks_edit_page_with_initial_values(): void
    {
        $block = $this->createDesignBlock('My Test Block');

        $this->actingAs($this->admin)
            ->get(route('cms.designblock.edit', $block))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/design-blocks/edit')
                ->where('designBlock.id', $block->id)
                ->where('designBlock.title', $block->title)
                ->where('initialValues.title', $block->title)
                ->where('initialValues.status', $block->status)
                ->where('initialValues.design_type', 'section')
            );
    }

    public function test_admin_can_store_a_design_block(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cms.designblock.store'), [
                'title' => 'Hero Section Block',
                'slug' => 'hero-section-block',
                'description' => 'A hero section for landing pages.',
                'html' => '<div class="hero">Hello</div>',
                'css' => '.hero { color: red; }',
                'scripts' => '',
                'preview_image_url' => '',
                'design_type' => 'section',
                'design_system' => 'tailwind',
                'category_id' => 'hero',
                'status' => 'draft',
            ]);

        $block = DesignBlock::query()->where('slug', 'hero-section-block')->firstOrFail();

        $response->assertRedirect(route('cms.designblock.edit', $block));

        $this->assertSame('design_block', $block->type);
        $this->assertSame('Hero Section Block', $block->title);
        $this->assertSame('draft', $block->status);
        $this->assertSame('section', $block->design_type);
    }

    public function test_admin_can_update_a_design_block(): void
    {
        $block = $this->createDesignBlock('Original Block');

        $response = $this->actingAs($this->admin)
            ->put(route('cms.designblock.update', $block), [
                'title' => 'Updated Block',
                'slug' => 'updated-block',
                'description' => 'Updated description.',
                'html' => '<div class="updated">Updated</div>',
                'css' => '',
                'scripts' => '',
                'preview_image_url' => '',
                'design_type' => 'component',
                'design_system' => 'bootstrap',
                'category_id' => 'content',
                'status' => 'published',
            ]);

        $response->assertRedirect(route('cms.designblock.edit', $block));

        $block->refresh();

        $this->assertSame('Updated Block', $block->title);
        $this->assertSame('updated-block', $block->slug);
        $this->assertSame('published', $block->status);
        $this->assertSame('component', $block->design_type);
    }

    public function test_admin_cannot_store_design_block_without_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cms.designblock.store'), [
                'title' => '',
                'design_type' => '',
                'category_id' => '',
                'design_system' => '',
                'status' => '',
            ]);

        $response->assertSessionHasErrors(['title', 'design_type', 'category_id', 'design_system', 'status']);
    }

    public function test_admin_cannot_store_design_block_with_invalid_design_type(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('cms.designblock.store'), [
                'title' => 'Invalid Block',
                'slug' => '',
                'design_type' => 'invalid_type',
                'design_system' => 'bootstrap',
                'category_id' => 'hero',
                'status' => 'draft',
            ]);

        $response->assertSessionHasErrors('design_type');
    }

    public function test_admin_can_soft_delete_a_design_block(): void
    {
        $block = $this->createDesignBlock('Block to Delete');

        $response = $this->actingAs($this->admin)
            ->delete(route('cms.designblock.destroy', $block));

        $response->assertRedirect();

        $this->assertSoftDeleted('cms_posts', ['id' => $block->id]);
    }

    private function createDesignBlock(string $title, ?string $slug = null, array $overrides = []): DesignBlock
    {
        return DesignBlock::unguarded(fn (): DesignBlock => DesignBlock::create(array_merge([
            'title' => $title,
            'slug' => $slug ?? Str::slug($title).'-'.Str::random(6),
            'status' => 'draft',
            'author_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'metadata' => [
                'design_type' => 'section',
                'block_type' => 'static',
                'design_system' => 'bootstrap',
                'category' => 'hero',
            ],
        ], $overrides)));
    }
}
