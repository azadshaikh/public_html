<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Services;

use Illuminate\Support\Facades\Date;
use Modules\Billing\Models\Credit;
use Modules\Customers\Models\Customer;
use Modules\Subscriptions\Contracts\SubscriptionAggregator;
use Modules\Subscriptions\Models\Plan;
use Modules\Subscriptions\Models\PlanPrice;
use Modules\Subscriptions\Models\Subscription;

class SubscriptionService implements SubscriptionAggregator
{
    /**
     * Get subscription summary for a customer.
     *
     * @return array{
     *     has_subscription: bool,
     *     plan_name: ?string,
     *     plan_code: ?string,
     *     status: ?string,
     *     billing_cycle: ?string,
     *     price: ?float,
     *     currency: string,
     *     current_period_start: ?string,
     *     current_period_end: ?string,
     *     on_trial: bool,
     *     trial_ends_at: ?string,
     *     on_grace_period: bool,
     *     cancels_at: ?string
     * }
     */
    public function getCustomerSubscriptionSummary(int $customerId): array
    {
        $subscription = $this->getActiveSubscription($customerId);

        if (! $subscription instanceof Subscription) {
            return [
                'has_subscription' => false,
                'plan_name' => null,
                'plan_code' => null,
                'status' => null,
                'billing_cycle' => null,
                'price' => null,
                'currency' => 'USD',
                'current_period_start' => null,
                'current_period_end' => null,
                'on_trial' => false,
                'trial_ends_at' => null,
                'on_grace_period' => false,
                'cancels_at' => null,
            ];
        }

        return [
            'has_subscription' => true,
            'plan_name' => $subscription->plan->name,
            'plan_code' => $subscription->plan->code,
            'status' => $subscription->status,
            'billing_cycle' => $subscription->plan->billing_cycle,
            'price' => (float) $subscription->price,
            'currency' => $subscription->currency,
            'current_period_start' => $subscription->current_period_start?->toIso8601String(),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'on_trial' => $subscription->on_trial,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'on_grace_period' => $subscription->on_grace_period,
            'cancels_at' => $subscription->cancels_at?->toIso8601String(),
        ];
    }

    /**
     * Get active subscriptions count for a customer.
     */
    public function getCustomerActiveSubscriptionsCount(int $customerId): int
    {
        return Subscription::query()
            ->where('customer_id', $customerId)
            ->active()
            ->count();
    }

    /**
     * Get feature availability for a customer.
     *
     * @return array<string, array{
     *     available: bool,
     *     type: string,
     *     value: mixed,
     *     limit: ?int,
     *     used: int,
     *     remaining: ?int
     * }>
     */
    public function getCustomerFeatures(int $customerId): array
    {
        $subscription = $this->getActiveSubscription($customerId);

        if (! $subscription instanceof Subscription) {
            return [];
        }

        $features = [];

        foreach ($subscription->plan->features as $feature) {
            $used = $feature->isMetered()
                ? $subscription->getCurrentUsage($feature->code)
                : 0;

            $limit = $feature->getLimit();
            $remaining = null;

            if ($limit !== null && $limit > 0) {
                $remaining = max(0, $limit - $used);
            } elseif ($limit === -1) {
                $remaining = -1; // Unlimited
            }

            $features[$feature->code] = [
                'available' => $feature->isEnabled(),
                'type' => $feature->type,
                'value' => $feature->value,
                'limit' => $limit,
                'used' => $used,
                'remaining' => $remaining,
            ];
        }

        return $features;
    }

    /**
     * Check if customer has access to a specific feature.
     */
    public function customerHasFeature(int $customerId, string $featureCode): bool
    {
        $subscription = $this->getActiveSubscription($customerId);

        if (! $subscription instanceof Subscription) {
            return false;
        }

        return $subscription->hasFeature($featureCode);
    }

