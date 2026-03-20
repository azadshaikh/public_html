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
use Modules\CMS\Models\DesignBlock;
use Tests\TestCase;

class BuilderPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['theme.active' => 'default']);

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

    public function test_admin_can_view_react_builder_page_with_palette_and_imported_content_fallback(): void
    {
        $page = $this->createPage('Landing Page');
        $page->forceFill([
            'content' => '<section class="hero"><h1>Imported content</h1></section>',
        ])->save();

        $this->createDesignBlock('Hero Section', overrides: [
            'status' => 'published',
            'content' => '<section class="hero"><div class="container">Hero block</div></section>',
        ]);

        $this->actingAs($this->admin)
            ->get(route('cms.builder.edit', $page))
            ->assertOk()
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->component('cms/builder/edit')
                ->where('activeTheme.directory', 'default')
                ->where('page.id', $page->id)
                ->where('page.title', $page->title)
                ->where('page.editor_url', route('cms.pages.edit', $page))
                ->where('builderState.source', 'imported-content')
                ->where('builderState.items.0.label', 'Imported page content')
                ->where('builderState.items.0.html', '<section class="hero"><h1>Imported content</h1></section>')
                ->where('pickerMedia', null)
                ->where('pickerFilters', null)
                ->where('uploadSettings', null)
                ->has('palette.sections')
                ->has('palette.sections.0.items')
                ->where('palette.sections.0.items.0.source', 'theme')
            );
    }

    public function test_builder_page_returns_media_picker_props_when_picker_query_is_present(): void
    {
        $page = $this->createPage('Image Builder Page');

        $this->actingAs($this->admin)
            ->get(route('cms.builder.edit', ['page' => $page, 'picker' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $assert): Assert => $assert
                ->component('cms/builder/edit')
                ->where('page.id', $page->id)
                ->where('pickerFilters.picker', '1')
                ->where('pickerFilters.status', 'all')
                ->where('uploadSettings.upload_route', route('app.media.upload-media'))
                ->where('pickerStatistics.total', 0)
                ->where('pickerStatistics.trash', 0)
                ->has('pickerMedia.data', 0)
            );
    }

    public function test_builder_save_updates_page_content_css_js_and_structured_builder_state(): void
    {
        $page = $this->createPage('Builder Save Page');

        $response = $this->actingAs($this->admin)
            ->postJson(route('cms.builder.save', $page), [
                'content' => '<section data-test="builder">Saved content</section>',
                'css' => '.builder { color: rebeccapurple; }',
                'js' => 'window.builderLoaded = true;',
                'format' => 'pagebuilder',
                'builder_state' => [
                    'css' => '.builder { color: rebeccapurple; }',
                    'js' => 'window.builderLoaded = true;',
                    'items' => [
                        [
                            'uid' => 'builder-item-1',
                            'catalog_id' => 'sections-hero/test-item',
                            'type' => 'sections',
                            'category' => 'hero',
                            'label' => 'Hero Block',
                            'html' => '<section id="hero-block" class="hero shell"><h1>Saved content</h1><a href="/contact">Talk to us</a></section>',
                            'css' => '',
                            'js' => '',
                            'preview_image_url' => null,
                            'source' => 'custom',
                        ],
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Page saved successfully',
            ]);

        $page->refresh();

        $this->assertSame('<section data-test="builder">Saved content</section>', $page->content);
        $this->assertSame('.builder { color: rebeccapurple; }', $page->css);
        $this->assertSame('window.builderLoaded = true;', $page->js);
        $this->assertSame('Hero Block', $page->getMetadata('builder_v1.items.0.label'));
        $this->assertSame('sections-hero/test-item', $page->getMetadata('builder_v1.items.0.catalog_id'));
        $this->assertSame(
            '<section id="hero-block" class="hero shell"><h1>Saved content</h1><a href="/contact">Talk to us</a></section>',
            $page->getMetadata('builder_v1.items.0.html'),
        );
        $this->assertSame('custom', $page->getMetadata('builder_v1.items.0.source'));
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

    private function createDesignBlock(string $title, array $overrides = []): DesignBlock
    {
        return DesignBlock::unguarded(fn (): DesignBlock => DesignBlock::create(array_merge([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
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
