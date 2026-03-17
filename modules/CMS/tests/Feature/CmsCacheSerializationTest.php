<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Menu;
use Modules\CMS\Models\Redirection;
use Modules\CMS\Services\CmsPostCacheService;
use Modules\CMS\Services\MenuCacheService;
use Modules\CMS\Services\RedirectionCacheService;
use Tests\TestCase;

class CmsCacheSerializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_cms_post_cache_stores_arrays_but_returns_models(): void
    {
        CmsPost::query()->create([
            'type' => 'post',
            'title' => 'Cached Post',
            'slug' => 'cached-post',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $service = resolve(CmsPostCacheService::class);
        $posts = $service->getPopularPosts(limit: 3);
        $cached = Cache::get(CmsPostCacheService::POPULAR_POSTS_PREFIX.'post_3');

        $this->assertInstanceOf(CmsPost::class, $posts->first());
        $this->assertIsArray($cached);
        $this->assertIsArray($cached[0] ?? null);
        $this->assertSame('cached-post', $cached[0]['slug'] ?? null);
    }

    public function test_menu_cache_stores_arrays_but_returns_models(): void
    {
        $menu = Menu::query()->create([
            'type' => Menu::TYPE_CONTAINER,
            'name' => 'Primary',
            'title' => 'Primary',
            'location' => 'primary',
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $menu->id,
            'name' => 'Home',
            'title' => 'Home',
            'url' => '/',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $service = resolve(MenuCacheService::class);
        $cachedMenu = $service->getByLocation('primary');
        $cachedItems = $service->getMenuItems($menu->id);
        $menuPayload = Cache::get(MenuCacheService::LOCATION_PREFIX.'primary');
        $itemsPayload = Cache::get(MenuCacheService::ITEMS_PREFIX.$menu->id);

        $this->assertInstanceOf(Menu::class, $cachedMenu);
        $this->assertInstanceOf(Menu::class, $cachedItems->first());
        $this->assertIsArray($menuPayload);
        $this->assertIsArray($menuPayload['items'] ?? null);
        $this->assertIsArray($itemsPayload);
        $this->assertSame('Home', $itemsPayload[0]['title'] ?? null);
    }

    public function test_redirection_cache_stores_arrays_but_returns_models(): void
    {
        Redirection::query()->create([
            'redirect_type' => 301,
            'url_type' => 'internal',
            'match_type' => 'exact',
            'source_url' => '/old-path',
            'target_url' => '/new-path',
            'status' => 'active',
        ]);

        $service = resolve(RedirectionCacheService::class);
        $redirections = $service->getActive();
        $match = $service->findBySourceUrl('/old-path');
        $payload = Cache::get(RedirectionCacheService::ACTIVE_CACHE_KEY);

        $this->assertInstanceOf(Redirection::class, $redirections->first());
        $this->assertInstanceOf(Redirection::class, $match);
        $this->assertIsArray($payload);
        $this->assertSame('/old-path', $payload[0]['source_url'] ?? null);
    }
}
