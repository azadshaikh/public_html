<?php

namespace Modules\CMS\Observers;

use App\Enums\Status;
use App\Models\User;
use App\Support\CacheInvalidation;
use Modules\CMS\Jobs\SendPostPublishedNotificationsJob;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Services\MenuUrlService;

class CmsPostObserver
{
    protected $exclude_types = ['post'];

    public function __construct(
        private readonly MenuUrlService $menuUrlService
    ) {}

    /**
     * Handle the CmsPost "created" event.
     */
    public function created(CmsPost $cmspost): void
    {
        $this->invalidateFrontendCaches($cmspost);

        if ($cmspost->type === 'post' && $cmspost->status === 'published') {
            $this->notifyUsersOfPublishedPost($cmspost, wasScheduled: false);
        }
    }

    /**
     * Handle the CmsPost "updated" event.
     */
    public function updated(CmsPost $cmspost): void
    {
        // Invalidate frontend caches on any content update
        $this->invalidateFrontendCaches($cmspost);

        // Check if post was just published (status changed to 'published')
        if ($cmspost->type === 'post' && $cmspost->wasChanged('status')) {
            $oldStatus = $cmspost->getOriginal('status');
            $newStatus = $cmspost->status;

            if ($newStatus === 'published' && $oldStatus !== 'published') {
                $this->notifyUsersOfPublishedPost($cmspost, wasScheduled: false);
            }
        }

        if (in_array($cmspost->type, $this->exclude_types)) {
            return;
        }

        // slug changed than update menu item url
        if (($cmspost->slug != $cmspost->getRawOriginal('slug')) || ($cmspost->status != $cmspost->getRawOriginal('status'))) {
            $this->menuUrlService->updateMenuItemUrlByObjectId($cmspost->id);
        }
    }

    /**
     * Handle the CmsPost "deleted" event.
     */
    public function deleted(CmsPost $cmspost): void
    {
        $this->invalidateFrontendCaches($cmspost);

        if (in_array($cmspost->type, $this->exclude_types)) {
            return;
        }

        // delete menu item url
        $this->menuUrlService->updateMenuItemUrlByObjectId($cmspost->id);
    }

    /**
     * Handle the CmsPost "restored" event.
     */
    public function restored(CmsPost $cmspost): void
    {
        $this->invalidateFrontendCaches($cmspost);
    }

    /**
     * Handle the CmsPost "force deleted" event.
     */
    public function forceDeleted(CmsPost $cmspost): void
    {
        $this->invalidateFrontendCaches($cmspost);

        if (in_array($cmspost->type, $this->exclude_types)) {
            return;
        }

        // delete menu item url
        $this->menuUrlService->forceDeleteMenuItemUrlByObjectId($cmspost->id);
    }

    /**
     * Invalidate frontend caches on content change.
     */
    protected function invalidateFrontendCaches(?CmsPost $cmspost = null): void
    {
        if (! $cmspost instanceof CmsPost) {
            CacheInvalidation::touch('CMS post changed');

            return;
        }

        CacheInvalidation::touchForModel(
            $cmspost,
            'CMS post changed',
            ['status' => $cmspost->getOriginal('status')]
        );
    }

    /**
     * Notify users when a post is published.
     */
    public function notifyUsersOfPublishedPost(CmsPost $post, bool $wasScheduled = false): void
    {
        if (! User::query()->where('status', Status::ACTIVE)->exists()) {
            return;
        }

        SendPostPublishedNotificationsJob::dispatch($post->id, $wasScheduled);
    }
}