    /**
     * Get subscription history for a customer.
     *
     * @return array<int, array{
     *     id: int,
     *     plan_name: string,
     *     status: string,
     *     billing_cycle: string,
     *     started_at: string,
     *     ended_at: ?string,
     *     price: float,
     *     currency: string
     * }>
     */
    public function getCustomerSubscriptionHistory(int $customerId, int $limit = 10): array
    {
        $subscriptions = Subscription::query()
            ->with('plan')
            ->where('customer_id', $customerId)->latest()
            ->limit($limit)
            ->get();

        return $subscriptions->map(fn (Subscription $subscription): array => [
            'id' => $subscription->id,
            'plan_name' => $subscription->plan->name,
            'status' => $subscription->status,
            'billing_cycle' => $subscription->plan->billing_cycle,
            'started_at' => $subscription->created_at->toIso8601String(),
            'ended_at' => $subscription->ended_at?->toIso8601String(),
            'price' => (float) $subscription->price,
            'currency' => $subscription->currency,
        ])->all();
    }

    /**
     * Get all available plans.
     *
     * @return array<int, array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     description: ?string,
     *     billing_cycle: string,
     *     price: float,
     *     currency: string,
     *     features: array<string, mixed>
     * }>
     */
    public function getAvailablePlans(): array
    {
        $plans = Plan::getActivePlans()->load('features');

        return $plans->map(fn (Plan $plan): array => [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'billing_cycle' => $plan->billing_cycle,
            'price' => (float) $plan->price,
            'currency' => $plan->currency,
            'features' => $plan->getFeaturesList(),
        ])->all();
    }

