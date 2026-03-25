<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers {
    function setting(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'billing_invoice_prefix' => 'INV',
            'billing_invoice_serial_number' => 1,
            'billing_invoice_digit_length' => 5,
            'billing_invoice_format' => 'date_sequence',
            default => $default,
        };
    }
}

namespace Modules\Billing\Tests\Feature {

    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;
    use Inertia\Response as InertiaResponse;
    use Modules\Billing\Http\Controllers\SettingsController;
    use Modules\Billing\Http\Controllers\StripeWebhookController;
    use ReflectionClass;
    use Tests\TestCase;

    class BillingSecurityContractTest extends TestCase
    {
        public function test_billing_settings_page_masks_stripe_secrets_instead_of_echoing_raw_values(): void
        {
            $stripeKey = 'pk_test_1234567890';
            $stripeSecret = 'sk_test_1234567890';
            $webhookSecret = 'whsec_test_1234567890';

            config([
                'cashier.key' => $stripeKey,
                'cashier.secret' => $stripeSecret,
                'cashier.webhook.secret' => $webhookSecret,
            ]);

            request()->merge(['section' => 'stripe']);

            Auth::shouldReceive('user')
                ->andReturn(new class
                {
                    public function can(string $ability): bool
                    {
                        return true;
                    }
                });

            $response = app(SettingsController::class)->settings();
            $props = $this->extractInertiaProps($response);

            $this->assertSame('stripe', $props['section']);
            $this->assertSame('••••7890', $props['stripeSettings']['stripe_key']);
            $this->assertSame('••••7890', $props['stripeSettings']['stripe_secret']);
            $this->assertSame('••••7890', $props['stripeSettings']['stripe_webhook_secret']);
            $this->assertNotSame($stripeKey, $props['stripeSettings']['stripe_key']);
            $this->assertNotSame($stripeSecret, $props['stripeSettings']['stripe_secret']);
            $this->assertNotSame($webhookSecret, $props['stripeSettings']['stripe_webhook_secret']);
        }

        public function test_stripe_webhook_rejects_invalid_signatures(): void
        {
            config(['cashier.webhook.secret' => 'whsec_test_1234567890']);

            $request = Request::create(
                '/api/billing/v1/webhooks/stripe',
                'POST',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_STRIPE_SIGNATURE' => 't=1,v1=invalid',
                ],
                json_encode([
                    'id' => 'evt_test',
                    'object' => 'event',
                ], JSON_THROW_ON_ERROR),
            );

            $response = app(StripeWebhookController::class)->handle($request);

            $this->assertSame(400, $response->getStatusCode());
            $this->assertSame('Webhook Error: Invalid signature.', $response->getContent());
        }

        private function extractInertiaProps(InertiaResponse $response): array
        {
            $reflection = new ReflectionClass($response);
            $property = $reflection->getProperty('props');
            $property->setAccessible(true);

            /** @var array<string, mixed> $props */
            $props = $property->getValue($response);

            return $props;
        }
    }
}
