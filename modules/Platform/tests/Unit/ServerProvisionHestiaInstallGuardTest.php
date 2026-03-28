<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class ServerProvisionHestiaInstallGuardTest extends TestCase
{
    public function test_server_provision_prevents_installer_multiphp_mass_install_and_uses_setup_versions(): void
    {
        $jobPath = base_path('modules/Platform/app/Jobs/ServerProvision.php');
        $jobContents = file_get_contents($jobPath);
        $sourceContents = $this->serverProvisionSourceContents();
        $scriptPath = base_path('hestia/bin/a-provision-hestia');
        $scriptContents = file_get_contents($scriptPath);

        $this->assertNotFalse($jobContents, 'Failed to read modules/Platform/app/Jobs/ServerProvision.php');
        $this->assertNotFalse($scriptContents, 'Failed to read hestia/bin/a-provision-hestia');
        $this->assertStringContainsString('use InteractsWithServerProvisionInstall;', $jobContents);
        $this->assertStringContainsString('use InteractsWithServerProvisionReleaseSync;', $jobContents);
        $this->assertStringContainsString('use InteractsWithServerProvisionState;', $jobContents);
        $this->assertStringContainsString("'--multiphp' => 'no'", $sourceContents);
        $this->assertStringContainsString('buildHestiaInstallFlags', $sourceContents);
        $this->assertStringContainsString('buildHestiaProvisionCommand', $sourceContents);
        $this->assertStringContainsString('uploadHestiaProvisionHelperScript', $sourceContents);
        $this->assertStringContainsString('self::HESTIA_PROVISION_HELPER_REMOTE_PATH', $sourceContents);
        $this->assertStringContainsString('$this->configureReleaseApiKey($server, $sshService);', $sourceContents);
        $this->assertStringContainsString('protected function executeSshCommand(', $sourceContents);
        $this->assertStringContainsString('ServerProvision: ssh command completed', $sourceContents);
        $this->assertStringContainsString('ServerProvision: step state updated', $sourceContents);
        $this->assertStringContainsString('yes | bash /tmp/hst-install.sh', $scriptContents);
        $this->assertStringContainsString('apt-get remove -y ufw', $scriptContents);
        $this->assertStringContainsString('STATE:STARTED', $scriptContents);
    }

    public function test_server_provision_detects_existing_installer_session_or_process_before_restart(): void
    {
        $jobContents = $this->serverProvisionSourceContents();
        $scriptPath = base_path('hestia/bin/a-provision-hestia');
        $scriptContents = file_get_contents($scriptPath);

        $this->assertNotFalse($scriptContents, 'Failed to read hestia/bin/a-provision-hestia');
        $this->assertStringContainsString('isHestiaInstallerActive', $jobContents);
        $this->assertStringContainsString('isInstallSessionRunning', $jobContents);
        $this->assertStringContainsString('isInstallProcessRunning', $jobContents);
        $this->assertStringContainsString('$alreadyInstalledNow && ! $installerActive', $jobContents);
        $this->assertStringContainsString('installer is still active; waiting for completion', $jobContents);
        $this->assertStringContainsString('STATE:INSTALLED', $scriptContents);
        $this->assertStringContainsString('STATE:RUNNING', $scriptContents);
        $this->assertStringContainsString("pgrep -fa '/tmp/hst-install", $scriptContents);
    }

    public function test_server_provision_supports_manual_stop_requests_between_steps(): void
    {
        $contents = $this->serverProvisionSourceContents();
        $this->assertStringContainsString('provisioning_control.stop_requested', $contents);
        $this->assertStringContainsString('STOP_REQUESTED_MARKER', $contents);
        $this->assertStringContainsString('abortIfProvisioningStopRequested', $contents);
    }

    public function test_server_provision_upload_scripts_step_uses_quiet_unzip_with_readable_progress_lines(): void
    {
        $contents = $this->serverProvisionSourceContents();
        $this->assertStringContainsString('unzip -oq', $contents);
        $this->assertStringContainsString('[upload] Preparing script deployment...', $contents);
        $this->assertStringContainsString('[upload] Extracting package...', $contents);
        $this->assertStringContainsString('[upload] Installing executable scripts...', $contents);
        $this->assertStringContainsString('[upload] Script deployment completed.', $contents);
    }

    private function serverProvisionSourceContents(): string
    {
        return collect([
            'modules/Platform/app/Jobs/ServerProvision.php',
            'modules/Platform/app/Jobs/Concerns/InteractsWithServerProvisionInstall.php',
            'modules/Platform/app/Jobs/Concerns/InteractsWithServerProvisionReleaseSync.php',
            'modules/Platform/app/Jobs/Concerns/InteractsWithServerProvisionState.php',
        ])->map(fn (string $relativePath): string => $this->readRequiredFile($relativePath))
            ->implode("\n");
    }

    private function readRequiredFile(string $relativePath): string
    {
        $contents = file_get_contents(base_path($relativePath));

        $this->assertNotFalse($contents, 'Failed to read '.$relativePath);

        return $contents;
    }
}
