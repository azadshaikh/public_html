<?php

namespace Modules\Platform\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ServerReleasesUpdatedNotificationTest extends TestCase
{
    public function test_server_releases_updated_notification_contains_expected_payload_fields(): void
    {
        $projectRoot = dirname(__DIR__, 4);
        $contents = file_get_contents($projectRoot.'/modules/Platform/app/Notifications/ServerReleasesUpdated.php');

        $this->assertNotFalse($contents, 'Failed to read ServerReleasesUpdated notification');
        $this->assertStringContainsString("'title' => 'Server Releases Updated!'", $contents);
        $this->assertStringContainsString("'category' => 'server'", $contents);
        $this->assertStringContainsString("'icon' => 'ri-download-cloud-line'", $contents);
        $this->assertStringContainsString('server info sync warning', $contents);
        $this->assertStringContainsString("route('platform.servers.show', \$server->id)", $contents);
    }

    public function test_server_update_releases_job_and_controller_include_notification_wiring(): void
    {
        $projectRoot = dirname(__DIR__, 4);
        $jobContents = file_get_contents($projectRoot.'/modules/Platform/app/Jobs/ServerUpdateReleases.php');
        $controllerContents = file_get_contents($projectRoot.'/modules/Platform/app/Http/Controllers/ServerController.php');

        $this->assertNotFalse($jobContents, 'Failed to read ServerUpdateReleases job');
        $this->assertNotFalse($controllerContents, 'Failed to read ServerController');
        $this->assertStringContainsString('public ?int $initiatedById;', $jobContents);
        $this->assertStringContainsString('notify(new NotificationServerReleasesUpdated', $jobContents);
        $this->assertStringContainsString('dispatch($server, auth()->id())', $controllerContents);
    }
}
