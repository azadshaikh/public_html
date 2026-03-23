<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Models\Coupon;
use Modules\Billing\Services\CouponService;
use Modules\Customers\Models\Customer;
use Modules\Orders\Models\Order;
use Modules\Orders\Services\OrderService;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Handles incoming Stripe webhook events.
 *
 * Stripe endpoint: POST /api/billing/v1/webhooks/stripe
 *
 * Required settings (configured in Billing → Settings → Stripe):
 *   STRIPE_KEY             = pk_live_...
 *   STRIPE_SECRET          = sk_live_...
 *   STRIPE_WEBHOOK_SECRET  = whsec_...  (from Stripe Dashboard → Webhooks)
 *
 * Subscribed events (register these in Stripe Dashboard):
 *   - checkout.session.completed
 *   - invoice.payment_succeeded
 *   - customer.subscription.deleted
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');
        $webhookSecret = config('cashier.webhook.secret', '');

        // Reject when webhook secret is not configured — unsigned requests must not be trusted.
        if (blank($webhookSecret)) {
            Log::warning('Stripe webhook: webhook secret not configured, rejecting request', ['ip' => $request->ip()]);

            return response('Webhook Error: Webhook secret not configured.', 500);
        }

        // Verify signature using the official Stripe SDK (handles timestamp tolerance, multiple signatures, etc.)
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: invalid signature', ['ip' => $request->ip(), 'message' => $e->getMessage()]);

            return response('Webhook Error: Invalid signature.', 400);
        } catch (UnexpectedValueException $e) {
            Log::warning('Stripe webhook: invalid payload', ['ip' => $request->ip(), 'message' => $e->getMessage()]);

            return response('Webhook Error: Invalid payload.', 400);
        }

        /** @var string $eventType */
        $eventType = $event->type;
        $eventObject = $event->data->object->toArray();

        Log::info('Stripe webhook received', ['type' => $eventType, 'id' => $request->input('id')]);

        return match ($eventType) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($eventObject),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($eventObject),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($eventObject),
            default => response('Event received (unhandled).', 200),
        };
    }

    /**
     * checkout.session.completed — Stripe Checkout hosted page payment confirmed.
     *
     * The primary fulfillment path is OnboardingController::stripeSuccess() (synchronous,
     * triggered when the user is redirected back from Stripe). This webhook is an async
     * safety net for edge cases where the user closed the browser before the redirect
     * completed, yet Stripe has already captured the payment.
     *
     * @param  array<string, mixed>  $session
     */
    private function handleCheckoutSessionCompleted(array $session): Response
    {
        $sessionId = $session['id'] ?? null;
        $customerId = $session['metadata']['customer_id'] ?? null;
        $paymentStatus = $session['payment_status'] ?? null;

        Log::info('Stripe: checkout.session.completed', [
            'session_id' => $sessionId,
            'customer_id' => $customerId,
            'payment_status' => $paymentStatus,
            'amount_total' => $session['amount_total'] ?? null,
        ]);

        if (! $customerId) {
            Log::warning('Stripe Checkout Session: missing customer_id in metadata', ['session_id' => $sessionId]);

            return response('OK', 200);
        }

        $customer = Customer::query()->find((int) $customerId);

        if (! $customer) {
            Log::warning('Stripe Checkout Session: customer not found', [
                'session_id' => $sessionId,
                'customer_id' => $customerId,
            ]);

            return response('OK', 200);
        }

        $onboarding = $customer->getMetadata('onboarding', []);

        // Idempotency: stripeSuccess() already fulfilled the order.
        if (! empty($onboarding['payment_completed_at'])) {
            Log::info('Stripe Checkout Session: already fulfilled via stripeSuccess()', [
                'session_id' => $sessionId,
                'customer_id' => $customerId,
            ]);

            return response('OK', 200);
        }

        // --- Fallback fulfillment: mark order paid + redeem coupon ---
        // stripeSuccess() did not run (user closed browser after payment).
        if ($paymentStatus === 'paid') {
            $this->fulfillOrderFromWebhook($session, $customer);
        }

        return response('OK', 200);
    }

    /**
     * Webhook fallback: mark the pending Order as paid and redeem coupon.
     *
     * Provisioning (Platform API + website creation) is NOT done here because
     * it requires the Agency module's PlatformApiClient (cross-module coupling).
     * Instead we mark payment_completed_at so the user can resume provisioning
     * on their next visit, and log a critical alert for manual follow-up.
     *
     * @param  array<string, mixed>  $session
     */
    private function fulfillOrderFromWebhook(array $session, Customer $customer): void
    {
        $sessionId = $session['id'] ?? null;
        $orderId = $session['metadata']['order_id'] ?? null;
        $paymentIntent = $session['payment_intent'] ?? 'webhook_fallback';

        // 1. Mark order as paid (if Orders module is enabled and order exists).
        $order = null;
        if ($orderId && module_enabled('orders') && class_exists(Order::class)) {
            $order = Order::query()->find((int) $orderId);

            if ($order && $order->status === Order::STATUS_PROCESSING) {
                /** @var OrderService $orderService */
                $orderService = resolve(OrderService::class);
                $orderService->markPaid($order, (string) $paymentIntent);

                Log::info('Stripe webhook fallback: order marked paid', [
                    'order_id' => $order->id,
                    'session_id' => $sessionId,
                ]);
            }
        }

        // 2. Redeem the coupon (if applicable).
        $couponCode = $session['metadata']['coupon_code'] ?? null;
        if ($couponCode && $order && module_enabled('billing') && class_exists(CouponService::class)) {
            $coupon = Coupon::query()
                ->where('code', $couponCode)
                ->where('is_active', true)
                ->first();

            if ($coupon) {
                /** @var CouponService $couponService */
                $couponService = resolve(CouponService::class);
                $couponService->redeem($coupon, $customer->id, $order->id, (float) ($order->discount_amount ?? 0));

                Log::info('Stripe webhook fallback: coupon redeemed', [
                    'coupon_code' => $couponCode,
                    'order_id' => $order->id,
                ]);
            }
        }

        // 3. Update customer metadata so the user can resume provisioning.
        $metadata = $customer->metadata ?? [];
        $metadata['onboarding'] = array_merge($metadata['onboarding'] ?? [], [
            'payment_completed_at' => now()->toDateTimeString(),
            'last_paid_stripe_session_id' => $sessionId,
            'webhook_fulfilled' => true,
        ]);
        $customer->update(['metadata' => $metadata]);

        // 4. Alert for manual provisioning since we can't call Platform API from Billing module.
        Log::critical('Stripe webhook fallback: payment fulfilled but website NOT yet provisioned — manual intervention required', [
            'session_id' => $sessionId,
            'customer_id' => $customer->id,
            'order_id' => $order?->id,
            'plan_id' => $session['metadata']['plan_id'] ?? null,
            'website_domain' => $session['metadata']['website_domain'] ?? null,
        ]);
    }

    /**
     * invoice.payment_succeeded — renew the subscription's current period.
     *
     * TODO: Update the local Subscription's current_period_end.
     *
     * @param  array<string, mixed>  $invoice
     */
    private function handleInvoicePaymentSucceeded(array $invoice): Response
    {
        Log::info('Stripe: invoice.payment_succeeded', ['invoice_id' => $invoice['id'] ?? null]);

        // TODO: renew subscription period in the Subscriptions module

        return response('OK', 200);
    }

    /**
     * customer.subscription.deleted — Stripe subscription ended.
     * Not active for PaymentIntent-based plans, but handled for future Stripe Subscription support.
     *
     * @param  array<string, mixed>  $subscription
     */
    private function handleSubscriptionDeleted(array $subscription): Response
    {
        Log::info('Stripe: customer.subscription.deleted', [
            'stripe_subscription_id' => $subscription['id'] ?? null,
        ]);

        // TODO: When Stripe Subscriptions are used, cancel the matching local Subscription
        // record via SubscriptionService::cancelCustomerSubscription().

        return response('OK', 200);
    }
}
