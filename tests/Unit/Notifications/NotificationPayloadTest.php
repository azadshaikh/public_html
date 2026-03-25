<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Notifications\Support\NotificationPayload;
use PHPUnit\Framework\TestCase;

class NotificationPayloadTest extends TestCase
{
    public function test_it_builds_a_message_first_payload_with_links(): void
    {
        $payload = NotificationPayload::make('Queue alert', '<p>Action required.</p>')
            ->module('QueueMonitor')
            ->type('queue_failure_alert')
            ->category(NotificationCategory::System)
            ->priority(NotificationPriority::High)
            ->icon('ri-error-warning-line')
            ->backendLink('/admin/queue-monitor?status=failed', 'Open queue monitor')
            ->frontendLink('https://status.example.com', 'Open status page')
            ->links([
                [
                    'label' => 'Read playbook',
                    'href' => 'https://docs.example.com/playbook',
                ],
            ])
            ->extra([
                'module_slug' => 'queue-monitor',
            ])
            ->toArray();

        self::assertSame('Queue alert', $payload['title']);
        self::assertSame('<p>Action required.</p>', $payload['text']);
        self::assertSame('QueueMonitor', $payload['module']);
        self::assertSame('queue_failure_alert', $payload['type']);
        self::assertSame('system', $payload['category']);
        self::assertSame('high', $payload['priority']);
        self::assertSame('ri-error-warning-line', $payload['icon']);
        self::assertSame('/admin/queue-monitor?status=failed', $payload['url']);
        self::assertSame('/admin/queue-monitor?status=failed', $payload['url_backend']);
        self::assertSame('https://status.example.com', $payload['url_frontend']);
        self::assertSame('queue-monitor', $payload['module_slug']);
        self::assertCount(3, $payload['links']);
        self::assertSame('Open queue monitor', $payload['links'][0]['label']);
        self::assertFalse($payload['links'][0]['external']);
        self::assertTrue($payload['links'][1]['external']);
    }

    public function test_it_skips_blank_links(): void
    {
        $payload = NotificationPayload::make('Test', 'Body')
            ->backendLink('', 'Ignored link')
            ->frontendLink(null, 'Ignored external link')
            ->link('', '')
            ->toArray();

        self::assertArrayNotHasKey('url', $payload);
        self::assertArrayNotHasKey('url_backend', $payload);
        self::assertArrayNotHasKey('url_frontend', $payload);
        self::assertArrayNotHasKey('links', $payload);
    }
}
