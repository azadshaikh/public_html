<?php

declare(strict_types=1);

namespace Modules\CMS\Observers;

use App\Support\CacheInvalidation;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Services\TaxonomyCacheService;

/**
 * Observes CmsPost model for taxonomy (category/tag) changes.
 *
 * This observer invalidates taxonomy caches when categories or tags
 * are created, updated, or deleted to ensure cache consistency.
 */
class CmsPostTaxonomyObserver
{
    public function __construct(
        protected TaxonomyCacheService $taxonomyCache
    ) {}

    public function created(CmsPost $post): void
    {
        $this->invalidateIfTaxonomy($post);
    }

    public function updated(CmsPost $post): void
    {
        $this->invalidateIfTaxonomy($post);
    }

    public function deleted(CmsPost $post): void
    {
        $this->invalidateIfTaxonomy($post);
    }

    public function restored(CmsPost $post): void
    {
        $this->invalidateIfTaxonomy($post);
    }

    public function forceDeleted(CmsPost $post): void
    {
        $this->invalidateIfTaxonomy($post);
    }

    /**
     * Invalidate cache if the post is a taxonomy type (category or tag).
     */
    protected function invalidateIfTaxonomy(CmsPost $post): void
    {
        if ($post->type === 'category') {
            $this->taxonomyCache->invalidateCategories(sprintf('Category %s modified', $post->title));
            CacheInvalidation::touchForModel(
                $post,
                'Category modified: '.$post->title,
                ['status' => $post->getOriginal('status')]
            );
        } elseif ($post->type === 'tag') {
            $this->taxonomyCache->invalidateTags(sprintf('Tag %s modified', $post->title));
            CacheInvalidation::touchForModel(
                $post,
                'Tag modified: '.$post->title,
                ['status' => $post->getOriginal('status')]
            );
        }
    }
}
