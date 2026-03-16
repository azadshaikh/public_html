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
use Modules\CMS\Definitions\PostDefinition;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Services\PostService;

class PostsController extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly PostService $postService) {}

    public static function middleware(): array
    {
        return [
            ...(new PostDefinition)->getMiddleware(),
            // Custom endpoint
            new Middleware('permission:add_posts', only: ['duplicate']),
        ];
    }

    /**
     * Duplicate a post with all its content and relationships
     */
    public function duplicate(CmsPost $post): RedirectResponse
    {
        try {
            $duplicatedPost = $this->postService->duplicate($post);

            return to_route('cms.posts.edit', $duplicatedPost)
                ->with('success', 'Post duplicated successfully. You can now edit the draft.');
        } catch (Exception $exception) {
            return back()->with('error', 'Failed to duplicate post: '.$exception->getMessage());
        }
    }

    protected function service(): PostService
    {
        return $this->postService;
    }

    protected function inertiaPage(): string
    {
        return 'cms/posts';
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var CmsPost $model */
        if ($model->exists) {
            $model->load(['categories:id', 'tags:id', 'createdBy:id,name', 'featuredImage:id']);
        }

        $selectedCategoryIds = $model->exists ? $model->categories->pluck('id')->all() : [];
        $selectedTagIds = $model->exists ? $model->tags->pluck('id')->all() : [];

        return [
            'initialValues' => $this->buildInitialValues($model),
            'categoryOptions' => $this->postService->getCategoryOptions($selectedCategoryIds),
            'tagOptions' => $this->postService->getTagOptions($selectedTagIds),
            'authorOptions' => $this->postService->getAuthorOptions(),
            'metaRobotsOptions' => $this->postService->getMetaRobotsOptions(),
            'statusOptions' => $this->postService->getStatusOptions(),
            'visibilityOptions' => $this->postService->getVisibilityOptions(),
            'templateOptions' => $this->postService->getTemplateOptions(),
            'preSlug' => '/',
            'baseUrl' => rtrim(url('/'), '/'),
            'defaults' => ['status' => 'draft'],
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
            'is_password_protected' => $model->isPasswordProtected(),
            'updated_at_formatted' => app_date_time_format($model->updated_at, 'datetime'),
            'updated_at_human' => $model->updated_at?->diffForHumans(),
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
            'is_featured' => (bool) ($model->getAttribute('is_featured') ?? false),
            'status' => (string) ($model->getAttribute('status') ?? 'draft'),
            'visibility' => (string) ($model->getAttribute('visibility') ?? 'public'),
            'post_password' => '',
            'password_hint' => (string) ($model->getAttribute('password_hint') ?? ''),
            'author_id' => $model->getAttribute('author_id') ? (int) $model->getAttribute('author_id') : ($defaultAuthorId ?? ''),
            'published_at' => $model->published_at
                ? $model->published_at->setTimezone(app_localization_timezone())->format('Y-m-d\TH:i')
                : '',
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
            'template' => (string) ($model->getAttribute('template') ?? ''),
            'categories' => $model->exists
                ? $model->categories->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                : [],
            'tags' => $model->exists
                ? $model->tags->pluck('id')->map(fn ($id): int => (int) $id)->values()->all()
                : [],
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
