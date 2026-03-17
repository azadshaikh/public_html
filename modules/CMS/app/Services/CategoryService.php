<?php

declare(strict_types=1);

namespace Modules\CMS\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Modules\CMS\Definitions\CategoryDefinition;
use Modules\CMS\Enums\CmsPostType;
use Modules\CMS\Enums\MetaRobotsTag;
use Modules\CMS\Http\Resources\CategoryResource;
use Modules\CMS\Jobs\GenerateSitemapJob;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Theme;
use RuntimeException;

class CategoryService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new CategoryDefinition;
    }

    public function getStatusOptions(): array
    {
        return [
            ['value' => 'published', 'label' => 'Published'],
            ['value' => 'draft', 'label' => 'Draft'],
        ];
    }

    public function getStatistics(): array
    {
        $statusField = $this->scaffold()->getStatusField();
        $type = CmsPostType::CATEGORY->value;

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

    public function generateSitemap(): void
    {
        dispatch(new GenerateSitemapJob('categories'));
    }

    public function getParentCategoryOptions(): array
    {
        /** @var Collection<int, CmsPost> $categories */
        $categories = CmsPost::query()
            ->where('type', CmsPostType::CATEGORY->value)
            ->whereNull('deleted_at')
            ->orderBy('title')
            ->get(['id', 'title']);

        return $categories
            ->map(fn (CmsPost $category): array => ['value' => $category->id, 'label' => $category->title])
            ->all();
    }

    public function getMetaRobotsOptions(): array
    {
        $options = [['value' => '', 'label' => 'Default']];

        foreach (MetaRobotsTag::options() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    public function getTemplateOptions(): array
    {
        return Theme::getAvailableTemplates('category');
    }

    /**
     * Get the count of non-trashed posts associated with this category.
     */
    public function getAssociatedPostsCount(CmsPost $category): int
    {
        return $category->termPosts()
            ->whereHas('post', fn ($q) => $q->whereNull('deleted_at'))
            ->count();
    }

    protected function getResourceClass(): ?string
    {
        return CategoryResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'parent:id,title,slug,parent_id', // Include slug and parent_id for permalink generation
            'parent.parent:id,slug,parent_id', // Load grandparent for hierarchy
            'featuredImage',
            'createdBy:id,name',
            'updatedBy:id,name',
        ];
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        $query->where('type', CmsPostType::CATEGORY->value)
            ->withCount('termPosts as posts_count');
    }

    protected function prepareCreateData(array $data): array
    {
        $data['type'] = CmsPostType::CATEGORY->value;
        $data['status'] ??= 'published';

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
        $data['type'] = CmsPostType::CATEGORY->value;

        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (isset($data['status']) && ! in_array($data['status'], ['published', 'scheduled'], true)) {
            $data['published_at'] = null;
        }

        return $data;
    }

    protected function beforeDelete(Model $model): void
    {
        if ($model instanceof CmsPost) {
            $this->assertNoAssociatedPosts($model);
        }
    }

    protected function beforeForceDelete(Model $model): void
    {
        if ($model instanceof CmsPost) {
            $this->assertNoAssociatedPosts($model);
        }
    }

    /**
     * Prevent deletion if the category has associated posts.
     */
    protected function assertNoAssociatedPosts(CmsPost $category): void
    {
        $count = $this->getAssociatedPostsCount($category);

        if ($count > 0) {
            $label = $count === 1 ? '1 post' : $count.' posts';

            throw new RuntimeException(
                sprintf('Cannot delete this category because it has %s. Please reassign or remove the posts first.', $label)
            );
        }
    }
}
