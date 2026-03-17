<?php

namespace Modules\Platform\Models\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;

class WebsiteQueryBuilder extends Builder
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

        return $this->where(function ($query) use ($search): void {
            $query->where('name', 'ilike', sprintf('%%%s%%', $search))
                ->orWhere('uid', 'ilike', sprintf('%%%s%%', $search))
                ->orWhere('domain', 'ilike', sprintf('%%%s%%', $search));
        });
    }

    public function filterByTypeslug(string|array|null $typeslug): self
    {
        if (! $typeslug) {
            return $this;
        }

        if ($typeslug === 'paid') {
            return $this->where('type', 'paid');
        }

        if ($typeslug === 'trial') {
            return $this->where('type', 'trial');
        }

        if ($typeslug === 'other') {
            return $this->whereNotIn('type', ['paid', 'trial']);
        }

        return $this;
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

    public function filterByType(string|array|null $type): self
    {
        if (! $type) {
            return $this;
        }

        if (is_array($type)) {
            return $this->whereIn('type', $type);
        }

        return $this->where('type', $type);
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

    public function filterByExpiredDate(?array $date): self
    {
        if (! $date) {
            return $this;
        }

        if (isset($date['from'])) {
            $this->whereDate('expired_on', '>=', $date['from']);
        }

        if (isset($date['to'])) {
            $this->whereDate('expired_on', '<=', $date['to']);
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

    /**
     * @deprecated owner_id column has been removed. Customer data is now in customer_data JSON.
     */
    public function filterByOwner(string|array|null $ownerIds): self
    {
        // No-op: owner_id column was dropped in favour of customer_data JSON.
        return $this;
    }

    public function filterByServer(string|array|null $serverIds): self
    {
        if (! $serverIds) {
            return $this;
        }

        if (is_array($serverIds)) {
            return $this->whereIn('server_id', $serverIds);
        }

        return $this->where('server_id', $serverIds);
    }

    public function filterByAgency(string|array|null $agencyIds): self
    {
        if (! $agencyIds) {
            return $this;
        }

        if (is_array($agencyIds)) {
            return $this->whereIn('agency_id', $agencyIds);
        }

        return $this->where('agency_id', $agencyIds);
    }

    public function isAgencyWebsite(): self
    {
        $driver = $this->getModel()->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            return $this->whereRaw("COALESCE((metadata->>'is_agency')::boolean, false) = true");
        }

        return $this->whereRaw("JSON_EXTRACT(metadata, '$.is_agency') = true");
    }

    public function filterByDnsProvider(string|array|null $dnsProviders): self
    {
        if (! $dnsProviders) {
            return $this;
        }

        if (is_array($dnsProviders)) {
            return $this->whereIn('dns_provider', $dnsProviders);
        }

        return $this->where('dns_provider', $dnsProviders);
    }

    public function filterBySslEnabled(?bool $sslEnabled): self
    {
        if ($sslEnabled === null) {
            return $this;
        }

        return $this->where('ssl_enabled', $sslEnabled);
    }

    public function filterBySetupComplete(?bool $setupComplete): self
    {
        if ($setupComplete === null) {
            return $this;
        }

        return $this->where('setup_complete_flag', $setupComplete);
    }

    public function filterByExpired(?bool $expired): self
    {
        if ($expired === null) {
            return $this;
        }

        if ($expired) {
            return $this->where('expired_on', '<=', now());
        }

        return $this->where(function ($query): void {
            $query->where('expired_on', '>', now())
                ->orWhereNull('expired_on');
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
            'domain_asc' => $this->orderBy('domain', 'ASC'),
            'domain_desc' => $this->orderBy('domain', 'DESC'),
            'type_asc' => $this->orderBy('type', 'ASC'),
            'type_desc' => $this->orderBy('type', 'DESC'),
            'status_asc' => $this->orderBy('status', 'ASC'),
            'status_desc' => $this->orderBy('status', 'DESC'),
            'expired_on_asc' => $this->oldest('expired_on'),
            'expired_on_desc' => $this->latest('expired_on'),
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
        $page = $pagination['page'] ?? 1;

        return $this->paginate($limit, ['*'], 'page', $page);
    }
}
