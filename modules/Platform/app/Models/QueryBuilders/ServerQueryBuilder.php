<?php

namespace Modules\Platform\Models\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;

class ServerQueryBuilder extends Builder
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

        $escapedSearch = addcslashes($search, '\\%_');
        $pattern = sprintf('%%%s%%', $escapedSearch);
        $driver = $this->getModel()->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return $this->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("name ILIKE ? ESCAPE '\\\\'", [$pattern])
                    ->orWhereRaw("uid ILIKE ? ESCAPE '\\\\'", [$pattern])
                    ->orWhereRaw("ip ILIKE ? ESCAPE '\\\\'", [$pattern])
                    ->orWhereRaw("driver ILIKE ? ESCAPE '\\\\'", [$pattern])
                    ->orWhereRaw("COALESCE(metadata->>'server_os', '') ILIKE ? ESCAPE '\\\\'", [$pattern]);
            });
        }

        return $this->where(function (Builder $query) use ($pattern): void {
            $query->where('name', 'like', $pattern)
                ->orWhere('uid', 'like', $pattern)
                ->orWhere('ip', 'like', $pattern)
                ->orWhere('driver', 'like', $pattern)
                ->orWhereRaw("COALESCE(json_extract(metadata, '$.server_os'), '') LIKE ?", [$pattern]);
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
            return $this->whereIn('group', $groups);
        }

        return $this->where('group', $groups);
    }

    public function filterByProvider(string|array|null $providerIds): self
    {
        if (! $providerIds) {
            return $this;
        }

        if (is_array($providerIds)) {
            return $this->whereHas('providers', function (Builder $query) use ($providerIds): void {
                $query->whereIn('platform_providers.id', $providerIds);
            });
        }

        return $this->whereHas('providers', function (Builder $query) use ($providerIds): void {
            $query->where('platform_providers.id', $providerIds);
        });
    }

    public function filterByMonitoring(?bool $monitoring): self
    {
        if ($monitoring === null) {
            return $this;
        }

        return $this->where('monitor', $monitoring);
    }

    public function filterByDriver(?string $driver): self
    {
        if (! $driver) {
            return $this;
        }

        return $this->where('driver', $driver);
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
            'ip_asc' => $this->orderBy('ip', 'ASC'),
            'ip_desc' => $this->orderBy('ip', 'DESC'),
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
}
