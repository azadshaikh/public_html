<?php

namespace Modules\Platform\Models\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class DomainDnsRecordQueryBuilder extends Builder
{
    protected array $allowedFields = [
        'platform_dns_records.id',
        'platform_dns_records.domain_id',
        'platform_dns_records.type',
        'platform_dns_records.name',
        'platform_dns_records.value',
        'platform_dns_records.ttl',
        'platform_dns_records.priority',
        'platform_dns_records.weight',
        'platform_dns_records.port',
        'platform_dns_records.disabled',
        'platform_dns_records.created_at',
        'platform_dns_records.updated_at',
    ];

    protected array $allowedDirections = ['asc', 'desc'];

    public function search(?string $search): self
    {
        if ($search) {
            $escapedSearch = $this->escapeLike($search);
            $pattern = sprintf('%%%s%%', $escapedSearch);

            $this->where(function ($query) use ($pattern): void {
                $query->where('platform_dns_records.name', 'ilike', $pattern);
            });
        }

        return $this;
    }

    public function filterByType(?string $type): self
    {
        if ($type) {
            $this->where('platform_dns_records.type', $type);
        }

        return $this;
    }

    public function filterByTTL(?string $ttl): self
    {
        if ($ttl) {
            $this->where('platform_dns_records.ttl', $ttl);
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
            } else {
                switch ($sortable) {
                    case 'latest':
                        return $this->latest('platform_dns_records.created_at');
                    case 'oldest':
                        return $this->oldest('platform_dns_records.created_at');
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
                $normalizedDirection = strtolower((string) $direction);

                if (in_array($field, $this->allowedFields, true) && in_array($normalizedDirection, $this->allowedDirections, true)) {
                    $this->orderBy($field, $normalizedDirection);
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
