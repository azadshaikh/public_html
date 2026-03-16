<?php

namespace Modules\CMS\Observers;

use App\Support\CacheInvalidation;
use Modules\CMS\Models\Menu;
use Modules\CMS\Services\MenuCacheService;

/**
 * Observer to automatically invalidate menu cache on model changes.
 */
class MenuObserver
{
    public function __construct(
        private readonly MenuCacheService $cacheService
    ) {}

    public function created(Menu $menu): void
    {
        $this->cacheService->invalidateMenu($menu);
        $this->invalidateFrontendCaches();
    }

    public function updated(Menu $menu): void
    {
        // Track original location if it changed
        $originalLocation = $menu->wasChanged('location') ? $menu->getOriginal('location') : null;
        $this->cacheService->invalidateMenu($menu, $originalLocation);
        $this->invalidateFrontendCaches();
    }

    public function deleted(Menu $menu): void
    {
        $this->cacheService->invalidateMenu($menu);
        $this->invalidateFrontendCaches();
    }

    public function restored(Menu $menu): void
    {
        $this->cacheService->invalidateMenu($menu);
        $this->invalidateFrontendCaches();
    }

    public function forceDeleted(Menu $menu): void
    {
        $this->cacheService->invalidateMenu($menu);
        $this->invalidateFrontendCaches();
    }

    /**
     * Menus appear on all pages, so any menu change invalidates frontend caches.
     */
    protected function invalidateFrontendCaches(): void
    {
        CacheInvalidation::touch('Menu changed');
    }
}
