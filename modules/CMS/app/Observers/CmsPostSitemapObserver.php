<?php

namespace Modules\CMS\Observers;

use App\Support\CacheInvalidation;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Jobs\GenerateSitemapJob;
use Modules\CMS\Models\CmsPost;

class CmsPostSitemapObserver
{
    /**
     * Statuses that should be considered "public" for sitemap purposes.
     */
    protected array $publicStatuses = ['published', 'scheduled'];

    /**
     * Mapping of post types to sitemap types.
     */
    protected array $typeMapping = [
        'post' => 'posts',
        'page' => 'pages',
        'category' => 'categories',
        'tag' => 'tags',
    ];

    /**
     * Handle the CmsPost "created" event.
     */
    public function created(CmsPost $cmsPost): void
    {
        // Only for public items
        if (CacheInvalidation::affectsPublic($cmsPost, null, $this->publicStatuses)) {
            $this->dispatchSitemapJob($cmsPost);
        }
    }

    /**
     * Handle the CmsPost "updated" event.
     */
    public function updated(CmsPost $cmsPost): void
    {
        $previousValues = ['status' => $cmsPost->getOriginal('status')];

        // Status transitions that affect public visibility
        if ($cmsPost->wasChanged('status') && CacheInvalidation::affectsPublic($cmsPost, $previousValues, $this->publicStatuses)) {
            $this->dispatchSitemapJob($cmsPost);

            return;
        }

        // Deleting/restoring a public item affects the sitemap
        if ($cmsPost->wasChanged('deleted_at') && CacheInvalidation::affectsPublic($cmsPost, $previousValues, $this->publicStatuses)) {
            $this->dispatchSitemapJob($cmsPost);

            return;
        }

        // URL / indexing-related changes: only regenerate if the item is public
        if ($cmsPost->wasChanged(['slug', 'meta_robots'])) {
            $status = (string) ($cmsPost->getAttribute('status') ?? '');
            $originalStatus = (string) ($cmsPost->getOriginal('status') ?? '');

            if (in_array($status, $this->publicStatuses, true) || in_array($originalStatus, $this->publicStatuses, true)) {
                $this->dispatchSitemapJob($cmsPost);
            }
        }
    }

    /**
     * Handle the CmsPost "deleted" event.
     */
    public function deleted(CmsPost $cmsPost): void
    {
        // Only if it was public
        if (CacheInvalidation::affectsPublic($cmsPost, ['status' => $cmsPost->getOriginal('status')], $this->publicStatuses)) {
            $this->dispatchSitemapJob($cmsPost);
        }
    }

    /**
     * Handle the CmsPost "restored" event.
     */
    public function restored(CmsPost $cmsPost): void
    {
        // Only if it is public
        if (CacheInvalidation::affectsPublic($cmsPost, ['status' => $cmsPost->getOriginal('status')], $this->publicStatuses)) {
            $this->dispatchSitemapJob($cmsPost);
        }
    }

    /**
     * Handle the CmsPost "force deleted" event.
     */
    public function forceDeleted(CmsPost $cmsPost): void
    {
        // Only if it was public
        if (CacheInvalidation::affectsPublic($cmsPost, ['status' => $cmsPost->getOriginal('status')], $this->publicStatuses)) {
            $this->dispatchSitemapJob($cmsPost);
        }
    }

    /**
     * Dispatch the sitemap regeneration job if enabled.
     */
    protected function dispatchSitemapJob(CmsPost $cmsPost): void
    {
        // Check if auto-regenerate is enabled
        if (! setting('seo.sitemap.auto_regenerate', true)) {
            return;
        }

        // Check if sitemap is enabled globally
        if (! setting('seo.sitemap.enabled', false)) {
            return;
        }

        // Determine sitemap type from post type
        $sitemapType = $this->typeMapping[$cmsPost->type] ?? null;

        if (! $sitemapType) {
            Log::debug('CmsPostSitemapObserver: Unknown post type, skipping sitemap regeneration', [
                'post_type' => $cmsPost->type,
            ]);

            return;
        }

        dispatch(new GenerateSitemapJob($sitemapType))->delay(now()->addSeconds(30));

        Log::info('Sitemap regeneration queued (30s delay) for type: '.$sitemapType);
    }
}
