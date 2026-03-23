<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Subscriptions\Models\Subscription;

/**
 * Subscribable Contract
 *
 * Interface for models that can have subscriptions (typically customers).
 * Provides methods for subscription management and status checking.
 */
interface Subscribable
{
    /**
     * Get all subscriptions for this subscribable entity.
     */
    public function subscriptions(): HasMany;

    /**
     * Get the currently active subscription.
     */
    public function activeSubscription(): ?Subscription;

    /**
     * Check if the entity has an active subscription.
     */
    public function hasActiveSubscription(): bool;

    /**
     * Check if the entity is subscribed to a specific plan.
     */
    public function isSubscribedTo(int|string $planId): bool;

    /**
     * Check if the entity is on a trial period.
     */
    public function onTrial(): bool;

    /**
     * Check if the entity's subscription has ended grace period.
     */
    public function onGracePeriod(): bool;

    /**
     * Subscribe the entity to a plan.
     *
     * @param  array<string, mixed>  $options
     */
    public function subscribeTo(int $planId, array $options = []): Subscription;

    /**
     * Cancel the current subscription.
     */
    public function cancelSubscription(bool $immediately = false): bool;

    /**
     * Resume a cancelled subscription (if in grace period).
     */
    public function resumeSubscription(): bool;

    /**
     * Switch to a different plan.
     *
     * @param  array<string, mixed>  $options
     */
    public function changePlan(int $planId, array $options = []): Subscription;

    /**
     * Get subscription summary for the entity.
     *
     * @return array{
     *     has_subscription: bool,
     *     plan_name: ?string,
     *     status: ?string,
     *     billing_cycle: ?string,
     *     current_period_end: ?string,
     *     on_trial: bool,
     *     on_grace_period: bool
     * }
     */
    public function getSubscriptionSummary(): array;

    /**
     * Get all subscription history for the entity.
     *
     * @return Collection<int, Subscription>
     */
    public function getSubscriptionHistory(): Collection;
}
