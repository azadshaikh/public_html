<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Scaffold\ScaffoldController;
use App\Support\CacheInvalidation;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\CMS\Definitions\PageDefinition;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Services\PageService;

class PagesController extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly PageService $pageService) {}

    public static function middleware(): array
    {
        return [
            ...(new PageDefinition)->getMiddleware(),
            // Custom endpoint
            new Middleware('permission:add_pages', only: ['duplicate']),
        ];
    }

    /**
     * Duplicate a page with all its content
     */
    public function duplicate(CmsPost $page): RedirectResponse
    {
        try {
            $duplicatedPage = $this->pageService->duplicate($page);

            return to_route('cms.pages.edit', $duplicatedPage)
                ->with('success', 'Page duplicated successfully. You can now edit the draft.');
        } catch (Exception $exception) {
            return back()->with('error', 'Failed to duplicate page: '.$exception->getMessage());
        }
    }

    protected function service(): PageService
    {
        return $this->pageService;
    }

    protected function inertiaPage(): string
    {
        return 'cms/pages';
    }

    protected function getFormViewData(Model $model): array
    {
        return [
            'parentPageOptions' => $this->pageService->getParentPageOptions(),
            'metaRobotsOptions' => $this->pageService->getMetaRobotsOptions(),
            'statusOptions' => $this->pageService->getStatusOptions(),
            'visibilityOptions' => $this->pageService->getVisibilityOptions(),
            'templateOptions' => $this->pageService->getTemplateOptions(),
            'preSlug' => '/',
            'defaults' => ['status' => 'draft'],
        ];
    }

    protected function handleCreationSideEffects(Model $model): void
    {
        // Sitemap is handled by CmsPostSitemapObserver (dispatched with 30s delay for debouncing).
        if (CacheInvalidation::affectsPublic($model)) {
            CacheInvalidation::clearCacheStore();
        }
    }

    protected function handleUpdateSideEffects(Model $model): void
    {
        // Use handleUpdateSideEffectsWithPrevious() for status-aware sitemap behavior.
    }

    protected function handleUpdateSideEffectsWithPrevious(Model $model, array $previousValues): void
    {
        // Sitemap is handled by CmsPostSitemapObserver (dispatched with 30s delay for debouncing).
        if (CacheInvalidation::affectsPublic($model, $previousValues)) {
            CacheInvalidation::clearCacheStore();
        }
    }

    protected function handleDeletionSideEffects(Model $model): void
    {
        // Sitemap is handled by CmsPostSitemapObserver (dispatched with 30s delay for debouncing).
        if (CacheInvalidation::affectsPublic($model)) {
            CacheInvalidation::clearCacheStore();
        }
    }

    protected function handleRestorationSideEffects(Model $model): void
    {
        // Sitemap is handled by CmsPostSitemapObserver (dispatched with 30s delay for debouncing).
        if (CacheInvalidation::affectsPublic($model)) {
            CacheInvalidation::clearCacheStore();
        }
    }

    protected function handleBulkActionSideEffects(string $action, array $ids): void
    {
        // Sitemap is handled by CmsPostSitemapObserver (one debounced job per type).
        // Clear the cache store once after the entire bulk operation.
        if (in_array($action, ['publish', 'unpublish', 'delete', 'force_delete', 'restore'], true)) {
            CacheInvalidation::clearCacheStore();
        }
    }
}