    /**
     * Subscribe a customer to a plan.
     *
     * @param  array<string, mixed>  $options
     */
    public function subscribeCustomer(int $customerId, int $planId, array $options = []): Subscription
    {
        $plan = Plan::query()->findOrFail($planId);

        // Use PlanPrice values when a specific price is selected, otherwise fall back to Plan defaults
        $planPrice = isset($options['plan_price_id'])
            ? PlanPrice::query()
                ->where('plan_id', $plan->id)
                ->where('is_active', true)
                ->find((int) $options['plan_price_id'])
            : null;

        $price = $planPrice?->price ?? $plan->price; // @phpstan-ignore nullsafe.neverNull
        $currency = $planPrice?->currency ?? $plan->currency; // @phpstan-ignore nullsafe.neverNull
        $billingCycle = $planPrice?->billing_cycle ?? $plan->billing_cycle; // @phpstan-ignore nullsafe.neverNull

        $now = Date::now();
        $trialEndsAt = $plan->trial_days > 0 ? $now->copy()->addDays($plan->trial_days) : null;
        $periodStart = $trialEndsAt ?? $now;

        $periodEnd = match ($billingCycle) {
            Plan::CYCLE_MONTHLY => $periodStart->copy()->addMonth(),
            Plan::CYCLE_QUARTERLY => $periodStart->copy()->addMonths(3),
            Plan::CYCLE_YEARLY => $periodStart->copy()->addYear(),
            Plan::CYCLE_LIFETIME => $periodStart->copy()->addYears(100),
            default => $periodStart->copy()->addMonth(),
        };

        // End any existing active/trialing subscriptions to prevent duplicates
        $previousSubscription = $this->endActiveSubscriptions($customerId);

        return Subscription::query()->create([
            'customer_id' => $customerId,
            'plan_id' => $planId,
            'plan_price_id' => $planPrice?->id,
            'previous_plan_id' => $options['previous_plan_id'] ?? $previousSubscription?->plan_id,
            'status' => $trialEndsAt ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE,
            'billing_cycle' => $billingCycle,
            'price' => $price,
            'currency' => $currency,
            'trial_ends_at' => $trialEndsAt,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'plan_changed_at' => $options['plan_changed_at'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    /**
     * Cancel a customer's subscription.
     */
    public function cancelCustomerSubscription(int $customerId, bool $immediately = false): bool
    {
        $subscription = $this->getActiveSubscription($customerId);

        if (! $subscription instanceof Subscription) {
            return false;
        }

        $subscription->cancel($immediately);

        return true;
    }

    /**
     * Change a customer's plan.
     *
     * @param  array<string, mixed>  $options
     */
    public function changeCustomerPlan(int $customerId, int $planId, array $options = []): ?Subscription
    {
        $currentSubscription = $this->getActiveSubscription($customerId);

        if ($currentSubscription instanceof Subscription) {
            $creditAmount = $this->calculateProrationCredit($currentSubscription);

            if ($creditAmount > 0) {
                $this->issueProrationCredit($customerId, $creditAmount, $currentSubscription);
            }

            $currentSubscription->cancel(true);
            $options['previous_plan_id'] = $currentSubscription->plan_id;
        }

        return $this->subscribeCustomer($customerId, $planId, $options);
    }

    /**
     * Get plans as select options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getPlanOptions(): array
    {
        return Plan::getActivePlans()
            ->map(fn (Plan $plan): array => [
                'value' => (string) $plan->id,
                'label' => $plan->name.' - '.$plan->formatted_price.'/'.$plan->billing_cycle_label,
            ])
            ->all();
    }

    /**
     * Get billing cycle options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getBillingCycleOptions(): array
    {
        return [
            ['value' => Plan::CYCLE_MONTHLY, 'label' => 'Monthly'],
            ['value' => Plan::CYCLE_QUARTERLY, 'label' => 'Quarterly'],
            ['value' => Plan::CYCLE_YEARLY, 'label' => 'Yearly'],
            ['value' => Plan::CYCLE_LIFETIME, 'label' => 'Lifetime'],
        ];
    }

    /**
     * Get subscription status options.
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
     * Get active subscription for a customer.
     */
    protected function getActiveSubscription(int $customerId): ?Subscription
    {
        return Subscription::query()
            ->with('plan.features')
            ->where('customer_id', $customerId)
            ->valid()
            ->latest()
            ->first();
    }

    /**
     * Get the customer model class.
     * Uses dynamic resolution through container.
     */
    protected function getCustomerModelClass(): string
    {
        return Customer::class;
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

    protected function calculateProrationCredit(Subscription $subscription): float
    {
        if ($subscription->status === Subscription::STATUS_TRIALING) {
            return 0.0;
        }

        $plan = $subscription->plan;
        if (! $plan && $subscription->plan_id) {
            $plan = Plan::query()->find($subscription->plan_id);
        }

        if ($plan && $plan->billing_cycle === Plan::CYCLE_LIFETIME) {
            return 0.0;
        }

        if ((float) $subscription->price <= 0) {
            return 0.0;
        }

        if (! $subscription->current_period_end || ! $subscription->current_period_start) {
            return 0.0;
        }

        if ($subscription->current_period_end->isPast()) {
            return 0.0;
        }

        $totalSeconds = max(1, $subscription->current_period_start->diffInSeconds($subscription->current_period_end));
        $remainingSeconds = Date::now()->diffInSeconds($subscription->current_period_end, false);

        if ($remainingSeconds <= 0) {
            return 0.0;
        }

        $fraction = $remainingSeconds / $totalSeconds;

        return round((float) $subscription->price * $fraction, 2);
    }

    protected function issueProrationCredit(int $customerId, float $amount, Subscription $subscription): void
    {
        if ($amount <= 0) {
            return;
        }

        if (! class_exists(Credit::class)) {
            return;
        }

        if (function_exists('active_modules') && ! active_modules('billing')) {
            return;
        }

        Credit::query()->create([
            'customer_id' => $customerId,
            'credit_number' => Credit::generateCreditNumber(),
            'amount' => $amount,
            'amount_remaining' => $amount,
            'currency' => $subscription->currency,
            'type' => Credit::TYPE_REFUND_CREDIT,
            'status' => Credit::STATUS_ACTIVE,
            'reason' => 'Plan change proration credit',
            'metadata' => [
                'subscription_id' => $subscription->id,
                'previous_plan_id' => $subscription->plan_id,
            ],
        ]);
    }
}
