<?php

namespace Modules\CMS\Observers;

use App\Support\CacheInvalidation;
use Modules\CMS\Models\Redirection;
use Modules\CMS\Services\RedirectionCacheService;

/**
 * Observer to automatically invalidate redirection cache on model changes.
 */
class RedirectionObserver
{
    public function __construct(
        private readonly RedirectionCacheService $cacheService
    ) {}

    public function created(Redirection $redirection): void
    {
        $this->cacheService->invalidate('Redirection created: '.$redirection->source_url);
        $this->invalidateFrontendCaches();
    }

    public function updated(Redirection $redirection): void
    {
        $this->cacheService->invalidate('Redirection updated: '.$redirection->source_url);
        $this->invalidateFrontendCaches();
    }

    public function deleted(Redirection $redirection): void
    {
        $this->cacheService->invalidate('Redirection deleted: '.$redirection->source_url);
        $this->invalidateFrontendCaches();
    }

    public function restored(Redirection $redirection): void
    {
        $this->cacheService->invalidate('Redirection restored: '.$redirection->source_url);
        $this->invalidateFrontendCaches();
    }

    public function forceDeleted(Redirection $redirection): void
    {
        $this->cacheService->invalidate('Redirection force deleted: '.$redirection->source_url);
        $this->invalidateFrontendCaches();
    }

    /**
     * Redirections affect routing, so changes invalidate frontend caches.
     */
    protected function invalidateFrontendCaches(): void
    {
        CacheInvalidation::touch('Redirection changed');
    }
}
