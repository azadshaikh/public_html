<?php

namespace App\Modules\Tests\Feature;

use App\Models\User;
use App\Modules\ModuleManager;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    protected string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = storage_path('framework/testing/modules-management.json');

        File::ensureDirectoryExists(dirname($this->manifestPath));
        File::put($this->manifestPath, json_encode([
            'CMS' => 'enabled',
            'ChatBot' => 'enabled',
            'Todos' => 'disabled',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        config()->set('modules.manifest', $this->manifestPath);
        app()->forgetInstance(ModuleManager::class);
        app()->singleton(ModuleManager::class, fn ($app): ModuleManager => new ModuleManager(
            files: $app['files'],
            config: $app['config'],
        ));
    }

    protected function tearDown(): void
    {
        File::delete($this->manifestPath);

        parent::tearDown();
    }

    public function test_guests_are_redirected_from_the_module_management_page(): void
    {
        $this->get(route('modules.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_module_management_page(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('modules/index')
                ->has('managedModules', 3)
                ->where('managedModules.0.name', 'CMS')
                ->where('managedModules.0.version', '1.0.0')
                ->where('managedModules.0.status', 'enabled')
                ->where('managedModules.0.enabled', true)
                ->missing('managedModules.0.slug')
                ->missing('managedModules.0.url')
                ->missing('managedModules.0.inertiaNamespace')
                ->where('managedModules.1.name', 'ChatBot')
                ->where('managedModules.1.status', 'enabled')
                ->where('managedModules.2.name', 'Todos')
                ->where('managedModules.2.status', 'disabled'));
    }

    public function test_authenticated_users_can_update_module_statuses(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('modules.update'), [
                'modules' => [
                    'CMS' => 'disabled',
                    'ChatBot' => 'enabled',
                    'Todos' => 'enabled',
                ],
            ])
            ->assertRedirect(route('modules.index'))
            ->assertSessionHas('status', 'Module settings updated.');

        $this->assertSame([
            'CMS' => 'disabled',
            'ChatBot' => 'enabled',
            'Todos' => 'enabled',
        ], json_decode((string) File::get($this->manifestPath), true, 512, JSON_THROW_ON_ERROR));
    }
}
