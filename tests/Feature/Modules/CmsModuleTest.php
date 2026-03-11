<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use App\Modules\ModuleManager;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CmsModuleTest extends TestCase
{
    public function test_guests_are_redirected_away_from_the_cms_module(): void
    {
        $this->get(route('cms.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_sample_cms_module_dashboard(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('cms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cms/index')
                ->where('module.name', 'CMS')
                ->where('module.slug', 'cms')
                ->where('module.version', '1.0.0'));
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

        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('cms.index'))
            ->assertRedirect(route('modules.index'));

        File::delete($manifestPath);
    }
}
