<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use Modules\Platform\Notifications\ServerReleasesUpdated;
use Modules\Platform\Notifications\ServerScriptsUpdated;
use Modules\Platform\Notifications\WebsiteActivated;
use Modules\Platform\Notifications\WebsiteActivationFailed;
use Modules\Platform\Notifications\WebsiteCreated;
use Modules\Platform\Notifications\WebsiteDeleted;
use Modules\Platform\Notifications\WebsiteDeletionFailed;
use Modules\Platform\Notifications\WebsiteExpirationFailed;
use Modules\Platform\Notifications\WebsiteExpired;
use Modules\Platform\Notifications\WebsiteSuspended;
use Modules\Platform\Notifications\WebsiteSuspensionFailed;
use Modules\Platform\Notifications\WebsiteUnexpirationFailed;
use Modules\Platform\Notifications\WebsiteUnexpired;
use Modules\Platform\Notifications\WebsiteUpdated;
use Modules\Platform\Notifications\WebsiteUpdateFailed;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlatformNotificationPayloadMigrationTest extends TestCase
{
    #[DataProvider('notificationProvider')]
    public function test_platform_notifications_use_the_standard_payload_builder(object $notification, string $expectedType, string $expectedCategory, string $expectedPriority, bool $expectsFrontendLink): void
    {
        $payload = $notification->toDatabase(new \stdClass);

        self::assertSame('Platform', $payload['module']);
        self::assertSame($expectedType, $payload['type']);
        self::assertSame($expectedCategory, $payload['category']);
        self::assertSame($expectedPriority, $payload['priority']);
        self::assertArrayHasKey('title', $payload);
        self::assertArrayHasKey('icon', $payload);
        self::assertArrayHasKey('text', $payload);
        self::assertArrayHasKey('url_backend', $payload);
        self::assertArrayHasKey('url', $payload);
        self::assertNotEmpty($payload['links']);

        if ($expectsFrontendLink) {
            self::assertArrayHasKey('url_frontend', $payload);
            self::assertCount(2, $payload['links']);
        } else {
            self::assertArrayNotHasKey('url_frontend', $payload);
            self::assertCount(1, $payload['links']);
        }
    }

    /**
     * @return array<string, array{0: object, 1: string, 2: string, 3: string, 4: bool}>
     */
    public static function notificationProvider(): array
    {
        $website = (object) [
            'id' => 101,
            'domain' => 'example.com',
        ];

        $server = (object) [
            'id' => 202,
            'name' => 'Edge Server',
        ];

        return [
            'website_created' => [new WebsiteCreated($website), 'created', 'website', 'medium', true],
            'website_deletion_failed' => [new WebsiteDeletionFailed('example.com', 'Cleanup failed'), 'deletion_failed', 'website', 'high', false],
            'website_updated' => [new WebsiteUpdated($website), 'updated', 'website', 'low', true],
            'website_suspended' => [new WebsiteSuspended($website), 'suspended', 'website', 'medium', true],
            'website_unexpired' => [new WebsiteUnexpired($website), 'unexpired', 'website', 'medium', true],
            'website_expiration_failed' => [new WebsiteExpirationFailed($website, 'Cron timeout'), 'expiration_failed', 'website', 'high', true],
            'website_activation_failed' => [new WebsiteActivationFailed($website, 'Certificate missing'), 'activation_failed', 'website', 'high', true],
            'website_deleted' => [new WebsiteDeleted('example.com'), 'deleted', 'website', 'medium', false],
            'server_scripts_updated' => [new ServerScriptsUpdated($server, 5, 1), 'update', 'server', 'medium', false],
            'website_unexpiration_failed' => [new WebsiteUnexpirationFailed($website, 'Sync failed'), 'unexpiration_failed', 'website', 'high', true],
            'website_activated' => [new WebsiteActivated($website), 'activated', 'website', 'medium', true],
            'server_releases_updated' => [new ServerReleasesUpdated($server, '2.5.0', true), 'update', 'server', 'medium', false],
            'website_suspension_failed' => [new WebsiteSuspensionFailed($website, 'API failure'), 'suspension_failed', 'website', 'high', true],
            'website_expired' => [new WebsiteExpired($website), 'expired', 'website', 'high', true],
            'website_update_failed' => [new WebsiteUpdateFailed($website), 'update_failed', 'website', 'high', true],
        ];
    }
}
