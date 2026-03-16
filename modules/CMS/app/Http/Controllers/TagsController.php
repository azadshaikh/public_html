<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Scaffold\ScaffoldController;
use App\Support\CacheInvalidation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\CMS\Definitions\TagDefinition;
use Modules\CMS\Services\TagService;
use RuntimeException;

class TagsController extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly TagService $tagService) {}

    public static function middleware(): array
    {
        return (new TagDefinition)->getMiddleware();
    }

    public function destroy(int|string $id): RedirectResponse
    {
        try {
            return parent::destroy($id);
        } catch (RuntimeException $runtimeException) {
            return back()->with('error', $runtimeException->getMessage());
        }
    }

    public function forceDelete(int|string $id): RedirectResponse
    {
        try {
            return parent::forceDelete($id);
        } catch (RuntimeException $runtimeException) {
            return back()->with('error', $runtimeException->getMessage());
        }
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        try {
            return parent::bulkAction($request);
        } catch (RuntimeException $runtimeException) {
            return back()->with('error', $runtimeException->getMessage());
        }
    }

    protected function service(): TagService
    {
        return $this->tagService;
    }

    protected function inertiaPage(): string
    {
        return 'cms/tags';
    }

    protected function getFormViewData(Model $model): array
    {
        $tagBase = setting('seo_tags_permalink_base');
        $preSlug = $tagBase ? '/'.$tagBase.'/' : '/';

        return [
            'preSlug' => $preSlug,
            'statusOptions' => $this->tagService->getStatusOptions(),
            'metaRobotsOptions' => $this->tagService->getMetaRobotsOptions(),
            'templateOptions' => $this->tagService->getTemplateOptions(),
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
        // Use handleUpdateSideEffectsWithPrevious() for status-aware cache clearing.
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
