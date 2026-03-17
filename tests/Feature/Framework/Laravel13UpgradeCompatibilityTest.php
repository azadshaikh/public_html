<?php

namespace Tests\Feature\Framework;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Laravel13UpgradeCompatibilityTest extends TestCase
{
    public function test_prevent_request_forgery_is_configured_with_expected_exceptions(): void
    {
        $adminPrefix = trim((string) config('app.admin_slug', ''), '/');
        $adminPrefix = $adminPrefix !== '' ? $adminPrefix : 'admin';

        $middleware = new PreventRequestForgery($this->app, $this->app->make(Encrypter::class));
        $excludedPaths = $middleware->getExcludedPaths();

        $this->assertContains($adminPrefix.'/media/upload-media', $excludedPaths);
        $this->assertContains($adminPrefix.'/media/media-details/update', $excludedPaths);
    }

    public function test_cache_unserialization_of_objects_remains_disabled(): void
    {
        $this->assertFalse(config('cache.serializable_classes'));

        $cacheConfig = File::get(config_path('cache.php'));

        $this->assertStringNotContainsString('use Modules\\CMS\\Models\\CmsPost;', $cacheConfig);
        $this->assertStringNotContainsString('use Modules\\CMS\\Models\\Menu;', $cacheConfig);
        $this->assertStringNotContainsString('use Modules\\CMS\\Models\\Redirection;', $cacheConfig);
    }

    public function test_cms_permalink_route_does_not_reference_removed_markdown_middleware(): void
    {
        $route = Route::getRoutes()->getByName('cms.view');

        $this->assertNotNull($route);
        $this->assertNotContains(
            'Spatie\\MarkdownResponse\\Middleware\\ProvideMarkdownResponse',
            $route->gatherMiddleware()
        );
    }
}
