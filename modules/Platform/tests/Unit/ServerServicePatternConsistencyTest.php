<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class ServerServicePatternConsistencyTest extends TestCase
{
    public function test_server_service_uses_refactored_sync_concerns(): void
    {
        $servicePath = base_path('modules/Platform/app/Services/ServerService.php');
        $serviceContents = file_get_contents($servicePath);

        $this->assertNotFalse($serviceContents, 'Failed to read modules/Platform/app/Services/ServerService.php');
        $this->assertStringContainsString('use InteractsWithServerInfoSync;', $serviceContents);
        $this->assertStringContainsString('use InteractsWithServerReleaseSync;', $serviceContents);
        $this->assertStringNotContainsString('public function syncServerInfo(', $serviceContents);
        $this->assertStringNotContainsString('public function updateLocalReleases(', $serviceContents);
    }

    public function test_server_service_concerns_hold_release_and_info_sync_behaviour(): void
    {
        $infoSyncPath = base_path('modules/Platform/app/Services/Concerns/InteractsWithServerInfoSync.php');
        $infoSyncContents = file_get_contents($infoSyncPath);

        $this->assertNotFalse($infoSyncContents, 'Failed to read InteractsWithServerInfoSync concern.');
        $this->assertStringContainsString('public function syncServerInfo(', $infoSyncContents);
        $this->assertStringContainsString('protected function resolveDomainCount(', $infoSyncContents);
        $this->assertStringContainsString('protected function syncServerInfoFallback(', $infoSyncContents);

        $releaseSyncPath = base_path('modules/Platform/app/Services/Concerns/InteractsWithServerReleaseSync.php');
        $releaseSyncContents = file_get_contents($releaseSyncPath);

        $this->assertNotFalse($releaseSyncContents, 'Failed to read InteractsWithServerReleaseSync concern.');
        $this->assertStringContainsString('public function updateLocalReleases(', $releaseSyncContents);
        $this->assertStringContainsString('protected function finalizeReleaseSyncResponse(', $releaseSyncContents);
    }
}
