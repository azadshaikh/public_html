<?php

namespace Modules\ReleaseManager\Models\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ReleaseQueryBuilder extends Builder
{
    public function search(?string $search): self
    {
        if ($search) {
            $this->where(function ($query) use ($search): void {
                $query->where('release_manager_releases.package_identifier', 'ilike', sprintf('%%%s%%', $search))
                    ->orWhere('release_manager_releases.version', 'ilike', sprintf('%%%s%%', $search))
                    ->orWhere('release_manager_releases.change_log', 'ilike', sprintf('%%%s%%', $search))
                    ->orWhere('release_manager_releases.release_link', 'ilike', sprintf('%%%s%%', $search));
            });
        }

        return $this;
    }

    public function filterByReleaseType($releaseType): self
    {
        if ($releaseType) {
            if (is_array($releaseType)) {
                $this->whereIn('release_manager_releases.release_type', $releaseType);
            } else {
                $this->where('release_manager_releases.release_type', $releaseType);
            }
        }

        return $this;
    }

    public function filterByVersionType($versionType): self
    {
        if ($versionType) {
            if (is_array($versionType)) {
                $this->whereIn('release_manager_releases.version_type', $versionType);
            } else {
                $this->where('release_manager_releases.version_type', $versionType);
            }
        }

        return $this;
    }

    public function filterByStatus($status): self
    {
        if ($status && $status !== 'all') {
            $this->where('release_manager_releases.status', $status);
        }

        return $this;
    }

    public function filterByCreator(?int $creator_id): self
    {
        if ($creator_id) {
            $this->where('release_manager_releases.created_by', $creator_id);
        }

        return $this;
    }

    public function filterByReleaseDate($date): self
    {
        if (! empty($date)) {
            if (isset($date['from'])) {
                $this->whereDate('release_manager_releases.release_at', '>=', $date['from']);
            }

            if (isset($date['to'])) {
                $this->whereDate('release_manager_releases.release_at', '<=', $date['to']);
            }
        }

        return $this;
    }

    public function filterByCreatedDate($date): self
    {
        if (! empty($date)) {
            if (isset($date['from'])) {
                $this->whereDate('release_manager_releases.created_at', '>=', $date['from']);
            }

            if (isset($date['to'])) {
                $this->whereDate('release_manager_releases.created_at', '<=', $date['to']);
            }
        }

        return $this;
    }

    public function filterBySortable($sortable): self
    {
        if ($sortable) {
            if (is_array($sortable)) {
                foreach ($sortable as $field => $value) {
                    if ($value) {
                        $this->where($field, $value);
                    }
                }
            } elseif ($sortable === 'latest_release') {
                return $this->latest('release_manager_releases.release_at');
            } elseif ($sortable === 'latest') {
                return $this->latest('release_manager_releases.created_at');
            } elseif ($sortable === 'latest_updated') {
                return $this->latest('release_manager_releases.updated_at');
            } elseif ($sortable === 'oldest_release') {
                return $this->oldest('release_manager_releases.release_at');
            } elseif ($sortable === 'oldest') {
                return $this->oldest('release_manager_releases.created_at');
            } elseif ($sortable === 'oldest_updated') {
                return $this->oldest('release_manager_releases.updated_at');
            }
        }

        return $this;
    }

    public function sortBy(?string $sort_by): self
    {
        if ($sort_by) {
            $direction = str_starts_with($sort_by, '-') ? 'desc' : 'asc';
            $field = ltrim($sort_by, '-');
            $this->orderBy('release_manager_releases.'.$field, $direction);
        }

        return $this;
    }

    public function orderResults(?array $order): self
    {
        if ($order) {
            foreach ($order as $field => $direction) {
                $this->orderBy('release_manager_releases.'.$field, $direction);
            }
        }

        return $this;
    }

    public function paginateResults(?array $pagination): LengthAwarePaginator
    {
        if (! $pagination) {
            return $this->paginate(10);
        }

        $limit = $pagination['limit'] ?? 10;
        $skip = $pagination['skip'] ?? 0;
        $page = ($skip / $limit) + 1;

        return $this->paginate($limit, ['*'], 'page', $page);
    }
}
