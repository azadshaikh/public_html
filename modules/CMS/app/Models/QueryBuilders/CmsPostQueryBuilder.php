<?php

namespace Modules\CMS\Models\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;

class CmsPostQueryBuilder extends Builder
{
    /**
     * Scope to only published posts.
     */
    public function published(): self
    {
        return $this->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function withTrashed(): self
    {
        return $this->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function search(?string $search): self
    {
        if (! $search) {
            return $this;
        }

        $escapedSearch = $this->escapeLikeQuery($search);

        return $this->where(function ($query) use ($escapedSearch): void {
            $query->where('cms_posts.title', 'ilike', sprintf('%%%s%%', $escapedSearch))
                ->orWhere('cms_posts.slug', 'ilike', sprintf('%%%s%%', $escapedSearch))
                ->orWhere('cms_posts.excerpt', 'ilike', sprintf('%%%s%%', $escapedSearch))
                ->orWhere('cms_posts.content', 'ilike', sprintf('%%%s%%', $escapedSearch));
        });
    }

    public function filterByStatus(string|array|null $status): self
    {
        if (! $status) {
            return $this;
        }

        if (is_array($status)) {
            return $this->whereIn('cms_posts.status', $status);
        }

        if ($status === 'all') {
            return $this->whereNull('cms_posts.deleted_at');
        }

        if ($status === 'trash') {
            return $this->whereNotNull('cms_posts.deleted_at');
        }

        return $this->where('cms_posts.status', $status);
    }

    public function filterByStatuses(string|array|null $statuses): self
    {
        if (! $statuses) {
            return $this;
        }

        if (is_string($statuses) && str_contains($statuses, ',')) {
            $statuses = array_values(array_filter(array_map(trim(...), explode(',', $statuses)), fn ($v): bool => $v !== ''));
        }

        if (is_array($statuses)) {
            $statuses = array_values(array_filter($statuses, fn ($v): bool => $v !== null && $v !== ''));

            if ($statuses === []) {
                return $this;
            }

            return $this->whereIn('cms_posts.status', $statuses);
        }

        if ($statuses === 'all') {
            return $this;
        }

        return $this->where('cms_posts.status', $statuses);
    }

    public function filterByType(string|array|null $type): self
    {
        if (! $type) {
            return $this;
        }

        if (is_array($type)) {
            return $this->whereIn('cms_posts.type', $type);
        }

        return $this->where('cms_posts.type', $type);
    }

    public function filterByVisibility(string|array|null $visibility): self
    {
        if (! $visibility) {
            return $this;
        }

        if (is_array($visibility)) {
            return $this->whereIn('cms_posts.visibility', $visibility);
        }

        return $this->where('cms_posts.visibility', $visibility);
    }

    public function filterByAuthor(string|array|null $authorIds): self
    {
        if (! $authorIds) {
            return $this;
        }

        if (is_array($authorIds)) {
            return $this->whereIn('cms_posts.author_id', $authorIds);
        }

        return $this->where('cms_posts.author_id', $authorIds);
    }

    public function filterByCategory(string|array|null $categoryIds): self
    {
        if (! $categoryIds) {
            return $this;
        }

        // Categories are stored in cms_post_terms table (many-to-many relationship)
        // Check both the primary category_id column AND the pivot table
        if (is_array($categoryIds)) {
            return $this->where(function ($query) use ($categoryIds): void {
                $query->whereIn('cms_posts.category_id', $categoryIds)
                    ->orWhereHas('categories', function ($q) use ($categoryIds): void {
                        $q->whereIn('cms_post_terms.term_id', $categoryIds);
                    });
            });
        }

        return $this->where(function ($query) use ($categoryIds): void {
            $query->where('cms_posts.category_id', $categoryIds)
                ->orWhereHas('categories', function ($q) use ($categoryIds): void {
                    $q->where('cms_post_terms.term_id', $categoryIds);
                });
        });
    }

    public function filterByTags(string|array|null $tagIds): self
    {
        if (! $tagIds) {
            return $this;
        }

        // Tags are now stored in cms_post_terms table, not in a comma-separated column
        if (is_array($tagIds)) {
            $this->whereHas('tags', function ($query) use ($tagIds): void {
                $query->whereIn('term_id', $tagIds);
            });
        } else {
            $this->whereHas('tags', function ($query) use ($tagIds): void {
                $query->where('term_id', $tagIds);
            });
        }

        return $this;
    }

    public function filterByCommentStatus(string|array|null $commentStatus): self
    {
        if (! $commentStatus) {
            return $this;
        }

        if (is_array($commentStatus)) {
            return $this->whereIn('cms_posts.comment_status', $commentStatus);
        }

        return $this->where('cms_posts.comment_status', $commentStatus);
    }

    public function filterByCaching(?bool $caching): self
    {
        if ($caching === null) {
            return $this;
        }

        return $this->where('cms_posts.is_cached', $caching);
    }

    public function filterByDate(?array $date): self
    {
        if (! $date) {
            return $this;
        }

        if (isset($date['from'])) {
            $this->whereDate('cms_posts.created_at', '>=', $date['from']);
        }

        if (isset($date['to'])) {
            $this->whereDate('cms_posts.created_at', '<=', $date['to']);
        }

        return $this;
    }

    public function filterByPublishedDate(?array $date): self
    {
        if (! $date) {
            return $this;
        }

        if (isset($date['from'])) {
            $this->whereDate('cms_posts.published_at', '>=', $date['from']);
        }

        if (isset($date['to'])) {
            $this->whereDate('cms_posts.published_at', '<=', $date['to']);
        }

        return $this;
    }

    public function filterByCreator(string|array|null $creatorIds): self
    {
        if (! $creatorIds) {
            return $this;
        }

        if (is_array($creatorIds)) {
            return $this->whereIn('cms_posts.created_by', $creatorIds);
        }

        return $this->where('cms_posts.created_by', $creatorIds);
    }

    public function filterByParent(string|array|null $parentIds): self
    {
        if (! $parentIds) {
            return $this;
        }

        if (is_array($parentIds)) {
            return $this->whereIn('cms_posts.parent_id', $parentIds);
        }

        return $this->where('cms_posts.parent_id', $parentIds);
    }

    public function filterBySortable(string|array|null $sortable): self
    {
        if (! $sortable) {
            return $this;
        }

        if ($sortable === 'latest') {
            return $this->latest('cms_posts.created_at');
        }

        if ($sortable === 'oldest') {
            return $this->oldest('cms_posts.created_at');
        }

        if ($sortable === 'latest_updated') {
            return $this->latest('cms_posts.updated_at');
        }

        if ($sortable === 'oldest_updated') {
            return $this->oldest('cms_posts.updated_at');
        }

        if ($sortable === 'latest_published') {
            return $this->latest('cms_posts.published_at');
        }

        if ($sortable === 'oldest_published') {
            return $this->oldest('cms_posts.published_at');
        }

        return $this;
    }

    public function sortBy(?string $sortBy): self
    {
        if (! $sortBy) {
            return $this;
        }

        $sortFields = [
            'title' => 'title',
            'status' => 'status',
            'type' => 'type',
            'author' => 'author_id',
            'created' => 'created_at',
            'updated' => 'updated_at',
            'published' => 'published_at',
            'hits' => 'hits',
            'comments' => 'comment_count',
        ];

        if (isset($sortFields[$sortBy])) {
            return $this->orderBy($sortFields[$sortBy]);
        }

        return $this;
    }

    public function orderResults(string|array|null $order): self
    {
        if (! $order) {
            return $this->latest('cms_posts.created_at');
        }

        if (is_array($order)) {
            foreach ($order as $field => $direction) {
                $this->orderBy($field, $direction);
            }
        }

        return $this;
    }

    public function paginateResults(?array $pagination): LengthAwarePaginator
    {
        $perPage = $pagination['per_page'] ?? 15;
        $page = $pagination['page'] ?? 1;

        return $this->paginate($perPage, ['*'], 'page', $page);
    }

    private function escapeLikeQuery(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
