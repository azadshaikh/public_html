<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Contracts;

/**
 * SubscriptionAggregator Contract
 *
 * Interface for services that provide aggregated subscription data.
 * Used by the Customer module to display subscription summaries.
 */
interface SubscriptionAggregator
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
    public function getCustomerSubscriptionSummary(int $customerId): array;

    /**
     * Get active subscriptions count for a customer.
     */
    public function getCustomerActiveSubscriptionsCount(int $customerId): int;

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
    public function getCustomerFeatures(int $customerId): array;

    /**
     * Check if customer has access to a specific feature.
     */
    public function customerHasFeature(int $customerId, string $featureCode): bool;

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
    public function getCustomerSubscriptionHistory(int $customerId, int $limit = 10): array;

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
    public function getAvailablePlans(): array;
}
