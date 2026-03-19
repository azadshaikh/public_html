<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Scaffold\ScaffoldController;
use App\Support\CacheInvalidation;
use App\Traits\HasMediaPicker;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\CMS\Definitions\PageDefinition;
use Modules\CMS\Http\Controllers\Concerns\BuildsCmsRevisionPayload;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Services\PageService;

class PagesController extends ScaffoldController implements HasMiddleware
{
    use BuildsCmsRevisionPayload;
    use HasMediaPicker;

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
        /** @var CmsPost $model */
        if ($model->exists) {
            $model->load(['featuredImage']);
        }

        return [
            'initialValues' => $this->buildInitialValues($model),
            'parentPageOptions' => $this->pageService->getParentPageOptions(),
            'authorOptions' => $this->pageService->getAuthorOptions(),
            'metaRobotsOptions' => $this->pageService->getMetaRobotsOptions(),
            'statusOptions' => $this->pageService->getStatusOptions(),
            'visibilityOptions' => $this->pageService->getVisibilityOptions(),
            'templateOptions' => $this->pageService->getTemplateOptions(),
            'preSlug' => '/',
            'baseUrl' => rtrim(url('/'), '/'),
            'defaults' => ['status' => 'draft'],
            ...$this->getMediaPickerProps(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var CmsPost $model */
        $model->loadMissing(['featuredImage']);

        return [
            'id' => $model->getKey(),
            'title' => (string) $model->getAttribute('title'),
            'permalink_url' => $model->permalink_url ? url($model->permalink_url) : null,
            'featured_image_url' => $model->featuredImage
                ? get_media_url($model->featuredImage, 'thumbnail', usePlaceholder: false)
                : null,
            'is_password_protected' => $model->isPasswordProtected(),
            'updated_at_formatted' => app_date_time_format($model->updated_at, 'datetime'),
            'updated_at_human' => $model->updated_at?->diffForHumans(),
            ...$this->buildCmsRevisionPayload($model),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInitialValues(CmsPost $model): array
    {
        $defaultAuthorId = auth()->check() && ! auth()->user()?->isSuperUser()
            ? auth()->id()
            : null;

        $schema = $model->getAttribute('schema');

        return [
            'title' => (string) ($model->getAttribute('title') ?? ''),
            'slug' => (string) ($model->getAttribute('slug') ?? ''),
            'content' => (string) ($model->getAttribute('content') ?? ''),
            'excerpt' => (string) ($model->getAttribute('excerpt') ?? ''),
            'feature_image' => $model->getAttribute('feature_image_id') ? (int) $model->getAttribute('feature_image_id') : '',
            'status' => (string) ($model->getAttribute('status') ?? 'draft'),
            'visibility' => (string) ($model->getAttribute('visibility') ?? 'public'),
            'post_password' => '',
            'password_hint' => (string) ($model->getAttribute('password_hint') ?? ''),
            'author_id' => $model->getAttribute('author_id') ? (int) $model->getAttribute('author_id') : ($defaultAuthorId ?? ''),
            'published_at' => $model->published_at
                ? $model->published_at->setTimezone(app_localization_timezone())->format('Y-m-d\TH:i')
                : '',
            'parent_id' => $model->getAttribute('parent_id') ? (string) $model->getAttribute('parent_id') : '',
            'template' => (string) ($model->getAttribute('template') ?? ''),
            'meta_title' => (string) ($model->meta_title ?? ''),
            'meta_description' => (string) ($model->meta_description ?? ''),
            'meta_robots' => (string) ($model->meta_robots ?? ''),
            'og_title' => (string) ($model->og_title ?? ''),
            'og_description' => (string) ($model->og_description ?? ''),
            'og_image' => (string) ($model->og_image ?? ''),
            'og_url' => (string) ($model->og_url ?? ''),
            'schema' => is_array($schema)
                ? json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : (string) ($schema ?? ''),
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
