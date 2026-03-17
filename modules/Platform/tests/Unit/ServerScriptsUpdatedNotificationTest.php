<?php

namespace Modules\Platform\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ServerScriptsUpdatedNotificationTest extends TestCase
{
    public function test_server_scripts_updated_notification_contains_expected_payload_fields(): void
    {
        $projectRoot = dirname(__DIR__, 4);
        $contents = file_get_contents($projectRoot.'/modules/Platform/app/Notifications/ServerScriptsUpdated.php');

        $this->assertNotFalse($contents, 'Failed to read ServerScriptsUpdated notification');
        $this->assertStringContainsString("'title' => 'Server Scripts Updated!'", $contents);
        $this->assertStringContainsString("'category' => 'server'", $contents);
        $this->assertStringContainsString("'icon' => 'ri-tools-line'", $contents);
        $this->assertStringContainsString("route('platform.servers.show', \$server->id)", $contents);
    }

    public function test_server_update_scripts_job_and_controller_include_notification_wiring(): void
    {
        $projectRoot = dirname(__DIR__, 4);
        $jobContents = file_get_contents($projectRoot.'/modules/Platform/app/Jobs/ServerUpdateScripts.php');
        $controllerContents = file_get_contents($projectRoot.'/modules/Platform/app/Http/Controllers/ServerController.php');

        $this->assertNotFalse($jobContents, 'Failed to read ServerUpdateScripts job');
        $this->assertNotFalse($controllerContents, 'Failed to read ServerController');
        $this->assertStringContainsString('public ?int $initiatedById;', $jobContents);
        $this->assertStringContainsString('notify(new NotificationServerScriptsUpdated', $jobContents);
        $this->assertStringContainsString('dispatch($server, auth()->id())', $controllerContents);
    }
}
