<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\BillingAggregator;
use Modules\Billing\Models\Credit;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Refund;
use Modules\Billing\Models\Tax;
use Modules\Billing\Models\Transaction;

class BillingService implements BillingAggregator
{
    public function __construct(protected ?CurrencyService $currencyService = new CurrencyService) {}

    /**
     * Get the default currency.
     */
    public function getDefaultCurrency(): string
    {
        return $this->currencyService->getDefaultCurrency();
    }

    /**
     * Get the currency service instance.
     */
    public function currency(): CurrencyService
    {
        return $this->currencyService;
    }

    /**
     * Get billing summary for a customer.
     *
     * @return array{
     *     total_spent: float,
     *     outstanding_balance: float,
     *     currency: string,
     *     formatted_total_spent: string,
     *     formatted_outstanding_balance: string,
     *     invoice_count: int,
     *     last_payment_date: ?string,
     *     last_payment_amount: ?float
     * }
     */
    public function getCustomerBillingSummary(int $customerId): array
    {
        $currency = $this->getDefaultCurrency();
        $totalSpent = $this->getCustomerTotalSpent($customerId);
        $outstandingBalance = $this->getCustomerOutstandingBalance($customerId);
        $invoiceCount = Invoice::query()->where('customer_id', $customerId)->count();

        $lastPayment = Payment::query()->where('customer_id', $customerId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->latest('paid_at')
            ->first();

        return [
            'total_spent' => $totalSpent,
            'outstanding_balance' => $outstandingBalance,
            'currency' => $currency,
            'formatted_total_spent' => $this->currencyService->format($totalSpent, $currency),
            'formatted_outstanding_balance' => $this->currencyService->format($outstandingBalance, $currency),
            'invoice_count' => $invoiceCount,
            'last_payment_date' => $lastPayment?->paid_at?->format('Y-m-d'),
            'last_payment_amount' => $lastPayment?->amount,
        ];
    }

    /**
     * Get total amount spent by a customer.
     */
    public function getCustomerTotalSpent(int $customerId): float
    {
        return (float) Payment::query()->where('customer_id', $customerId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
    }

    /**
     * Get outstanding balance for a customer.
     */
    public function getCustomerOutstandingBalance(int $customerId): float
    {
        return (float) Invoice::query()->where('customer_id', $customerId)
            ->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_OVERDUE])
            ->sum('amount_due');
    }

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
    public function getCustomerPaymentHistory(int $customerId, int $limit = 10): array
    {
        return Payment::query()->where('customer_id', $customerId)->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'date' => $payment->paid_at?->format('Y-m-d') ?? $payment->created_at->format('Y-m-d'),
                'method' => $payment->payment_method,
            ])
            ->all();
    }

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
    public function getCustomerInvoices(int $customerId, int $limit = 10): array
    {
        return Invoice::query()->where('customer_id', $customerId)->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->invoice_number,
                'total' => (float) $invoice->total,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'created_at' => $invoice->created_at->format('Y-m-d'),
            ])
            ->all();
    }

    /**
     * Get available credits for a customer.
     */
    public function getCustomerAvailableCredits(int $customerId): float
    {
        return (float) Credit::query()->where('customer_id', $customerId)
            ->where('status', Credit::STATUS_ACTIVE)
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('amount_remaining');
    }

    /**
     * Get transaction history for a customer.
     */
    public function getCustomerTransactions(int $customerId, int $limit = 20): array
    {
        return Transaction::query()->where('customer_id', $customerId)->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $txn): array => [
                'id' => $txn->id,
                'transaction_id' => $txn->transaction_id,
                'type' => $txn->type,
                'amount' => (float) $txn->amount,
                'currency' => $txn->currency,
                'status' => $txn->status,
                'description' => $txn->description,
                'date' => $txn->created_at->format('Y-m-d H:i:s'),
            ])
            ->all();
    }

    /**
     * Calculate applicable taxes for a location and amount.
     *
     * @return array{
     *     taxes: array<int, array{name: string, rate: float, amount: float}>,
     *     total_tax: float
     * }
     */
    public function calculateTaxes(float $subtotal, ?string $country = null, ?string $state = null): array
    {
        $taxes = Tax::query()->effective()
            ->forLocation($country, $state)
            ->orderBy('priority')
            ->get();

        $taxDetails = [];
        $totalTax = 0;
        $taxableAmount = $subtotal;

        foreach ($taxes as $tax) {
            $taxAmount = $tax->calculateTax($tax->is_compound ? $taxableAmount + $totalTax : $subtotal);
            $totalTax += $taxAmount;

            $taxDetails[] = [
                'name' => $tax->name,
                'rate' => (float) $tax->rate,
                'amount' => $taxAmount,
            ];
        }

        return [
            'taxes' => $taxDetails,
            'total_tax' => $totalTax,
        ];
    }

    /**
     * Create an invoice for a customer.
     */
    public function createInvoice(array $data): Invoice
    {
        $data['invoice_number'] ??= Invoice::generateInvoiceNumber();
        $data['issue_date'] ??= now();
        $data['due_date'] ??= now()->addDays(30);
        $data['status'] ??= Invoice::STATUS_DRAFT;
        $data['payment_status'] ??= Invoice::PAYMENT_STATUS_UNPAID;
        $data['currency'] ??= $this->getDefaultCurrency();

        return Invoice::query()->create($data);
    }

    /**
     * Record a payment.
     */
    public function recordPayment(array $data): Payment
    {
        if (! empty($data['idempotency_key'])) {
            $existing = Payment::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($data) {
            $data['payment_number'] ??= Payment::generatePaymentNumber();
            $data['status'] ??= Payment::STATUS_PENDING;
            $data['currency'] ??= $this->getDefaultCurrency();

            $payment = Payment::query()->create($data);

            // Create transaction record
            Transaction::createFromPayment($payment);

            return $payment;
        });
    }

    /**
     * Process a refund.
     */
    public function processRefund(Payment $payment, float $amount, string $reason = '', ?string $idempotencyKey = null): Refund
    {
        $refundType = $amount >= $payment->amount ? Refund::TYPE_FULL : Refund::TYPE_PARTIAL;

        if ($idempotencyKey) {
            $existing = Refund::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($payment, $amount, $reason, $refundType, $idempotencyKey) {
            $refund = Refund::query()->create([
                'refund_number' => Refund::generateRefundNumber(),
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'customer_id' => $payment->customer_id,
                'idempotency_key' => $idempotencyKey,
                'amount' => $amount,
                'currency' => $payment->currency,
                'type' => $refundType,
                'status' => Refund::STATUS_PENDING,
                'reason' => $reason,
            ]);

            // Create transaction record
            Transaction::createFromRefund($refund);

            return $refund;
        });
    }

    /**
     * Issue credit to a customer.
     */
    public function issueCredit(int $customerId, float $amount, string $type = Credit::TYPE_CREDIT_NOTE, ?string $reason = null): Credit
    {
        return Credit::query()->create([
            'credit_number' => Credit::generateCreditNumber(),
            'customer_id' => $customerId,
            'amount' => $amount,
            'amount_remaining' => $amount,
            'currency' => $this->getDefaultCurrency(),
            'type' => $type,
            'status' => Credit::STATUS_ACTIVE,
            'reason' => $reason,
        ]);
    }

    /**
     * Get supported currencies.
     *
     * @return array<string, array{name: string, symbol: string, decimals: int, symbol_position: string}>
     */
    public function getSupportedCurrencies(): array
    {
        return $this->currencyService->getSupportedCurrencies();
    }

    /**
     * Format currency amount.
     */
    public function formatCurrency(float $amount, string $currency = 'USD'): string
    {
        return $this->currencyService->format($amount, $currency);
    }

    /**
     * Convert amount between currencies.
     */
    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency): float
    {
        return $this->currencyService->convert($amount, $fromCurrency, $toCurrency);
    }

    /**
     * Get exchange rate for a currency.
     */
    public function getExchangeRate(string $currency): float
    {
        return $this->currencyService->getExchangeRate($currency);
    }

    /**
     * Get invoice status options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getInvoiceStatusOptions(): array
    {
        return [
            ['value' => Invoice::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => Invoice::STATUS_PENDING, 'label' => 'Pending'],
            ['value' => Invoice::STATUS_SENT, 'label' => 'Sent'],
            ['value' => Invoice::STATUS_PAID, 'label' => 'Paid'],
            ['value' => Invoice::STATUS_PARTIAL, 'label' => 'Partial'],
            ['value' => Invoice::STATUS_OVERDUE, 'label' => 'Overdue'],
            ['value' => Invoice::STATUS_CANCELLED, 'label' => 'Cancelled'],
            ['value' => Invoice::STATUS_REFUNDED, 'label' => 'Refunded'],
        ];
    }

    /**
     * Get payment status options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getPaymentStatusOptions(): array
    {
        return [
            ['value' => Payment::STATUS_PENDING, 'label' => 'Pending'],
            ['value' => Payment::STATUS_PROCESSING, 'label' => 'Processing'],
            ['value' => Payment::STATUS_COMPLETED, 'label' => 'Completed'],
            ['value' => Payment::STATUS_FAILED, 'label' => 'Failed'],
            ['value' => Payment::STATUS_CANCELLED, 'label' => 'Cancelled'],
            ['value' => Payment::STATUS_REFUNDED, 'label' => 'Refunded'],
        ];
    }

    /**
     * Get payment method options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getPaymentMethodOptions(): array
    {
        return [
            ['value' => Payment::METHOD_CARD, 'label' => 'Credit Card'],
            ['value' => Payment::METHOD_BANK_TRANSFER, 'label' => 'Bank Transfer'],
            ['value' => Payment::METHOD_CASH, 'label' => 'Cash'],
            ['value' => Payment::METHOD_CHECK, 'label' => 'Check'],
            ['value' => Payment::METHOD_PAYPAL, 'label' => 'PayPal'],
            ['value' => Payment::METHOD_OTHER, 'label' => 'Other'],
        ];
    }

    /**
     * Get currency options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getCurrencyOptions(): array
    {
        return $this->currencyService->getCurrencyOptions();
    }
}
