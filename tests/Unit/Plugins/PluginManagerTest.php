<?php

namespace Tests\Unit\Plugins;

use App\Plugins\PluginManager;
use App\Plugins\Support\PluginManifest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PluginManagerTest extends TestCase
{
    public function test_it_discovers_all_sample_plugins_listed_in_the_root_manifest(): void
    {
        $plugins = $this->app->make(PluginManager::class)
            ->enabled()
            ->map(fn (PluginManifest $plugin): string => $plugin->slug)
            ->all();

        $this->assertEqualsCanonicalizing(['chatbot', 'cms', 'todos'], $plugins);
    }

    public function test_it_discovers_the_sample_cms_plugin(): void
    {
        $plugin = $this->app->make(PluginManager::class)
            ->enabled()
            ->first(fn (PluginManifest $plugin): bool => $plugin->slug === 'cms');

        $this->assertNotNull($plugin);
        $this->assertSame('CMS', $plugin->name);
        $this->assertSame('Plugins\\Cms\\', $plugin->namespace);
        $this->assertSame('cms/', $plugin->inertiaNamespace());
    }

    public function test_it_only_enables_plugins_listed_in_the_root_manifest(): void
    {
        $manifestPath = storage_path('framework/testing/plugins-test.json');

        File::ensureDirectoryExists(dirname($manifestPath));
        File::put($manifestPath, json_encode([
            'CMS' => 'disabled',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        config()->set('plugins.manifest', $manifestPath);

        $pluginManager = new PluginManager(
            files: app('files'),
            config: app('config'),
        );

        $this->assertCount(0, $pluginManager->enabled());

        File::delete($manifestPath);
    }

    public function test_it_enables_plugins_using_their_display_name_status_map(): void
    {
        $manifestPath = storage_path('framework/testing/plugins-test.json');

        File::ensureDirectoryExists(dirname($manifestPath));
        File::put($manifestPath, json_encode([
            'CMS' => 'enabled',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        config()->set('plugins.manifest', $manifestPath);

        $pluginManager = new PluginManager(
            files: app('files'),
            config: app('config'),
        );

        $this->assertCount(1, $pluginManager->enabled());
        $this->assertSame('cms', $pluginManager->enabled()->first()?->slug);

        File::delete($manifestPath);
    }

    public function test_plugin_routes_are_registered_with_the_web_middleware_group(): void
    {
        collect(['cms.index', 'chatbot.index', 'todos.index'])
            ->each(function (string $routeName): void {
                $route = app('router')->getRoutes()->getByName($routeName);

                $this->assertInstanceOf(Route::class, $route);
                $this->assertContains('web', $route->gatherMiddleware());
                $this->assertContains('auth', $route->gatherMiddleware());
                $this->assertContains('verified', $route->gatherMiddleware());
            });
    }
}
