<?php

declare(strict_types=1);

namespace Modules\Billing\Contracts;

/**
 * BillingAggregator Contract
 *
 * Interface for services that provide aggregated billing data.
 * Used by the Customer module to display billing summaries.
 */
interface BillingAggregator
{
    /**
     * Get billing summary for a customer.
     *
     * @return array{
     *     total_spent: float,
     *     outstanding_balance: float,
     *     currency: string,
     *     invoice_count: int,
     *     last_payment_date: ?string,
     *     last_payment_amount: ?float
     * }
     */
    public function getCustomerBillingSummary(int $customerId): array;

    /**
     * Get total amount spent by a customer.
     */
    public function getCustomerTotalSpent(int $customerId): float;

    /**
     * Get outstanding balance for a customer.
     */
    public function getCustomerOutstandingBalance(int $customerId): float;

    /**
     * Get payment history for a customer.
     *
     * @return array<int, array{
     *     id: int,
     *     amount: float,
     *     currency: string,
     *     status: string,
     *     date: string,
     *     method: string
     * }>
     */
    public function getCustomerPaymentHistory(int $customerId, int $limit = 10): array;

    /**
     * Get invoices for a customer.
     *
     * @return array<int, array{
     *     id: int,
     *     number: string,
     *     total: float,
     *     currency: string,
     *     status: string,
     *     due_date: string,
     *     created_at: string
     * }>
     */
    public function getCustomerInvoices(int $customerId, int $limit = 10): array;
}
