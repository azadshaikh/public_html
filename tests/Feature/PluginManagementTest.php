<?php

namespace Tests\Feature;

use App\Models\User;
use App\Plugins\PluginManager;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PluginManagementTest extends TestCase
{
    protected string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = storage_path('framework/testing/plugins-management.json');

        File::ensureDirectoryExists(dirname($this->manifestPath));
        File::put($this->manifestPath, json_encode([
            'CMS' => 'enabled',
            'ChatBot' => 'enabled',
            'Todos' => 'disabled',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        config()->set('plugins.manifest', $this->manifestPath);
        app()->forgetInstance(PluginManager::class);
        app()->singleton(PluginManager::class, fn ($app): PluginManager => new PluginManager(
            files: $app['files'],
            config: $app['config'],
        ));
    }

    protected function tearDown(): void
    {
        File::delete($this->manifestPath);

        parent::tearDown();
    }

    public function test_guests_are_redirected_from_the_plugin_management_page(): void
    {
        $this->get(route('plugins.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_plugin_management_page(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('plugins.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('plugins/index')
                ->has('managedPlugins', 3)
                ->where('managedPlugins.0.name', 'CMS')
                ->where('managedPlugins.0.status', 'enabled')
                ->where('managedPlugins.0.enabled', true)
                ->where('managedPlugins.1.name', 'ChatBot')
                ->where('managedPlugins.1.status', 'enabled')
                ->where('managedPlugins.2.name', 'Todos')
                ->where('managedPlugins.2.status', 'disabled'));
    }

    public function test_authenticated_users_can_update_plugin_statuses(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('plugins.update'), [
                'plugins' => [
                    'CMS' => 'disabled',
                    'ChatBot' => 'enabled',
                    'Todos' => 'enabled',
                ],
            ])
            ->assertRedirect(route('plugins.index'))
            ->assertSessionHas('status', 'Plugin settings updated.');

        $this->assertSame([
            'CMS' => 'disabled',
            'ChatBot' => 'enabled',
            'Todos' => 'enabled',
        ], json_decode((string) File::get($this->manifestPath), true, 512, JSON_THROW_ON_ERROR));
    }
}
