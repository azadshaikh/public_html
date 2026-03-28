<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class ServerProvisionServiceInstallTest extends TestCase
{
    public function test_server_provision_uses_consolidated_deployment_script_for_setup(): void
    {
        $jobPath = base_path('modules/Platform/app/Jobs/ServerProvision.php');
        $jobContents = file_get_contents($jobPath);
        $sourceContents = $this->serverProvisionSourceContents();

        $this->assertNotFalse($jobContents, 'Failed to read modules/Platform/app/Jobs/ServerProvision.php');
        $this->assertStringContainsString('use InteractsWithServerProvisionInstall;', $jobContents);
        $this->assertStringContainsString('/usr/local/hestia/bin/a-provision-server', $sourceContents);
        $this->assertStringContainsString('/usr/local/hestia/bin/a-run-server-setup-screen', $sourceContents);
        $this->assertStringContainsString('SERVER_SETUP_SESSION', $jobContents);
        $this->assertStringContainsString('start_server_setup_screen', $sourceContents);
        $this->assertStringContainsString('wait_server_setup_screen', $sourceContents);
        $this->assertStringContainsString("'screen -r '.self::SERVER_SETUP_SESSION", $sourceContents);
    }

    public function test_deployment_script_installs_required_packages_without_redis_or_memcached(): void
    {
        $scriptPath = base_path('hestia/bin/a-provision-server');
        $contents = file_get_contents($scriptPath);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-provision-server');
        $this->assertStringContainsString('apt-get install -y -qq git supervisor screen ripgrep universal-ctags', $contents);
        $this->assertStringContainsString('apt-get install -y -qq jpegoptim optipng pngquant gifsicle libavif-bin', $contents);
        $this->assertStringContainsString('apt-get install -y -qq snapd', $contents);
        $this->assertStringContainsString('snap install svgo', $contents);
        $this->assertStringContainsString('systemctl enable supervisor', $contents);
        $this->assertStringContainsString('systemctl start supervisor', $contents);
        $this->assertStringContainsString('supervisorctl reread', $contents);
        $this->assertStringContainsString('supervisorctl update', $contents);
        $this->assertStringNotContainsString('redis', strtolower($contents));
        $this->assertStringNotContainsString('memcached', strtolower($contents));
    }

    private function serverProvisionSourceContents(): string
    {
        return collect([
            'modules/Platform/app/Jobs/ServerProvision.php',
            'modules/Platform/app/Jobs/Concerns/InteractsWithServerProvisionInstall.php',
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
