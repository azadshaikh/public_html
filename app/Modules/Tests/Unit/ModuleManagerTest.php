<?php

namespace App\Modules\Tests\Unit;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleManifest;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleManagerTest extends TestCase
{
    public function test_it_discovers_all_sample_modules_listed_in_the_root_manifest(): void
    {
        $modules = $this->app->make(ModuleManager::class)
            ->enabled()
            ->map(fn (ModuleManifest $module): string => $module->slug)
            ->all();

        $this->assertEqualsCanonicalizing(['chatbot', 'cms', 'platform', 'releasemanager', 'todos'], $modules);
    }

    public function test_it_discovers_the_sample_cms_module(): void
    {
        $module = $this->app->make(ModuleManager::class)
            ->enabled()
            ->first(fn (ModuleManifest $module): bool => $module->slug === 'cms');

        $this->assertNotNull($module);
        $this->assertSame('CMS', $module->name);
        $this->assertSame('Modules\\CMS\\', $module->namespace);
        $this->assertNull($module->author);
        $this->assertNull($module->homepage);
        $this->assertNull($module->icon);
        $this->assertSame('cms/', $module->inertiaNamespace());
        $this->assertSame('/cms', $module->url());
    }

    public function test_it_exposes_optional_author_homepage_and_icon_metadata_from_module_manifests(): void
    {
        $module = $this->app->make(ModuleManager::class)
            ->enabled()
            ->first(fn (ModuleManifest $module): bool => $module->slug === 'chatbot');

        $this->assertNotNull($module);
        $this->assertSame('AsteroDigital', $module->author);
        $this->assertSame('https://asterodigital.com', $module->homepage);
        $this->assertStringContainsString('<svg', (string) $module->icon);

        $sharedModule = collect($this->app->make(ModuleManager::class)->sharedData()['items'])
            ->firstWhere('slug', 'chatbot');

        $this->assertSame('AsteroDigital', $sharedModule['author']);
        $this->assertSame('https://asterodigital.com', $sharedModule['homepage']);
        $this->assertStringContainsString('<svg', (string) $sharedModule['icon']);
    }

    public function test_it_only_enables_modules_listed_in_the_root_manifest(): void
    {
        $manifestPath = storage_path('framework/testing/modules-test.json');

        File::ensureDirectoryExists(dirname($manifestPath));
        File::put($manifestPath, json_encode([
            'CMS' => 'disabled',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        config()->set('modules.manifest', $manifestPath);

        $moduleManager = new ModuleManager(
            files: resolve(Filesystem::class),
            config: resolve(Repository::class),
        );

        $this->assertCount(0, $moduleManager->enabled());

        File::delete($manifestPath);
    }

    public function test_it_enables_modules_using_their_display_name_status_map(): void
    {
        $manifestPath = storage_path('framework/testing/modules-test.json');

        File::ensureDirectoryExists(dirname($manifestPath));
        File::put($manifestPath, json_encode([
            'CMS' => 'enabled',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        config()->set('modules.manifest', $manifestPath);

        $moduleManager = new ModuleManager(
            files: resolve(Filesystem::class),
            config: resolve(Repository::class),
        );

        $this->assertCount(1, $moduleManager->enabled());
        $this->assertSame('cms', $moduleManager->enabled()->first()?->slug);

        File::delete($manifestPath);
    }

    public function test_it_treats_a_missing_modules_manifest_as_all_modules_disabled(): void
    {
        $manifestPath = storage_path('framework/testing/missing-modules-test.json');

        File::delete($manifestPath);

        config()->set('modules.manifest', $manifestPath);

        $moduleManager = new ModuleManager(
            files: resolve(Filesystem::class),
            config: resolve(Repository::class),
        );

        $this->assertCount(0, $moduleManager->enabled());
    }

    public function test_it_treats_an_empty_modules_manifest_as_all_modules_disabled(): void
    {
        $manifestPath = storage_path('framework/testing/modules-empty-test.json');

        File::ensureDirectoryExists(dirname($manifestPath));
        File::put($manifestPath, '');

        config()->set('modules.manifest', $manifestPath);

        $moduleManager = new ModuleManager(
            files: resolve(Filesystem::class),
            config: resolve(Repository::class),
        );

        $this->assertCount(0, $moduleManager->enabled());

        File::delete($manifestPath);
    }

    public function test_module_routes_are_registered_with_the_web_middleware_group(): void
    {
        collect(['cms.pages.index', 'chatbot.index', 'app.todos.index'])
            ->each(function (string $routeName): void {
                $route = resolve(Router::class)->getRoutes()->getByName($routeName);

                $this->assertInstanceOf(Route::class, $route);
                $this->assertContains('web', $route->gatherMiddleware());
                $this->assertContains('auth', $route->gatherMiddleware());
                $this->assertContains('verified', $route->gatherMiddleware());
            });
    }
}
