<?php

declare(strict_types=1);

namespace Modules\Orders\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Orders\Events\OrderCancelled;
use Modules\Orders\Events\OrderPaid;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderItem;

class OrderService
{
    /**
     * Maximum retry attempts when a duplicate order number collision occurs.
     */
    private const int ORDER_NUMBER_MAX_RETRIES = 5;

    /**
     * Generate a unique order number based on the configured format.
     * Uses `ilike` for PostgreSQL-safe pattern matching.
     */
    public function generateOrderNumber(): string
    {
        $prefix = (string) setting('orders_order_prefix', 'ORD-');
        $digitLength = (int) setting('orders_order_digit_length', 4);
        $format = (string) setting('orders_order_format', 'year_sequence');
        $now = now();

        [$datePart, $pattern] = match ($format) {
            'year_sequence' => [$now->format('Y').'-',  $prefix.$now->format('Y').'-'.'%'],
            'year_month_sequence' => [$now->format('Ym').'-', $prefix.$now->format('Ym').'-'.'%'],
            'sequence_only' => ['',                     $prefix.'%'],
            default => [$now->format('Ymd').'-', $prefix.$now->format('Ymd').'-'.'%'],
        };

        // Order by the integer value of the trailing digit sequence, not lexicographically.
        // This handles the edge case where sequences overflow the padded digitLength
        // (e.g. ORD-2026-10000 would sort before ORD-2026-9999 lexicographically).
        // SUBSTRING(... FROM '[0-9]+$') extracts the trailing numeric portion regardless of width.
        $lastOrder = Order::query()
            ->withTrashed()
            ->where('order_number', 'ilike', $pattern)
            ->orderByRaw("CAST(SUBSTRING(order_number FROM '[0-9]+$') AS INTEGER) DESC")
            ->first();

        if ($lastOrder) {
            $matches = [];
            $lastNumber = preg_match('/(\d+)$/', (string) $lastOrder->order_number, $matches) === 1
                ? (int) $matches[1]
                : 0;
            $next = $lastNumber + 1;
        } else {
            $next = (int) setting('orders_order_serial_number', 1);
        }

        $seq = str_pad((string) $next, $digitLength, '0', STR_PAD_LEFT);

        return $prefix.$datePart.$seq;
    }

    /**
     * Create a new order from checkout data.
     *
     * Expected $data keys:
     *   customer_id, type, currency, subtotal, discount_amount, tax_amount,
     *   total, coupon_id (optional), coupon_code (optional), notes (optional),
     *   items: array of [plan_id?, name, description?, quantity, unit_price, total]
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromCheckout(array $data): Order
    {
        return $this->createWithRetry($data);
    }

    /**
     * Attempt order creation with retry on duplicate order number (race condition).
     */
    private function createWithRetry(array $data, int $attempt = 1): Order
    {
        try {
            return DB::transaction(function () use ($data): Order {
                $order = Order::query()->create([
                    'order_number' => $this->generateOrderNumber(),
                    'customer_id' => $data['customer_id'] ?? null,
                    'type' => $data['type'] ?? Order::TYPE_ONE_TIME,
                    'status' => Order::STATUS_PENDING,
                    'subtotal' => $data['subtotal'] ?? 0,
                    'discount_amount' => $data['discount_amount'] ?? 0,
                    'tax_amount' => $data['tax_amount'] ?? 0,
                    'total' => $data['total'] ?? 0,
                    'currency' => $data['currency'] ?? 'INR',
                    'coupon_id' => $data['coupon_id'] ?? null,
                    'coupon_code' => $data['coupon_code'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'metadata' => $data['metadata'] ?? null,
                ]);

                foreach ($data['items'] ?? [] as $item) {
                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'plan_id' => $item['plan_id'] ?? null,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                        'unit_price' => $item['unit_price'] ?? 0,
                        'total' => $item['total'] ?? 0,
                        'metadata' => $item['metadata'] ?? null,
                    ]);
                }

                return $order->fresh(['items']);
            });
        } catch (QueryException $queryException) {
            // PostgreSQL unique violation = 23505
            if ($attempt < self::ORDER_NUMBER_MAX_RETRIES && str_contains($queryException->getMessage(), '23505')) {
                return $this->createWithRetry($data, $attempt + 1);
            }

            throw $queryException;
        }
    }

    /**
     * Mark an order as processing (Stripe checkout session created).
     */
    public function markPendingPayment(Order $order, string $stripeSessionId): Order
    {
        $order->update([
            'status' => Order::STATUS_PROCESSING,
            'stripe_checkout_session_id' => $stripeSessionId,
        ]);

        return $order->fresh();
    }

    /**
     * Mark an order as paid (Stripe payment confirmed).
     * Fires OrderPaid event inside a DB transaction, so listener failures
     * roll back the order status update atomically.
     */
    public function markPaid(Order $order, string $paymentIntentId): Order
    {
        return DB::transaction(function () use ($order, $paymentIntentId): Order {
            $order->update([
                'status' => Order::STATUS_ACTIVE,
                'stripe_payment_intent_id' => $paymentIntentId,
                'paid_at' => now(),
            ]);

            $order->refresh();

            event(new OrderPaid($order));

            return $order;
        });
    }

    /**
     * Cancel an order.
     * Fires OrderCancelled event.
     */
    public function cancel(Order $order): Order
    {
        $order->update(['status' => Order::STATUS_CANCELLED]);
        $order->refresh();

        event(new OrderCancelled($order));

        return $order;
    }
}
