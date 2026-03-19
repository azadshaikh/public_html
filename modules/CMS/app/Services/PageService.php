<?php

declare(strict_types=1);

namespace Modules\CMS\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CMS\Definitions\PageDefinition;
use Modules\CMS\Enums\CmsPostType;
use Modules\CMS\Enums\MetaRobotsTag;
use Modules\CMS\Http\Resources\PageResource;
use Modules\CMS\Jobs\GenerateSitemapJob;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Theme;
use RuntimeException;

class PageService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        getFiltersConfig as protected scaffoldGetFiltersConfig;
        applySorting as protected scaffoldApplySorting;
    }

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new PageDefinition;
    }

    public function getStatusOptions(): array
    {
        return collect(config('cms.post_status', []))
            ->map(fn ($status): array => ['value' => $status['value'], 'label' => $status['label']])
            ->values()
            ->all();
    }

    protected function getFiltersConfig(): array
    {
        $filters = $this->scaffoldGetFiltersConfig();

        foreach ($filters as $index => $filter) {
            if (($filter['key'] ?? null) === 'statuses') {
                $filters[$index]['options'] = $this->normalizeFilterOptionMap($this->getStatusOptions());
            }

            if (($filter['key'] ?? null) === 'author_id') {
                $filters[$index]['options'] = $this->normalizeFilterOptionMap($this->getAuthorOptions());
            }

            if (($filter['key'] ?? null) === 'parent_id') {
                $filters[$index]['options'] = $this->normalizeFilterOptionMap($this->getParentPageOptions());
            }
        }

        return $filters;
    }

    public function getStatistics(): array
    {
        $statusField = $this->scaffold()->getStatusField();
        $type = CmsPostType::PAGE->value;

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

    protected function alwaysIncludeStatistics(): bool
    {
        return true;
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
        dispatch(new GenerateSitemapJob('pages'));
    }

    public function getVisibilityOptions(): array
    {
        return [
            ['value' => 'public', 'label' => 'Public'],
            ['value' => 'private', 'label' => 'Private'],
            ['value' => 'password', 'label' => 'Password Protected'],
        ];
    }

    public function getParentPageOptions(): array
    {
        return CmsPost::query()
            ->where('type', CmsPostType::PAGE->value)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn ($p): array => ['value' => $p->id, 'label' => $p->title]) // @phpstan-ignore-line property.notFound
            ->all();
    }

    /**
     * Get published pages formatted for select dropdowns.
     * Used for Default Pages settings.
     *
     * @param  bool  $includeEmpty  Whether to include an empty "— Select —" option
     * @return array<int, array{value: int|string, label: string}>
     */
    public function getPublishedPageOptions(bool $includeEmpty = true): array
    {
        $options = $includeEmpty ? [['value' => '', 'label' => '— Select —']] : [];

        $pages = CmsPost::query()
            ->where('type', CmsPostType::PAGE->value)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get(['id', 'title']);

        /** @var Collection<int, CmsPost> $pages */
        foreach ($pages as $page) {
            $options[] = ['value' => (string) $page->id, 'label' => $page->title];
        }

        return $options;
    }

    public function getTemplateOptions(): array
    {
        return Theme::getAvailableTemplates('page');
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

    /**
     * Duplicate a page with all its content
     */
    public function duplicate(CmsPost $page): CmsPost
    {
        return DB::transaction(function () use ($page): CmsPost {
            // Generate unique slug
            $baseSlug = Str::slug($page->title.'-copy');
            $slug = $baseSlug.'-'.time();

            // Create duplicate page
            $duplicate = CmsPost::query()->create([
                'title' => $page->title.' (Copy)',
                'subtitle' => $page->subtitle,
                'slug' => $slug,
                'type' => $page->type,
                'template' => $page->template,
                'format' => $page->format,
                'excerpt' => $page->excerpt,
                'content' => $page->content,
                'css' => $page->css,
                'js' => $page->js,
                'author_id' => Auth::id(), // Set current user as author
                'parent_id' => $page->parent_id,
                'visibility' => $page->visibility,
                'comment_status' => $page->comment_status,
                'seo_data' => $page->seo_data,
                'og_data' => $page->og_data,
                'schema' => $page->schema,
                'metadata' => $page->metadata,
                'is_cached' => $page->is_cached,
                'is_featured' => false, // Reset featured status
                'feature_image_id' => $page->feature_image_id, // Keep same reference
                'status' => 'draft', // Always create as draft
                'published_at' => null, // Clear publish date
                'post_password' => null, // Clear password for security
                'password_hint' => null,
                'hits' => 0, // Reset view count
            ]);

            throw_unless($duplicate instanceof CmsPost, RuntimeException::class, 'Failed to duplicate page.');

            return $duplicate;
        });
    }

    protected function getResourceClass(): ?string
    {
        return PageResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'author:id,name',
            'parent:id,title,slug,parent_id', // Include slug and parent_id for permalink generation
            'parent.parent:id,slug,parent_id', // Load grandparent for hierarchy
            'featuredImage',
            'createdBy:id,name',
            'updatedBy:id,name',
        ];
    }

    /**
     * WordPress-style sorting for admin page list.
     *
     * Sorting algorithm:
     * - When sorting by date (published_at), use a computed "sort_date" column:
     *   - For published/scheduled pages: use published_at
     *   - For draft pages: use updated_at (last modified date)
     * - This ensures recently modified drafts appear near the top alongside recent published pages
     */
    protected function applySorting(Builder $query, Request $request): void
    {
        $sortBy = $request->input('sort_column', $this->scaffold()->getDefaultSort());
        $sortOrder = $request->input('sort_direction', $this->scaffold()->getDefaultSortDirection());
        $statusTab = $request->input('status');

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
        if ($sortBy === 'published_at' && (empty($statusTab) || $statusTab === 'all')) {
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
        $query->where('type', CmsPostType::PAGE->value);
    }

    protected function prepareCreateData(array $data): array
    {
        $data['type'] = CmsPostType::PAGE->value;
        $data['status'] ??= 'draft';
        $data['visibility'] ??= 'public';
        $data['author_id'] ??= Auth::id();

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (! in_array($data['status'], ['published', 'scheduled'], true)) {
            $data['published_at'] = null;
        }

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        $data['type'] = CmsPostType::PAGE->value;

        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (isset($data['status']) && ! in_array($data['status'], ['published', 'scheduled'], true)) {
            $data['published_at'] = null;
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
}
