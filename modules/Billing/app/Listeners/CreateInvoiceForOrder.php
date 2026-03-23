<?php

declare(strict_types=1);

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\BillingService;
use Modules\Orders\Events\OrderPaid;

/**
 * Creates a Billing invoice + payment record when an Order is marked as paid.
 *
 * Registered in BillingServiceProvider::boot().
 * Runs synchronously inside the DB transaction opened by OrderService::markPaid(),
 * so a listener failure will roll back the order status update atomically.
 */
class CreateInvoiceForOrder
{
    public function __construct(private readonly BillingService $billingService) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        // Only create invoices for orders with a positive total.
        if ((float) $order->total <= 0) {
            return;
        }

        // Idempotency: skip if an invoice for this order already exists.
        if (Invoice::query()->where('metadata->order_id', (string) $order->id)->exists()) {
            Log::info('CreateInvoiceForOrder: invoice already exists, skipping.', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $order->loadMissing('customer');
        $customer = $order->customer;

        if (! $customer) {
            Log::error('CreateInvoiceForOrder: no customer found for order, skipping invoice.', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $billingEmail = (string) ($customer->billing_email ?: $customer->email ?? '');
        $billingName = (string) ($customer->contact_name ?: $customer->company_name ?? '');

        // Build description from order items.
        $order->loadMissing('items');
        $description = $order->items->pluck('name')->filter()->implode(' · ');
        if ($description === '') {
            $description = 'Order #'.$order->order_number;
        }

        $currency = strtoupper((string) $order->currency);
        $amountPaid = (float) $order->total;
        $discountAmount = (float) ($order->discount_amount ?? 0);
        $sessionId = (string) ($order->stripe_checkout_session_id ?? '');
        $paymentIntentId = (string) ($order->stripe_payment_intent_id ?? '');

        $invoice = $this->billingService->createInvoice([
            'customer_id' => $customer->id,
            'billing_name' => $billingName,
            'billing_email' => $billingEmail,
            'subtotal' => (float) ($order->subtotal ?? $amountPaid),
            'tax_amount' => (float) ($order->tax_amount ?? 0),
            'discount_amount' => $discountAmount,
            'total' => $amountPaid,
            'amount_paid' => $amountPaid,
            'amount_due' => 0,
            'currency' => $currency,
            'issue_date' => now(),
            'due_date' => now(),
            'paid_at' => $order->paid_at ?? now(),
            'status' => Invoice::STATUS_PAID,
            'payment_status' => Invoice::PAYMENT_STATUS_PAID,
            'notes' => $description,
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_number' => $order->order_number,
                'stripe_checkout_session_id' => $sessionId ?: null,
                'stripe_payment_intent_id' => $paymentIntentId ?: null,
            ],
        ]);

        $gatewayTxId = $sessionId ?: $paymentIntentId;

        $this->billingService->recordPayment([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => $amountPaid,
            'currency' => $currency,
            'exchange_rate' => 1.0,
            'payment_method' => 'card',
            'payment_gateway' => 'stripe',
            'status' => Payment::STATUS_COMPLETED,
            'gateway_transaction_id' => $gatewayTxId ?: null,
            'paid_at' => $order->paid_at ?? now(),
            'idempotency_key' => $sessionId !== '' && $sessionId !== '0' ? 'stripe_cs_'.$sessionId : 'order_'.$order->id,
            'notes' => $description,
        ]);
    }
}
