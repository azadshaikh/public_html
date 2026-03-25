<?php

declare(strict_types=1);

namespace Modules\Platform\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Modules\Platform\Jobs\SendAgencyWebhook;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Website;
use ReflectionMethod;
use Tests\TestCase;

class SendAgencyWebhookTest extends TestCase
{
    public function test_generate_signature_uses_the_agency_secret_key(): void
    {
        $agency = new Agency;
        $agency->forceFill([
            'secret_key' => encrypt('agency-secret-123'),
        ]);

        $job = new SendAgencyWebhook(1, 'website.created', [
            'site_id' => 'WS00001',
        ]);

        $method = new ReflectionMethod($job, 'generateSignature');
        $method->setAccessible(true);

        $jsonBody = json_encode([
            'event' => 'website.created',
            'site_id' => 'WS00001',
            'timestamp' => '2026-03-25T00:00:00+00:00',
        ], JSON_THROW_ON_ERROR);

        $this->assertSame(
            hash_hmac('sha256', $jsonBody, 'agency-secret-123'),
            $method->invoke($job, $jsonBody, $agency)
        );
    }

    public function test_dispatch_for_website_skips_when_the_website_has_no_agency(): void
    {
        Queue::fake();

        $website = new Website;
        $website->forceFill([
            'agency_id' => null,
        ]);

        SendAgencyWebhook::dispatchForWebsite($website, 'website.created');

        Queue::assertNothingPushed();
    }

    public function test_retry_policy_uses_three_attempts_and_staged_backoff(): void
    {
        $job = new SendAgencyWebhook(1, 'website.failed', [
            'site_id' => 'WS00002',
        ]);

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff());
    }
}
