<?php

declare(strict_types=1);

namespace Modules\CMS\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CMS\Definitions\PostDefinition;
use Modules\CMS\Enums\CmsPostType;
use Modules\CMS\Enums\MetaRobotsTag;
use Modules\CMS\Http\Resources\PostResource;
use Modules\CMS\Jobs\GenerateSitemapJob;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Theme;
use Modules\CMS\Traits\SyncsTermRelationships;
use RuntimeException;

class PostService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        getFiltersConfig as protected scaffoldGetFiltersConfig;
        applySorting as protected scaffoldApplySorting;
    }
    use SyncsTermRelationships;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new PostDefinition;
    }

    /**
     * Override to include dynamically populated filter options in getData() responses.
     * Without this, the Scaffoldable trait returns raw definition filters with empty options.
     */
    protected function getFiltersConfig(): array
    {
        $filters = $this->scaffoldGetFiltersConfig();

        return $this->injectFilterOptions($filters);
    }

    /**
     * Inject dynamic options into filter configs (author, category, tag, status).
     * Used by both getDataGridConfig() and getFiltersConfig() to keep them consistent.
     */
    private function injectFilterOptions(array $filters): array
    {
        foreach ($filters as $i => $filter) {
            if (($filter['key'] ?? null) === 'author_id') {
                $userId = Auth::id() ?? 0;

                $filters[$i]['options'] = Cache::remember(
                    'cms:datagrid:posts:author-options:user:'.$userId,
                    now()->addMinutes(5),
                    fn () => User::visibleToCurrentUser()
                        ->where('status', 'active')
                        ->whereDoesntHave('roles', function ($q): void {
                            $q->where('roles.id', User::superUserRoleId());
                        })
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->mapWithKeys(fn (User $u): array => [$u->id => $u->name])
                        ->toArray()
                );
            }

            if (($filter['key'] ?? null) === 'category_ids') {
                $filters[$i]['options'] = Cache::remember(
                    'cms:datagrid:posts:category-options:v2',
                    now()->addMinutes(5),
                    function (): array {
                        /** @var Collection<int, CmsPost> $categories */
                        $categories = CmsPost::query()
                            ->where('type', CmsPostType::CATEGORY->value)
                            ->where('status', 'published')
                            ->whereNull('deleted_at')
                            ->orderBy('title')
                            ->get(['id', 'title']);

                        return $categories
                            ->mapWithKeys(fn (CmsPost $category): array => [$category->id => $category->title])
                            ->all();
                    }
                );
            }

            if (($filter['key'] ?? null) === 'tag_ids') {
                $filters[$i]['options'] = Cache::remember(
                    'cms:datagrid:posts:tag-options:v2',
                    now()->addMinutes(5),
                    function (): array {
                        /** @var Collection<int, CmsPost> $tags */
                        $tags = CmsPost::query()
                            ->where('type', CmsPostType::TAG->value)
                            ->where('status', 'published')
                            ->whereNull('deleted_at')
                            ->orderBy('title')
                            ->get(['id', 'title']);

                        return $tags
                            ->mapWithKeys(fn (CmsPost $tag): array => [$tag->id => $tag->title])
                            ->all();
                    }
                );
            }

            if (($filter['key'] ?? null) === 'statuses') {
                $filters[$i]['options'] = collect(config('cms.post_status', []))
                    ->mapWithKeys(fn ($status): array => [$status['value'] => $status['label']])
                    ->toArray();
            }
        }

        return $filters;
    }

    public function getStatusOptions(): array
    {
        return collect(config('cms.post_status', []))
            ->map(fn ($status): array => ['value' => $status['value'], 'label' => $status['label']])
            ->values()
            ->all();
    }

    public function getStatistics(): array
    {
        $statusField = $this->scaffold()->getStatusField();
        $type = CmsPostType::POST->value;

        $stats = [
            'total' => CmsPost::query()->where('type', $type)->count(),
        ];

        // Add status-specific counts
        if ($statusField) {
            $statusCounts = CmsPost::query()
                ->where('type', $type)
                ->selectRaw($statusField.', count(*) as count')
                ->groupBy($statusField)
                ->pluck('count', $statusField)
                ->toArray();

            $stats = array_merge($stats, $statusCounts);
        }

        // Add trash count
        $deletedAtColumn = (new CmsPost)->getDeletedAtColumn();
        $stats['trash'] = CmsPost::query()
            ->withoutGlobalScope(SoftDeletingScope::class)
            ->where('type', $type)
            ->whereNotNull($deletedAtColumn)
            ->count();

        return $stats;
    }

    public function getMetaRobotsOptions(): array
    {
        $options = [['value' => '', 'label' => 'Default']];

        foreach (MetaRobotsTag::options() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    public function generateSitemap(): void
    {
        dispatch(new GenerateSitemapJob('posts'));
    }

    public function getVisibilityOptions(): array
    {
        return [
            ['value' => 'public', 'label' => 'Public'],
            ['value' => 'private', 'label' => 'Private'],
            ['value' => 'password', 'label' => 'Password Protected'],
        ];
    }

    public function getCategoryOptions(array $includeIds = []): array
    {
        $includeIds = array_values(array_filter(array_unique(array_map(intval(...), $includeIds))));

        return CmsPost::query()
            ->where('type', CmsPostType::CATEGORY->value)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($includeIds): void {
                $query->where('status', 'published');

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->orderBy('title')
            ->get(['id', 'title', 'status'])
            ->map(fn ($c): array => [
                'value' => $c->getKey(),
                'label' => (string) $c->getAttribute('title'),
                'disabled' => (string) $c->getAttribute('status') !== 'published',
            ])
            ->all();
    }

    public function getTagOptions(array $includeIds = []): array
    {
        $includeIds = array_values(array_filter(array_unique(array_map(intval(...), $includeIds))));

        return CmsPost::query()
            ->where('type', CmsPostType::TAG->value)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($includeIds): void {
                $query->where('status', 'published');

                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->orderBy('title')
            ->get(['id', 'title', 'status'])
            ->map(fn ($t): array => [
                'value' => $t->getKey(),
                'label' => (string) $t->getAttribute('title'),
                'disabled' => (string) $t->getAttribute('status') !== 'published',
            ])
            ->all();
    }

    public function getAuthorOptions(): array
    {
        return User::visibleToCurrentUser()
            ->where('status', 'active')
            ->whereDoesntHave('roles', function ($q): void {
                $q->where('roles.id', User::superUserRoleId());
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name])
            ->toArray();
    }

    public function getTemplateOptions(): array
    {
        return Theme::getAvailableTemplates('post');
    }

    /**
     * Duplicate a post with all its content and relationships
     */
    public function duplicate(CmsPost $post): CmsPost
    {
        return DB::transaction(function () use ($post): CmsPost {
            // Generate unique slug
            $baseSlug = Str::slug($post->title.'-copy');
            $slug = $baseSlug.'-'.time();

            // Create duplicate post
            $duplicate = CmsPost::query()->create([
                'title' => $post->title.' (Copy)',
                'subtitle' => $post->subtitle,
                'slug' => $slug,
                'type' => $post->type,
                'template' => $post->template,
                'format' => $post->format,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'css' => $post->css,
                'js' => $post->js,
                'category_id' => $post->category_id,
                'author_id' => Auth::id(), // Set current user as author
                'parent_id' => $post->parent_id,
                'visibility' => $post->visibility,
                'comment_status' => $post->comment_status,
                'seo_data' => $post->seo_data,
                'og_data' => $post->og_data,
                'schema' => $post->schema,
                'metadata' => $post->metadata,
                'is_cached' => $post->is_cached,
                'is_featured' => false, // Reset featured status
                'feature_image_id' => $post->feature_image_id, // Keep same reference
                'status' => 'draft', // Always create as draft
                'published_at' => null, // Clear publish date
                'post_password' => null, // Clear password for security
                'password_hint' => null,
                'hits' => 0, // Reset view count
            ]);

            throw_unless($duplicate instanceof CmsPost, RuntimeException::class, 'Failed to duplicate post.');

            // Copy term relationships (categories and tags)
            $this->syncTerms($duplicate, [
                'categories' => $post->categories->pluck('id')->toArray(),
                'tags' => $post->tags->pluck('id')->toArray(),
            ]);

            return $duplicate;
        });
    }

    protected function getResourceClass(): ?string
    {
        return PostResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'author:id,name',
            'parent:id,title,slug,parent_id', // Include slug and parent_id for permalink generation
            'parent.parent:id,slug,parent_id', // Load grandparent for hierarchy
            'category:id,title,slug,parent_id', // Include slug and parent_id for permalink
            'category.parent:id,slug,parent_id', // Load category's parent for hierarchy
            'categories:id,title',
            'featuredImage', // Load all columns - needed for Spatie Media Library URL generation
            'createdBy:id,name',
            'updatedBy:id,name',
        ];
    }

    /**
     * WordPress-style sorting for admin post list.
     *
     * Sorting algorithm:
     * - When sorting by date (published_at), use a computed "sort_date" column:
     *   - For published/scheduled posts: use published_at
     *   - For draft posts: use updated_at (last modified date)
     * - This ensures recently modified drafts appear near the top alongside recent published posts
     * - When sorting by specific status tab, the sort is straightforward
     */
    protected function applySorting(Builder $query, Request $request): void
    {
        $sortBy = $request->input('sort_column', $this->scaffold()->getDefaultSort());
        $sortOrder = $request->input('sort_direction', $this->scaffold()->getDefaultSortDirection());
        $statusTab = $request->input('status');

        // WordPress-style sticky behavior: keep featured posts at the top,
        // regardless of the chosen secondary sort.
        if ($sortBy !== 'is_featured') {
            $query->orderByDesc('is_featured');
        }

        if (! $sortBy) {
            return;
        }

        // Validate sort column is allowed
        $sortableColumns = $this->scaffold()->getSortableColumns();
        if (! in_array($sortBy, $sortableColumns) && $sortBy !== 'created_at' && $sortBy !== 'published_at') {
            $sortBy = $this->scaffold()->getDefaultSort();
        }

        // Validate direction
        $sortOrder = strtolower((string) $sortOrder) === 'asc' ? 'asc' : 'desc';

        if (! $sortBy) {
            return;
        }

        // WordPress-style sorting: when sorting by date on "all" tab, use computed sort_date
        // For published/scheduled: use published_at, for drafts: use updated_at
        if ($sortBy === 'published_at' && (empty($statusTab) || $statusTab === 'all')) {
            // Use COALESCE to pick the right date based on status:
            // - published_at for published/scheduled posts
            // - updated_at for draft posts (most recently modified first)
            $query->orderByRaw("
                CASE
                    WHEN status IN ('published', 'scheduled') THEN COALESCE(published_at, updated_at)
                    ELSE updated_at
                END {$sortOrder}
            ");

            return;
        }

        // For specific status tabs or other sort columns, use standard sorting
        $actualSortColumn = $this->scaffold()->getActualSortColumn($sortBy) ?? $sortBy;
        $query->orderBy($actualSortColumn, $sortOrder);
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        $query->where('type', CmsPostType::POST->value);

        // Filter by category_id (for category show page)
        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('categories', function (Builder $categoryQuery) use ($categoryId): void {
                $categoryQuery->whereKey((int) $categoryId);
            });
        }

        // Filter by tag_id (for tag show page)
        if ($tagId = $request->input('tag_id')) {
            $query->whereHas('tags', function (Builder $tagQuery) use ($tagId): void {
                $tagQuery->whereKey((int) $tagId);
            });
        }
    }

    protected function prepareCreateData(array $data): array
    {
        $data['type'] = CmsPostType::POST->value;
        $data['status'] ??= 'draft';
        $data['visibility'] ??= 'public';

        // Default author
        $data['author_id'] ??= Auth::id();

        // Primary category for permalink generation
        if (! empty($data['categories']) && is_array($data['categories'])) {
            $data['category_id'] = (int) $data['categories'][0];
        }

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (! in_array($data['status'], ['published', 'scheduled'], true)) {
            $data['published_at'] = null;
        }

        if (array_key_exists('feature_image', $data)) {
            $data['feature_image_id'] = $data['feature_image'] ?: null;
            unset($data['feature_image']);
        }

        if (array_key_exists('categories', $data)) {
            unset($data['categories']);
        }

        if (array_key_exists('tags', $data)) {
            unset($data['tags']);
        }

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        // Keep type enforced
        $data['type'] = CmsPostType::POST->value;

        // Primary category for permalink generation
        if (! empty($data['categories']) && is_array($data['categories'])) {
            $data['category_id'] = (int) $data['categories'][0];
        }

        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (isset($data['status']) && ! in_array($data['status'], ['published', 'scheduled'], true)) {
            $data['published_at'] = null;
        }

        if (array_key_exists('feature_image', $data)) {
            $data['feature_image_id'] = $data['feature_image'] ?: null;
            unset($data['feature_image']);
        }

        if (array_key_exists('categories', $data)) {
            unset($data['categories']);
        }

        if (array_key_exists('tags', $data)) {
            unset($data['tags']);
        }

        // Handle password protection: don't overwrite existing password if empty
        // If visibility is changed away from 'password', clear the password
        if (isset($data['visibility']) && $data['visibility'] !== 'password') {
            $data['post_password'] = null;
            $data['password_hint'] = null;
        } elseif (array_key_exists('post_password', $data) && empty($data['post_password'])) {
            // Don't update password if empty (keep existing)
            unset($data['post_password']);
        }

        return $data;
    }

    protected function afterCreate(Model $model, array $data): void
    {
        if ($model instanceof CmsPost) {
            $this->syncTerms($model, [
                'categories' => $data['categories'] ?? [],
                'tags' => $data['tags'] ?? [],
            ]);
        }
    }

    protected function afterUpdate(Model $model, array $data): void
    {
        if ($model instanceof CmsPost) {
            $this->syncTerms($model, [
                'categories' => $data['categories'] ?? [],
                'tags' => $data['tags'] ?? [],
            ]);
        }
    }
}
