<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class ServerProvisionAcmeFlowTest extends TestCase
{
    public function test_server_provision_registers_acme_step_and_uses_shared_setup_service(): void
    {
        $jobPath = base_path('modules/Platform/app/Jobs/ServerProvision.php');
        $jobContents = file_get_contents($jobPath);

        $this->assertNotFalse($jobContents, 'Failed to read modules/Platform/app/Jobs/ServerProvision.php');
        $this->assertStringContainsString('use Modules\Platform\Services\ServerAcmeSetupService;', $jobContents);
        $this->assertStringContainsString("'acme_setup' => 'Setting up ACME SSL'", $jobContents);
        $this->assertStringContainsString('$acmeSetupResult = $acmeSetupService->setup($server);', $jobContents);

        $serverSetupOffset = strpos($jobContents, "'server_setup'");
        $acmeSetupOffset = strpos($jobContents, "'acme_setup'");
        $releaseApiKeyOffset = strpos($jobContents, "'release_api_key'");

        $this->assertIsInt($serverSetupOffset);
        $this->assertIsInt($acmeSetupOffset);
        $this->assertIsInt($releaseApiKeyOffset);
        $this->assertGreaterThan($serverSetupOffset, $acmeSetupOffset);
        $this->assertGreaterThan($acmeSetupOffset, $releaseApiKeyOffset);
    }

    public function test_server_provisioning_ui_and_controller_expose_acme_as_a_real_step(): void
    {
        $controllerPath = base_path('modules/Platform/app/Http/Controllers/ServerController.php');
        $controllerContents = file_get_contents($controllerPath);
        $overviewPath = base_path('modules/Platform/resources/js/pages/platform/servers/components/server-show-overview.tsx');
        $overviewContents = file_get_contents($overviewPath);

        $this->assertNotFalse($controllerContents, 'Failed to read modules/Platform/app/Http/Controllers/ServerController.php');
        $this->assertNotFalse($overviewContents, 'Failed to read modules/Platform/resources/js/pages/platform/servers/components/server-show-overview.tsx');
        $this->assertStringContainsString('resolve(ServerAcmeSetupService::class)->setup($server);', $controllerContents);
        $this->assertStringContainsString("'acme_setup' => [", $controllerContents);
        $this->assertStringContainsString("'pg_optimize' => [", $controllerContents);
        $this->assertStringContainsString('Install HestiaCP, Astero scripts, and ACME SSL automation on this server?', $overviewContents);
        $this->assertStringContainsString('Run the ACME setup workflow again to install or repair SSL certificate automation on this server?', $overviewContents);
    }
}
