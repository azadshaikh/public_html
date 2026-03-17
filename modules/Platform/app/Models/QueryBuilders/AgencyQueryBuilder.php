<?php

namespace Modules\Platform\Models\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;

class AgencyQueryBuilder extends Builder
{
    public function withTrashed(): self
    {
        return $this->withoutGlobalScope(SoftDeletingScope::class);
    }

    public function search(?string $search): self
    {
        if (! $search) {
            return $this;
        }

        $escapedSearch = $this->escapeLike($search);
        $pattern = sprintf('%%%s%%', $escapedSearch);
        $driver = $this->getModel()->getConnection()->getDriverName();

        return $this->where(function ($query) use ($pattern, $driver): void {
            $query->where('name', 'ilike', $pattern)
                ->orWhere('uid', 'ilike', $pattern)
                ->orWhere('email', 'ilike', $pattern);

            if ($driver === 'pgsql') {
                $query->orWhereRaw("COALESCE(metadata->>'branding_name', '') ilike ?", [$pattern])
                    ->orWhereRaw("COALESCE(metadata->>'branding_website', '') ilike ?", [$pattern]);
            }
        });
    }

    public function filterByStatus(string|array|null $status): self
    {
        if (! $status) {
            return $this;
        }

        if (is_array($status)) {
            return $this->whereIn('status', $status);
        }

        return $this->where('status', $status);
    }

    public function filterByDate(?array $date): self
    {
        if (! $date) {
            return $this;
        }

        if (isset($date['from'])) {
            $this->whereDate('created_at', '>=', $date['from']);
        }

        if (isset($date['to'])) {
            $this->whereDate('created_at', '<=', $date['to']);
        }

        return $this;
    }

    public function filterByCreator(string|array|null $creatorIds): self
    {
        if (! $creatorIds) {
            return $this;
        }

        if (is_array($creatorIds)) {
            return $this->whereIn('created_by', $creatorIds);
        }

        return $this->where('created_by', $creatorIds);
    }

    public function filterByGroup(string|array|null $groups): self
    {
        if (! $groups) {
            return $this;
        }

        if (is_array($groups)) {
            return $this->whereIn('type', $groups);
        }

        return $this->where('type', $groups);
    }

    public function filterByOwner(string|array|null $ownerIds): self
    {
        if (! $ownerIds) {
            return $this;
        }

        if (is_array($ownerIds)) {
            return $this->whereIn('owner_id', $ownerIds);
        }

        return $this->where('owner_id', $ownerIds);
    }

    public function filterByCountry(string|array|null $countryIds): self
    {
        if (! $countryIds) {
            return $this;
        }

        $values = is_array($countryIds) ? array_values($countryIds) : [$countryIds];

        return $this->whereHas('addresses', function ($query) use ($values): void {
            $query->whereIn('country_code', $values)
                ->orWhereIn('country', $values);
        });
    }

    public function filterByState(string|array|null $stateIds): self
    {
        if (! $stateIds) {
            return $this;
        }

        $values = is_array($stateIds) ? array_values($stateIds) : [$stateIds];

        return $this->whereHas('addresses', function ($query) use ($values): void {
            $query->whereIn('state_code', $values)
                ->orWhereIn('state', $values);
        });
    }

    public function filterByCity(string|array|null $cityIds): self
    {
        if (! $cityIds) {
            return $this;
        }

        $values = is_array($cityIds) ? array_values($cityIds) : [$cityIds];

        return $this->whereHas('addresses', function ($query) use ($values): void {
            $query->whereIn('city_code', $values)
                ->orWhereIn('city', $values);
        });
    }

    public function filterBySortable(string|array|null $sortable): self
    {
        if (! $sortable) {
            return $this;
        }

        if ($sortable === 'latest') {
            return $this->latest();
        }

        if ($sortable === 'oldest') {
            return $this->oldest();
        }

        if ($sortable === 'latest_updated') {
            return $this->latest('updated_at');
        }

        if ($sortable === 'oldest_updated') {
            return $this->oldest('updated_at');
        }

        return $this;
    }

    public function sortBy(?string $sortBy): self
    {
        if (! $sortBy) {
            return $this;
        }

        return match ($sortBy) {
            'latest' => $this->latest(),
            'oldest' => $this->oldest(),
            'latest_updated' => $this->latest('updated_at'),
            'oldest_updated' => $this->oldest('updated_at'),
            'name_asc' => $this->orderBy('name', 'ASC'),
            'name_desc' => $this->orderBy('name', 'DESC'),
            'email_asc' => $this->orderBy('email', 'ASC'),
            'email_desc' => $this->orderBy('email', 'DESC'),
            'city_asc' => $this->orderBy('name', 'ASC'),
            'city_desc' => $this->orderBy('name', 'DESC'),
            default => $this,
        };
    }

    // order
    public function orderResults(string|array|null $order): self
    {
        if (! $order) {
            return $this;
        }

        if (is_array($order)) {
            foreach ($order as $key => $value) {
                $this->orderBy($key, $value);
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
