<?php

namespace App\Models;

use App\Models\QueryBuilders\SubscriptionQueryBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Cashier\Subscription as CashierSubscription;
use Modules\Platform\Models\Website;
use Modules\Subscriptions\Models\Plan;

class Subscription extends CashierSubscription
{
    protected $table = 'subscriptions';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id', 'id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function newEloquentBuilder($query): SubscriptionQueryBuilder
    {
        return new SubscriptionQueryBuilder($query);
    }

    public static function getAllData(array $filter_arr = []): LengthAwarePaginator
    {
        return self::query()
            ->with(['user', 'website', 'plan'])
            ->withTrashed()
            ->search($filter_arr['search_text'] ?? null)
            ->filterByStatus($filter_arr['status'] ?? null)
            ->filterByDate($filter_arr['created_at'] ?? null)
            ->filterByStartDate($filter_arr['start_at'] ?? null)
            ->filterByEndDate($filter_arr['end_at'] ?? null)
            ->filterByPlanId($filter_arr['plan_id'] ?? null)
            ->filterByUserId($filter_arr['user_id'] ?? null)
            ->filterByCreator($filter_arr['added_by'] ?? null)
            ->filterBySortable($filter_arr['sortable'] ?? null)
            ->sortBy($filter_arr['sort_by'] ?? null)
            ->orderResults($filter_arr['order'] ?? null)
            ->paginateResults($filter_arr['pagelimit'] ?? null);
    }
}
