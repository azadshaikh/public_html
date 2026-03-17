<?php

namespace Modules\Platform\Models\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Platform\Models\Provider;

class DomainQueryBuilder extends Builder
{
    protected array $allowedFields = [
        'platform_domains.id',
        'platform_domains.name',
        'platform_domains.registrar_name',
        'platform_domains.agency_id',
        'platform_domains.created_at',
        'platform_domains.updated_at',
        'platform_domains.status',
    ];

    protected array $allowedDirections = ['asc', 'desc'];

    public function search(?string $search): self
    {
        if ($search) {
            $escapedSearch = $this->escapeLike($search);
            $pattern = sprintf('%%%s%%', $escapedSearch);

            $this->where(function ($query) use ($pattern): void {
                $query->where('platform_domains.name', 'ilike', $pattern)
                    ->orWhere('platform_domains.registrar_name', 'ilike', $pattern);
            });
        }

        return $this;
    }

    public function filterByGroup(?string $group): self
    {
        return $this;
    }

    public function filterByRegistrar(?string $registrar): self
    {
        if ($registrar) {
            // Filter via the providerable polymorphic relationship
            $this->whereHas('providers', function ($q) use ($registrar): void {
                $q->where('platform_providers.id', $registrar)
                    ->where('platform_providers.type', Provider::TYPE_DOMAIN_REGISTRAR);
            });
        }

        return $this;
    }

    public function filterBySortable($sortable): self
    {
        if ($sortable) {
            if (is_array($sortable)) {
                foreach ($sortable as $field => $value) {
                    if ($value && in_array($field, $this->allowedFields, true)) {
                        $this->where($field, $value);
                    }
                }
            } else {
                switch ($sortable) {
                    case 'latest':
                        return $this->latest('platform_domains.created_at');
                    case 'oldest':
                        return $this->oldest('platform_domains.created_at');
                }
            }
        }

        return $this;
    }

    public function sortBy(?string $sort_by): self
    {
        if ($sort_by) {
            $direction = str_starts_with($sort_by, '-') ? 'desc' : 'asc';
            $field = ltrim($sort_by, '-');
            if (in_array($field, $this->allowedFields, true) && in_array($direction, $this->allowedDirections, true)) {
                $this->orderBy($field, $direction);
            }
        }

        return $this;
    }

    public function orderResults(?array $order): self
    {
        if ($order) {
            foreach ($order as $field => $direction) {
                if (in_array($field, $this->allowedFields, true) && in_array(strtolower((string) $direction), $this->allowedDirections, true)) {
                    $this->orderBy($field, strtolower((string) $direction));
                }
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

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
