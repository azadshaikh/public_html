<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Modules\Customers\Models\Customer;
use Modules\Subscriptions\Definitions\SubscriptionDefinition;
use Modules\Subscriptions\Http\Resources\SubscriptionResource;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanPrice;
use Modules\Subscriptions\Models\Subscription;

class SubscriptionScaffoldService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new SubscriptionDefinition;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        return [
            'total' => Subscription::query()->whereNull('deleted_at')->count(),
            Subscription::STATUS_ACTIVE => Subscription::query()->where('status', Subscription::STATUS_ACTIVE)->whereNull('deleted_at')->count(),
            Subscription::STATUS_TRIALING => Subscription::query()->where('status', Subscription::STATUS_TRIALING)->whereNull('deleted_at')->count(),
            Subscription::STATUS_PAST_DUE => Subscription::query()->where('status', Subscription::STATUS_PAST_DUE)->whereNull('deleted_at')->count(),
            Subscription::STATUS_CANCELED => Subscription::query()->where('status', Subscription::STATUS_CANCELED)->whereNull('deleted_at')->count(),
            'trash' => Subscription::onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // FORM OPTIONS
    // ================================================================

    /**
     * Get plan options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getPlanOptions(): array
    {
        return Plan::getActivePlans()
            ->map(function (Plan $plan): array {
                /** @var PlanPrice|null $firstActivePrice */
                $firstActivePrice = $plan->prices
                    ->where('is_active', true)
                    ->sortBy('sort_order')
                    ->first();

                $label = $firstActivePrice
                    ? $plan->name.' - '.$firstActivePrice->billing_cycle_label.' — '.$firstActivePrice->formatted_price
                    : $plan->name;

                return [
                    'value' => (string) $plan->id,
                    'label' => $label,
                ];
            })
            ->all();
    }

    /**
     * Get active billing option choices keyed by plan ID.
     *
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    public function getPlanPriceOptionsByPlan(): array
    {
        return Plan::getActivePlans()
            ->mapWithKeys(function (Plan $plan): array {
                $options = $plan->prices
                    ->where('is_active', true)
                    ->sortBy('sort_order')
                    ->map(fn (PlanPrice $price): array => [
                        'value' => (string) $price->id,
                        'label' => $price->billing_cycle_label.' — '.$price->formatted_price,
                    ])
                    ->values()
                    ->all();

                return [(string) $plan->id => $options];
            })
            ->all();
    }

    /**
     * Get status options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getStatusOptions(): array
    {
        return [
            ['value' => Subscription::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => Subscription::STATUS_TRIALING, 'label' => 'Trial'],
            ['value' => Subscription::STATUS_PAST_DUE, 'label' => 'Past Due'],
            ['value' => Subscription::STATUS_CANCELED, 'label' => 'Canceled'],
            ['value' => Subscription::STATUS_EXPIRED, 'label' => 'Expired'],
            ['value' => Subscription::STATUS_PAUSED, 'label' => 'Paused'],
        ];
    }

    /**
     * Get customer options for forms.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function getCustomerOptions(): array
    {
        $customerModel = config('subscriptions.customer_model', Customer::class);

        if (! class_exists($customerModel)) {
            return [];
        }

        return $customerModel::query()
            ->select('id', 'company_name', 'contact_first_name', 'contact_last_name')
            ->limit(100)
            ->get()
            ->map(fn ($customer): array => [
                'value' => $customer->id,
                'label' => $customer->company_name ?: trim(($customer->contact_first_name ?? '').' '.($customer->contact_last_name ?? '')),
            ])
            ->toArray();
    }

    protected function getResourceClass(): ?string
    {
        return SubscriptionResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'plan:id,code,name',
            'planPrice:id,plan_id,billing_cycle,price,currency',
            'customer:id,company_name,contact_first_name,contact_last_name,email',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    // ================================================================
    // CUSTOM FILTER HANDLING
    // ================================================================

    protected function applyCustomFilters(Builder $query, Request $request): void
    {
        if ($request->filled('filter_plan_id')) {
            $query->where('plan_id', $request->input('filter_plan_id'));
        }
    }

    // ================================================================
    // DATA PREPARATION
    // ================================================================

    protected function prepareCreateData(array $data): array
    {
        $plan = Plan::query()->findOrFail($data['plan_id']);
        $planPrice = PlanPrice::query()
            ->where('id', (int) $data['plan_price_id'])
            ->where('plan_id', $plan->id)
            ->where('is_active', true)
            ->firstOrFail();

        $customerId = (int) $data['customer_id'];

        $now = Date::now();
        $trialDays = (int) ($data['trial_days'] ?? $plan->trial_days);
        $trialEndsAt = $trialDays > 0
            ? $now->copy()->addDays($trialDays)
            : null;

        $periodStart = $trialEndsAt ?? $now;

        $periodEnd = match ($planPrice->billing_cycle) {
            Plan::CYCLE_MONTHLY => $periodStart->copy()->addMonth(),
            Plan::CYCLE_QUARTERLY => $periodStart->copy()->addMonths(3),
            Plan::CYCLE_YEARLY => $periodStart->copy()->addYear(),
            Plan::CYCLE_LIFETIME => $periodStart->copy()->addYears(100),
            default => $periodStart->copy()->addMonth(),
        };

        $previousSubscription = $this->endActiveSubscriptions($customerId);

        $data['status'] = $trialEndsAt ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE;
        $data['plan_price_id'] = $planPrice->id;
        $data['billing_cycle'] = $planPrice->billing_cycle;
        $data['price'] = (float) $planPrice->price;
        $data['currency'] = $planPrice->currency;
        $data['trial_ends_at'] = $trialEndsAt;
        $data['current_period_start'] = $periodStart;
        $data['current_period_end'] = $periodEnd;
        $data['previous_plan_id'] ??= $previousSubscription?->plan_id;
        $data['plan_changed_at'] = $previousSubscription instanceof Subscription ? $now : null;

        unset($data['trial_days']);

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        return $data;
    }

    protected function endActiveSubscriptions(int|string $customerId): ?Subscription
    {
        $customerId = (int) $customerId;
        $current = Subscription::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_PAUSED,
            ])
            ->latest()
            ->first();

        if (! $current) {
            return null;
        }

        $current->canceled_at = Date::now();
        $current->status = Subscription::STATUS_CANCELED;
        $current->ended_at = Date::now();
        $current->cancel_at_period_end = false;
        $current->cancels_at = null;
        $current->save();

        return $current;
    }
}
