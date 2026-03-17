<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class ServerProvisionHestiaInstallGuardTest extends TestCase
{
    public function test_server_provision_prevents_installer_multiphp_mass_install_and_uses_setup_versions(): void
    {
        $jobPath = base_path('modules/Platform/app/Jobs/ServerProvision.php');
        $jobContents = file_get_contents($jobPath);
        $scriptPath = base_path('hestia/bin/a-provision-hestia');
        $scriptContents = file_get_contents($scriptPath);

        $this->assertNotFalse($jobContents, 'Failed to read modules/Platform/app/Jobs/ServerProvision.php');
        $this->assertNotFalse($scriptContents, 'Failed to read hestia/bin/a-provision-hestia');
        $this->assertStringContainsString("'--multiphp' => 'no'", $jobContents);
        $this->assertStringContainsString('buildHestiaInstallFlags', $jobContents);
        $this->assertStringContainsString('buildHestiaProvisionCommand', $jobContents);
        $this->assertStringContainsString('uploadHestiaProvisionHelperScript', $jobContents);
        $this->assertStringContainsString('self::HESTIA_PROVISION_HELPER_REMOTE_PATH', $jobContents);
        $this->assertStringContainsString('$this->configureReleaseApiKey($server, $sshService);', $jobContents);
        $this->assertStringContainsString('protected function executeSshCommand(', $jobContents);
        $this->assertStringContainsString('ServerProvision: ssh command completed', $jobContents);
        $this->assertStringContainsString('ServerProvision: step state updated', $jobContents);
        $this->assertStringContainsString('yes | bash /tmp/hst-install.sh', $scriptContents);
        $this->assertStringContainsString('apt-get remove -y ufw', $scriptContents);
        $this->assertStringContainsString('STATE:STARTED', $scriptContents);
    }

    public function test_server_provision_detects_existing_installer_session_or_process_before_restart(): void
    {
        $jobPath = base_path('modules/Platform/app/Jobs/ServerProvision.php');
        $jobContents = file_get_contents($jobPath);
        $scriptPath = base_path('hestia/bin/a-provision-hestia');
        $scriptContents = file_get_contents($scriptPath);

        $this->assertNotFalse($jobContents, 'Failed to read modules/Platform/app/Jobs/ServerProvision.php');
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
        $path = base_path('modules/Platform/app/Jobs/ServerProvision.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Jobs/ServerProvision.php');
        $this->assertStringContainsString('provisioning_control.stop_requested', $contents);
        $this->assertStringContainsString('STOP_REQUESTED_MARKER', $contents);
        $this->assertStringContainsString('abortIfProvisioningStopRequested', $contents);
    }

    public function test_server_provision_upload_scripts_step_uses_quiet_unzip_with_readable_progress_lines(): void
    {
        $path = base_path('modules/Platform/app/Jobs/ServerProvision.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Jobs/ServerProvision.php');
        $this->assertStringContainsString('unzip -oq', $contents);
        $this->assertStringContainsString('[upload] Preparing script deployment...', $contents);
        $this->assertStringContainsString('[upload] Extracting package...', $contents);
        $this->assertStringContainsString('[upload] Installing executable scripts...', $contents);
        $this->assertStringContainsString('[upload] Script deployment completed.', $contents);
    }
}
