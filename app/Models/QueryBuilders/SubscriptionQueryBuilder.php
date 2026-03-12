<?php

namespace App\Models\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Pagination\LengthAwarePaginator;

class SubscriptionQueryBuilder extends Builder
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
            $query->where('websites.domain_name', 'ilike', sprintf('%%%s%%', $search))
                ->orWhere('subscriptions_plans.name', 'ilike', sprintf('%%%s%%', $search))
                ->orWhere('users.first_name', 'ilike', sprintf('%%%s%%', $search));
        });
    }

    public function filterByStatus(string|array|null $status): self
    {
        if (! $status) {
            return $this;
        }

        if (is_array($status)) {
            return $this->whereIn('stripe_status', $status);
        }

        return $this->where('stripe_status', $status);
    }

    public function filterByStartDate(?array $start_at): self
    {
        if (! $start_at) {
            return $this;
        }

        if (isset($start_at['from'])) {
            $this->whereDate('subscriptions.starts_at', '>=', $start_at['from']);
        }

        if (isset($start_at['to'])) {
            $this->whereDate('subscriptions.starts_at', '<=', $start_at['to']);
        }

        return $this;
    }

    public function filterByEndDate(?array $end_at): self
    {
        if (! $end_at) {
            return $this;
        }

        if (isset($end_at['from'])) {
            $this->whereDate('subscriptions.ends_at', '>=', $end_at['from']);
        }

        if (isset($end_at['to'])) {
            $this->whereDate('subscriptions.ends_at', '<=', $end_at['to']);
        }

        return $this;
    }

    public function filterByPlanId(?array $plan_id): self
    {
        if (! $plan_id) {
            return $this;
        }

        return $this->where('subscriptions.plan_id', $plan_id);
    }

    public function filterByUserId(?array $user_id): self
    {
        if (! $user_id) {
            return $this;
        }

        return $this->where('subscriptions.user_id', $user_id);
    }

    public function filterByDate(?array $date): self
    {
        if (! $date) {
            return $this;
        }

        if (isset($date['from'])) {
            $this->whereDate('subscriptions.created_at', '>=', $date['from']);
        }

        if (isset($date['to'])) {
            $this->whereDate('subscriptions.created_at', '<=', $date['to']);
        }

        return $this;
    }

    public function filterByCreator(string|array|null $creatorIds): self
    {
        if (! $creatorIds) {
            return $this;
        }

        if (is_array($creatorIds)) {
            return $this->whereIn('subscriptions.created_by', $creatorIds);
        }

        return $this->where('subscriptions.created_by', $creatorIds);
    }

    public function filterBySortable(string|array|null $sortable): self
    {
        if (! $sortable) {
            return $this;
        }

        if ($sortable === 'latest') {
            return $this->latest('subscriptions.created_at');
        }

        if ($sortable === 'oldest') {
            return $this->oldest('subscriptions.created_at');
        }

        if ($sortable === 'latest_updated') {
            return $this->latest('subscriptions.updated_at');
        }

        if ($sortable === 'oldest_updated') {
            return $this->oldest('subscriptions.updated_at');
        }

        return $this;
    }

    public function sortBy(?string $sortBy): self
    {
        if (! $sortBy) {
            return $this;
        }

        return match ($sortBy) {
            'latest' => $this->latest('subscriptions.created_at'),
            'oldest' => $this->oldest('subscriptions.created_at'),
            'latest_updated' => $this->latest('subscriptions.updated_at'),
            'oldest_updated' => $this->oldest('subscriptions.updated_at'),
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

        return $this->paginate((int) $limit, ['*'], 'page', (int) $page);
    }
}
