<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Scaffold\ScaffoldController;
use App\Support\CacheInvalidation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\CMS\Definitions\CategoryDefinition;
use Modules\CMS\Services\CategoryService;
use RuntimeException;

class CategoriesController extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public static function middleware(): array
    {
        return (new CategoryDefinition)->getMiddleware();
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

    protected function service(): CategoryService
    {
        return $this->categoryService;
    }

    protected function inertiaPage(): string
    {
        return 'cms/categories';
    }

    protected function getFormViewData(Model $model): array
    {
        $categoryBase = setting('seo_categories_permalink_base');
        $preSlug = $categoryBase ? '/'.$categoryBase.'/' : '/';

        return [
            'parentCategoryOptions' => $this->categoryService->getParentCategoryOptions(),
            'statusOptions' => $this->categoryService->getStatusOptions(),
            'metaRobotsOptions' => $this->categoryService->getMetaRobotsOptions(),
            'templateOptions' => $this->categoryService->getTemplateOptions(),
            'preSlug' => $preSlug,
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
