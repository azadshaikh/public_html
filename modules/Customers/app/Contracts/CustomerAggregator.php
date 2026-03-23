<?php

declare(strict_types=1);

namespace Modules\Customers\Contracts;

interface CustomerAggregator
{
    /**
     * Return a full summary for a customer profile.
     *
     * @return array<string, mixed>
     */
    public function getCustomerSummary(int $customerId): array;

    /**
     * Return billing totals, outstanding balances, and payment history summary.
     *
     * @return array<string, mixed>
     */
    public function getCustomerBillingSummary(int $customerId): array;

    /**
     * Return subscription status and plan information.
     *
     * @return array<string, mixed>
     */
    public function getCustomerSubscriptionSummary(int $customerId): array;

    /**
     * Return activity summary for customer interactions.
     *
     * @return array<string, mixed>
     */
    public function getCustomerActivitySummary(int $customerId): array;
}
