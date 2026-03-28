<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class ServerProvisionPatternConsistencyTest extends TestCase
{
    public function test_server_provision_uses_refactored_job_concerns(): void
    {
        $jobContents = $this->readRequiredFile('modules/Platform/app/Jobs/ServerProvision.php');

        $this->assertStringContainsString('use InteractsWithServerProvisionInstall;', $jobContents);
        $this->assertStringContainsString('use InteractsWithServerProvisionReleaseSync;', $jobContents);
        $this->assertStringContainsString('use InteractsWithServerProvisionState;', $jobContents);
        $this->assertStringNotContainsString('protected function installHestia(', $jobContents);
        $this->assertStringNotContainsString('protected function updateReleases(', $jobContents);
        $this->assertStringNotContainsString('protected function initSteps(', $jobContents);
    }

    public function test_server_provision_job_concerns_hold_install_release_and_state_behaviour(): void
    {
        $installContents = $this->readRequiredFile('modules/Platform/app/Jobs/Concerns/InteractsWithServerProvisionInstall.php');
        $releaseContents = $this->readRequiredFile('modules/Platform/app/Jobs/Concerns/InteractsWithServerProvisionReleaseSync.php');
        $stateContents = $this->readRequiredFile('modules/Platform/app/Jobs/Concerns/InteractsWithServerProvisionState.php');

        $this->assertStringContainsString('protected function installHestia(', $installContents);
        $this->assertStringContainsString('protected function runServerSetup(', $installContents);
        $this->assertStringContainsString('protected function uploadScripts(', $installContents);
        $this->assertStringContainsString('protected function executeSshCommand(', $installContents);

        $this->assertStringContainsString('protected function configureReleaseApiKey(', $releaseContents);
        $this->assertStringContainsString('protected function updateReleases(', $releaseContents);
        $this->assertStringContainsString('protected function applyPgOptimizations(', $releaseContents);

        $this->assertStringContainsString('protected function initSteps(', $stateContents);
        $this->assertStringContainsString('protected function shouldReleaseForRetry(', $stateContents);
        $this->assertStringContainsString('protected function resolveApiAllowedIp(', $stateContents);
    }

    private function readRequiredFile(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($contents, 'Failed to read '.$relativePath);

        return $contents;
    }
}
