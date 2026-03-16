<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Scaffold\ScaffoldController;
use App\Support\CacheInvalidation;
use App\Traits\HasMediaPicker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\CMS\Definitions\TagDefinition;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Services\TagService;
use RuntimeException;

class TagsController extends ScaffoldController implements HasMiddleware
{
    use HasMediaPicker;

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
            'initialValues' => $this->buildInitialValues($model),
            'preSlug' => $preSlug,
            'statusOptions' => $this->tagService->getStatusOptions(),
            'metaRobotsOptions' => $this->tagService->getMetaRobotsOptions(),
            'templateOptions' => $this->tagService->getTemplateOptions(),
            'baseUrl' => rtrim(url('/'), '/'),
            'defaults' => ['status' => 'draft'],
            ...$this->getMediaPickerProps(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var CmsPost $model */
        $model->loadMissing(['featuredImage:id']);

        return [
            'id' => $model->getKey(),
            'title' => (string) $model->getAttribute('title'),
            'permalink_url' => $model->permalink_url ? url($model->permalink_url) : null,
            'featured_image_url' => $model->featuredImage
                ? get_media_url($model->featuredImage, 'thumbnail', usePlaceholder: false)
                : null,
            'updated_at_formatted' => app_date_time_format($model->updated_at, 'datetime'),
            'updated_at_human' => $model->updated_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInitialValues(CmsPost $model): array
    {
        return [
            'title' => (string) ($model->getAttribute('title') ?? ''),
            'slug' => (string) ($model->getAttribute('slug') ?? ''),
            'content' => (string) ($model->getAttribute('content') ?? ''),
            'excerpt' => (string) ($model->getAttribute('excerpt') ?? ''),
            'feature_image' => $model->getAttribute('feature_image_id') ? (int) $model->getAttribute('feature_image_id') : '',
            'status' => (string) ($model->getAttribute('status') ?? 'draft'),
            'template' => (string) ($model->getAttribute('template') ?? ''),
            'meta_title' => (string) ($model->meta_title ?? ''),
            'meta_description' => (string) ($model->meta_description ?? ''),
            'meta_robots' => (string) ($model->meta_robots ?? ''),
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
