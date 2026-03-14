<?php

namespace Modules\Cms\Tests\Feature;

use App\Models\User;
use App\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Cms\Models\CmsPage;
use Tests\TestCase;

class CmsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_away_from_the_cms_module(): void
    {
        $this->get(route('cms.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_and_filter_pages(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        CmsPage::query()->create($this->validPayload([
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
        ]));

        CmsPage::query()->create($this->validPayload([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]));

        $this->actingAs($user)
            ->get(route('cms.index', [
                'status' => 'published',
                'search' => 'home',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/index')
                ->where('module.name', 'CMS')
                ->where('filters.status', 'published')
                ->where('filters.search', 'home')
                ->has('pages.data', 1)
                ->where('pages.data.0.slug', 'home'));
    }

    public function test_authenticated_users_can_create_a_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('cms.store'), $this->validPayload())
            ->assertRedirect(route('cms.index'));

        $this->assertDatabaseHas('cms_pages', [
            'title' => 'Homepage',
            'slug' => 'homepage',
            'status' => 'published',
        ]);
    }

    public function test_authenticated_users_can_update_a_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $page = CmsPage::query()->create($this->validPayload());

        $this->actingAs($user)
            ->patch(route('cms.update', $page), $this->validPayload([
                'title' => 'Docs homepage',
                'slug' => 'docs-homepage',
                'is_featured' => false,
            ]))
            ->assertRedirect(route('cms.index'));

        $this->assertDatabaseHas('cms_pages', [
            'id' => $page->id,
            'title' => 'Docs homepage',
            'slug' => 'docs-homepage',
            'is_featured' => false,
        ]);
    }

    public function test_authenticated_users_can_delete_a_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $page = CmsPage::query()->create($this->validPayload());

        $this->actingAs($user)
            ->delete(route('cms.destroy', $page))
            ->assertRedirect(route('cms.index'));

        $this->assertDatabaseMissing('cms_pages', [
            'id' => $page->id,
        ]);
    }

    public function test_page_creation_requires_a_unique_slug(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        CmsPage::query()->create($this->validPayload());

        $this->actingAs($user)
            ->from(route('cms.create'))
            ->post(route('cms.store'), $this->validPayload())
            ->assertRedirect(route('cms.create'))
            ->assertSessionHasErrors(['slug']);
    }

    public function test_disabled_modules_redirect_back_to_the_modules_page(): void
    {
        $manifestPath = storage_path('framework/testing/modules-cms-disabled.json');

        File::ensureDirectoryExists(dirname($manifestPath));
        File::put($manifestPath, json_encode([
            'CMS' => 'disabled',
            'ChatBot' => 'enabled',
            'Todos' => 'enabled',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        config()->set('modules.manifest', $manifestPath);
        app()->forgetInstance(ModuleManager::class);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('cms.index'))
            ->assertRedirect(route('dashboard'));

        File::delete($manifestPath);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Homepage',
            'slug' => 'homepage',
            'summary' => 'The public-facing homepage for the sample CMS module.',
            'body' => 'This sample page gives the CMS module a realistic content record that can later grow into sections, blocks, SEO metadata, navigation controls, and publishing workflows.',
            'status' => 'published',
            'published_at' => '2026-03-11',
            'is_featured' => true,
        ], $overrides);
    }
}
