<?php

declare(strict_types=1);

namespace Modules\CMS\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\Request;
use Modules\CMS\Definitions\TagDefinition;
use Modules\CMS\Enums\CmsPostType;
use Modules\CMS\Enums\MetaRobotsTag;
use Modules\CMS\Http\Resources\TagResource;
use Modules\CMS\Jobs\GenerateSitemapJob;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Theme;
use RuntimeException;

class TagService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        getFiltersConfig as protected scaffoldGetFiltersConfig;
    }

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new TagDefinition;
    }

    public function getStatusOptions(): array
    {
        return [
            ['value' => 'published', 'label' => 'Published'],
            ['value' => 'draft', 'label' => 'Draft'],
        ];
    }

    protected function getFiltersConfig(): array
    {
        $filters = $this->scaffoldGetFiltersConfig();

        foreach ($filters as $index => $filter) {
            if (($filter['key'] ?? null) === 'statuses') {
                $filters[$index]['options'] = $this->normalizeFilterOptionMap($this->getStatusOptions());
            }

            if (($filter['key'] ?? null) === 'author_id') {
                $filters[$index]['options'] = $this->getAuthorFilterOptions();
            }
        }

        return $filters;
    }

    public function getStatistics(): array
    {
        $statusField = $this->scaffold()->getStatusField();
        $type = CmsPostType::TAG->value;

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
        dispatch(new GenerateSitemapJob('tags'));
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
        return Theme::getAvailableTemplates('tag');
    }

    /**
     * @return array<string, string>
     */
    public function getAuthorFilterOptions(): array
    {
        return User::visibleToCurrentUser()
            ->where('status', 'active')
            ->whereDoesntHave('roles', function ($query): void {
                $query->where('roles.id', User::superUserRoleId());
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $user): array => [(string) $user->id => $user->name])
            ->all();
    }

    /**
     * Get the count of non-trashed posts associated with this tag.
     */
    public function getAssociatedPostsCount(CmsPost $tag): int
    {
        return $tag->termPosts()
            ->whereHas('post', fn ($q) => $q->whereNull('deleted_at'))
            ->count();
    }

    protected function getResourceClass(): ?string
    {
        return TagResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'featuredImage',
            'createdBy:id,name',
            'updatedBy:id,name',
        ];
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        $query->where('type', CmsPostType::TAG->value)
            ->withCount('termPosts as posts_count');
    }

    protected function prepareCreateData(array $data): array
    {
        $data['type'] = CmsPostType::TAG->value;
        $status = $data['status'] ?? 'published';
        $data['status'] = $status;

        if ($status === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (! in_array($status, ['published', 'scheduled'], true)) {
            $data['published_at'] = null;
        }

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        $data['type'] = CmsPostType::TAG->value;

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
     * Prevent deletion if the tag has associated posts.
     */
    protected function assertNoAssociatedPosts(CmsPost $tag): void
    {
        $count = $this->getAssociatedPostsCount($tag);

        if ($count > 0) {
            $label = $count === 1 ? '1 post' : $count.' posts';

            throw new RuntimeException(
                sprintf('Cannot delete this tag because it has %s. Please reassign or remove the posts first.', $label)
            );
        }
    }
}
