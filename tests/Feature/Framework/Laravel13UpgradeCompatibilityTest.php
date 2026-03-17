<?php

namespace Tests\Feature\Framework;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Menu;
use Modules\CMS\Models\Redirection;
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

    public function test_cache_serializable_classes_allow_expected_cms_payloads(): void
    {
        $cacheKey = 'laravel13-upgrade-compatibility';

        $page = (new CmsPost)->forceFill([
            'title' => 'Welcome',
            'slug' => 'welcome',
            'type' => 'page',
            'status' => 'published',
        ]);

        $menuItem = (new Menu)->forceFill([
            'name' => 'Home',
            'type' => Menu::TYPE_PAGE,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $menuItem->setRelation('page', $page);

        $menu = (new Menu)->forceFill([
            'name' => 'Primary',
            'type' => Menu::TYPE_CONTAINER,
            'location' => 'header',
            'is_active' => true,
        ]);
        $menu->setRelation('items', new EloquentCollection([$menuItem]));

        $redirection = (new Redirection)->forceFill([
            'source_url' => '/old-url',
            'target_url' => '/new-url',
            'match_type' => 'exact',
            'status' => 'active',
        ]);

        Cache::put($cacheKey, [
            'menu' => $menu,
            'posts' => new EloquentCollection([$page]),
            'redirections' => new EloquentCollection([$redirection]),
        ], now()->addMinute());

        $cached = Cache::get($cacheKey);

        Cache::forget($cacheKey);

        $this->assertIsArray($cached);
        $this->assertInstanceOf(Menu::class, $cached['menu']);
        $this->assertInstanceOf(EloquentCollection::class, $cached['posts']);
        $this->assertInstanceOf(CmsPost::class, $cached['posts']->first());
        $this->assertInstanceOf(EloquentCollection::class, $cached['redirections']);
        $this->assertInstanceOf(Redirection::class, $cached['redirections']->first());
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
