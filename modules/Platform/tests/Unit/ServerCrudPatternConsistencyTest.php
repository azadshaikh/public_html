<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class ServerCrudPatternConsistencyTest extends TestCase
{
    public function test_server_form_uses_monitor_field_name_consistently(): void
    {
        $path = base_path('modules/Platform/resources/views/servers/form.blade.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/views/servers/form.blade.php');
        $this->assertStringContainsString('name="monitor"', $contents);
        $this->assertStringContainsString("old('monitor', \$server->monitor ?? false)", $contents);
    }

    public function test_server_show_restore_action_uses_patch_method(): void
    {
        $path = base_path('modules/Platform/resources/views/servers/show.blade.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/views/servers/show.blade.php');
        $this->assertMatchesRegularExpression(
            '/data-title="Restore Server"[\\s\\S]*data-method="PATCH"[\\s\\S]*platform\\.servers\\.restore/',
            $contents
        );
    }

    public function test_server_routes_allow_failed_status_tab_without_provisioning_tab(): void
    {
        $path = base_path('modules/Platform/routes/web.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/routes/web.php');
        $this->assertStringContainsString('^(all|active|failed|inactive|maintenance|trash)$', $contents);
        $this->assertStringNotContainsString('^(all|active|provisioning|failed|inactive|maintenance|trash)$', $contents);
    }

    public function test_server_definition_has_failed_status_tab_without_provisioning_tab(): void
    {
        $path = base_path('modules/Platform/app/Definitions/ServerDefinition.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Definitions/ServerDefinition.php');
        $this->assertStringContainsString("StatusTab::make('failed')", $contents);
        $this->assertStringNotContainsString("StatusTab::make('provisioning')", $contents);
    }

    public function test_server_service_statistics_and_navigation_fold_provisioning_into_failed(): void
    {
        $path = base_path('modules/Platform/app/Services/ServerService.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Services/ServerService.php');
        $this->assertStringContainsString("'failed' => (\$statusCounts['failed'] ?? 0) + (\$statusCounts['provisioning'] ?? 0)", $contents);
        $this->assertStringContainsString("'key' => 'failed'", $contents);
        $this->assertStringNotContainsString("'key' => 'provisioning'", $contents);
    }

    public function test_server_service_failed_status_filter_includes_provisioning_records(): void
    {
        $path = base_path('modules/Platform/app/Services/ServerService.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Services/ServerService.php');
        $this->assertStringContainsString("\$status === 'failed'", $contents);
        $this->assertStringContainsString("\$query->whereIn('status', ['failed', 'provisioning']);", $contents);
    }

    public function test_server_query_builder_uses_monitor_column_and_provider_relationship_filtering(): void
    {
        $path = base_path('modules/Platform/app/Models/QueryBuilders/ServerQueryBuilder.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Models/QueryBuilders/ServerQueryBuilder.php');
        $this->assertStringContainsString("where('monitor', \$monitoring)", $contents);
        $this->assertStringContainsString("whereHas('providers'", $contents);
        $this->assertStringNotContainsString("where('monitoring', \$monitoring)", $contents);
        $this->assertStringNotContainsString("where('provider_id', \$providerIds)", $contents);
    }

    public function test_server_agency_association_controllers_use_form_requests(): void
    {
        $serverControllerPath = base_path('modules/Platform/app/Http/Controllers/ServerAgencyController.php');
        $serverControllerContents = file_get_contents($serverControllerPath);

        $this->assertNotFalse($serverControllerContents, 'Failed to read modules/Platform/app/Http/Controllers/ServerAgencyController.php');
        $this->assertStringContainsString('use Modules\\Platform\\Http\\Requests\\ServerAttachAgenciesRequest;', $serverControllerContents);
        $this->assertStringContainsString('public function attachAgencies(ServerAttachAgenciesRequest $request, $id): JsonResponse', $serverControllerContents);

        $agencyControllerPath = base_path('modules/Platform/app/Http/Controllers/AgencyServerController.php');
        $agencyControllerContents = file_get_contents($agencyControllerPath);

        $this->assertNotFalse($agencyControllerContents, 'Failed to read modules/Platform/app/Http/Controllers/AgencyServerController.php');
        $this->assertStringContainsString('use Modules\\Platform\\Http\\Requests\\AgencyAttachServersRequest;', $agencyControllerContents);
        $this->assertStringContainsString('public function attachServers(AgencyAttachServersRequest $request, $id): JsonResponse', $agencyControllerContents);
    }

    public function test_server_edit_form_supports_ssh_keypair_generation_and_authorize_command(): void
    {
        $path = base_path('modules/Platform/resources/views/servers/form.blade.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/views/servers/form.blade.php');
        $this->assertStringContainsString('id="generate-ssh-key-pair"', $contents);
        $this->assertStringContainsString('id="ssh_public_key" name="ssh_public_key"', $contents);
        $this->assertStringContainsString('id="ssh_authorize_command"', $contents);
        $this->assertStringContainsString("route('platform.servers.generate-ssh-key')", $contents);
    }

    public function test_server_controller_allows_generate_ssh_key_for_edit_permission(): void
    {
        $path = base_path('modules/Platform/app/Http/Controllers/ServerController.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Http/Controllers/ServerController.php');
        $this->assertStringContainsString("new Middleware('permission:edit_servers', only: ['edit', 'update', 'generateSSHKey'", $contents);
    }

    public function test_server_secret_reveal_is_protected_by_edit_permission_and_post_route(): void
    {
        $controllerPath = base_path('modules/Platform/app/Http/Controllers/ServerController.php');
        $controllerContents = file_get_contents($controllerPath);
        $this->assertNotFalse($controllerContents, 'Failed to read modules/Platform/app/Http/Controllers/ServerController.php');
        $this->assertStringContainsString("new Middleware('permission:view_servers', only: ['index', 'show', 'data', 'websites', 'optimizationTool'])", $controllerContents);
        $this->assertStringContainsString("'reprovisionServer', 'stopProvisioning', 'revealSecret', 'revealSshKeyPair', 'revealAccessKeySecret'])", $controllerContents);

        $routesPath = base_path('modules/Platform/routes/web.php');
        $routesContents = file_get_contents($routesPath);
        $this->assertNotFalse($routesContents, 'Failed to read modules/Platform/routes/web.php');
        $this->assertStringContainsString("Route::post('/{server}/stop-provisioning'", $routesContents);
        $this->assertStringContainsString("Route::post('/{server}/secrets/{secret}/reveal'", $routesContents);
        $this->assertStringContainsString("->middleware('throttle:20,1')", $routesContents);
    }

    public function test_server_provisioning_step_definitions_include_release_api_key_step(): void
    {
        $controllerPath = base_path('modules/Platform/app/Http/Controllers/ServerController.php');
        $controllerContents = file_get_contents($controllerPath);
        $this->assertNotFalse($controllerContents, 'Failed to read modules/Platform/app/Http/Controllers/ServerController.php');
        $this->assertStringContainsString("'release_api_key' => [", $controllerContents);

        $viewPath = base_path('modules/Platform/resources/views/servers/show.blade.php');
        $viewContents = file_get_contents($viewPath);
        $this->assertNotFalse($viewContents, 'Failed to read modules/Platform/resources/views/servers/show.blade.php');
        $this->assertStringContainsString("'release_api_key', 'access_key'", $viewContents);
    }

    public function test_server_provisioning_supports_multiple_provisioner_ips_for_api_allowlist(): void
    {
        $configPath = base_path('modules/Platform/config/config.php');
        $configContents = file_get_contents($configPath);
        $this->assertNotFalse($configContents, 'Failed to read modules/Platform/config/config.php');
        $this->assertStringContainsString("'provisioner_ips' =>", $configContents);
        $this->assertStringContainsString("config('astero.provisioner_ips', [])", $configContents);

        $jobPath = base_path('modules/Platform/app/Jobs/ServerProvision.php');
        $jobContents = file_get_contents($jobPath);
        $this->assertNotFalse($jobContents, 'Failed to read modules/Platform/app/Jobs/ServerProvision.php');
        $this->assertStringContainsString("config('platform.provisioner_ips', [])", $jobContents);
        $this->assertStringContainsString('return implode(PHP_EOL, $provisionerIps);', $jobContents);
        $this->assertStringContainsString('Set PROVISIONER_IPS.', $jobContents);
    }
}
