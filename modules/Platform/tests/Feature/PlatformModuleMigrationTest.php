<?php

declare(strict_types=1);

namespace Modules\Platform\Tests\Feature;

use Tests\TestCase;

class PlatformModuleMigrationTest extends TestCase
{
    public function test_platform_module_manifest_uses_custom_module_runtime_keys(): void
    {
        $contents = file_get_contents(base_path('modules/Platform/module.json'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('"slug": "platform"', $contents);
        $this->assertStringContainsString('"namespace": "Modules\\\\Platform\\\\"', $contents);
        $this->assertStringContainsString('"provider": "Modules\\\\Platform\\\\Providers\\\\PlatformServiceProvider"', $contents);
    }

    public function test_platform_service_provider_uses_module_service_provider_instead_of_nwidart(): void
    {
        $contents = file_get_contents(base_path('modules/Platform/app/Providers/PlatformServiceProvider.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('use App\\Modules\\Support\\ModuleServiceProvider;', $contents);
        $this->assertStringContainsString('class PlatformServiceProvider extends ModuleServiceProvider', $contents);
        $this->assertStringContainsString("return 'platform';", $contents);
        $this->assertStringContainsString('registerAllConfigFiles();', $contents);
        $this->assertStringNotContainsString('Nwidart\\Modules\\Traits\\PathNamespace', $contents);
        $this->assertStringNotContainsString('module_path(', $contents);
    }

    public function test_platform_route_provider_uses_module_manager_and_current_route_paths(): void
    {
        $contents = file_get_contents(base_path('modules/Platform/app/Providers/RouteServiceProvider.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('use App\\Modules\\ModuleManager;', $contents);
        $this->assertStringContainsString("app(ModuleManager::class)->isEnabled('Platform')", $contents);
        $this->assertStringContainsString("base_path('modules/Platform/routes/web.php')", $contents);
        $this->assertStringContainsString("base_path('modules/Platform/routes/api.php')", $contents);
        $this->assertStringNotContainsString('Nwidart\\Modules\\Facades\\Module', $contents);
    }

    public function test_platform_navigation_uses_current_sections_schema_and_valid_routes(): void
    {
        $contents = file_get_contents(base_path('modules/Platform/config/navigation.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("'sections' => [", $contents);
        $this->assertStringContainsString("'platform' => [", $contents);
        $this->assertStringContainsString("'<svg viewBox=", $contents);
        $this->assertStringContainsString("'route' => ['name' => 'platform.settings.index']", $contents);
        $this->assertStringContainsString("'route' => ['name' => 'platform.providers.index', 'params' => ['all']]", $contents);
        $this->assertStringNotContainsString("'section' => [", $contents);
        $this->assertStringNotContainsString("'route' => 'platform.settings.settings'", $contents);
        $this->assertStringNotContainsString("'icon' => 'ri-", $contents);
    }

    public function test_platform_module_exposes_frontend_abilities_for_crud_actions(): void
    {
        $contents = file_get_contents(base_path('modules/Platform/config/abilities.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("'addWebsites' => 'add_websites'", $contents);
        $this->assertStringContainsString("'addServers' => 'add_servers'", $contents);
        $this->assertStringContainsString("'addAgencies' => 'add_agencies'", $contents);
        $this->assertStringContainsString("'addDomains' => 'add_domains'", $contents);
        $this->assertStringContainsString("'addTlds' => 'add_tlds'", $contents);
        $this->assertStringContainsString("'addProviders' => 'add_providers'", $contents);
        $this->assertStringContainsString("'addSecrets' => 'add_secrets'", $contents);
        $this->assertStringContainsString("'managePlatformSettings' => 'manage_platform_settings'", $contents);
    }

    public function test_platform_module_is_enabled_in_module_manifest(): void
    {
        $contents = file_get_contents(base_path('modules.json'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('"Platform": "enabled"', $contents);
    }

    public function test_primary_platform_crud_controllers_use_inertia_scaffold_controller(): void
    {
        $agencyController = file_get_contents(base_path('modules/Platform/app/Http/Controllers/AgencyController.php'));
        $dnsController = file_get_contents(base_path('modules/Platform/app/Http/Controllers/DomainDnsController.php'));
        $serverController = file_get_contents(base_path('modules/Platform/app/Http/Controllers/ServerController.php'));
        $secretController = file_get_contents(base_path('modules/Platform/app/Http/Controllers/SecretController.php'));
        $websiteController = file_get_contents(base_path('modules/Platform/app/Http/Controllers/WebsiteController.php'));

        $this->assertIsString($agencyController);
        $this->assertIsString($dnsController);
        $this->assertIsString($serverController);
        $this->assertIsString($secretController);
        $this->assertIsString($websiteController);

        $this->assertStringContainsString('use App\\Scaffold\\ScaffoldController;', $agencyController);
        $this->assertStringContainsString('class AgencyController extends ScaffoldController', $agencyController);
        $this->assertStringContainsString("return 'platform/agencies';", $agencyController);
        $this->assertStringContainsString('class DomainDnsController extends ScaffoldController', $dnsController);
        $this->assertStringContainsString("return 'platform/dns';", $dnsController);
        $this->assertStringContainsString('class ServerController extends ScaffoldController', $serverController);
        $this->assertStringContainsString("return 'platform/servers';", $serverController);
        $this->assertStringContainsString('class SecretController extends ScaffoldController', $secretController);
        $this->assertStringContainsString("return 'platform/secrets';", $secretController);
        $this->assertStringContainsString('class WebsiteController extends ScaffoldController', $websiteController);
        $this->assertStringContainsString("return 'platform/websites';", $websiteController);
        $this->assertFileDoesNotExist(base_path('modules/Platform/app/Http/Controllers/PlatformScaffoldController.php'));
    }

    public function test_platform_module_has_inertia_pages_for_platform_crud_resources(): void
    {
        $paths = [
            'modules/Platform/resources/js/pages/platform/agencies/index.tsx',
            'modules/Platform/resources/js/pages/platform/agencies/create.tsx',
            'modules/Platform/resources/js/pages/platform/agencies/edit.tsx',
            'modules/Platform/resources/js/pages/platform/agencies/show.tsx',
            'modules/Platform/resources/js/pages/platform/domains/index.tsx',
            'modules/Platform/resources/js/pages/platform/domains/create.tsx',
            'modules/Platform/resources/js/pages/platform/domains/edit.tsx',
            'modules/Platform/resources/js/pages/platform/domains/show.tsx',
            'modules/Platform/resources/js/pages/platform/dns/index.tsx',
            'modules/Platform/resources/js/pages/platform/dns/create.tsx',
            'modules/Platform/resources/js/pages/platform/dns/edit.tsx',
            'modules/Platform/resources/js/pages/platform/dns/show.tsx',
            'modules/Platform/resources/js/pages/platform/providers/index.tsx',
            'modules/Platform/resources/js/pages/platform/providers/create.tsx',
            'modules/Platform/resources/js/pages/platform/providers/edit.tsx',
            'modules/Platform/resources/js/pages/platform/providers/show.tsx',
            'modules/Platform/resources/js/pages/platform/secrets/index.tsx',
            'modules/Platform/resources/js/pages/platform/secrets/create.tsx',
            'modules/Platform/resources/js/pages/platform/secrets/edit.tsx',
            'modules/Platform/resources/js/pages/platform/secrets/show.tsx',
            'modules/Platform/resources/js/pages/platform/servers/index.tsx',
            'modules/Platform/resources/js/pages/platform/servers/create.tsx',
            'modules/Platform/resources/js/pages/platform/servers/edit.tsx',
            'modules/Platform/resources/js/pages/platform/servers/show.tsx',
            'modules/Platform/resources/js/pages/platform/ssl-certificates/index.tsx',
            'modules/Platform/resources/js/pages/platform/ssl-certificates/create.tsx',
            'modules/Platform/resources/js/pages/platform/ssl-certificates/edit.tsx',
            'modules/Platform/resources/js/pages/platform/ssl-certificates/show.tsx',
            'modules/Platform/resources/js/pages/platform/ssl-certificates/generate-self-signed.tsx',
            'modules/Platform/resources/js/pages/platform/settings/index.tsx',
            'modules/Platform/resources/js/pages/platform/tlds/index.tsx',
            'modules/Platform/resources/js/pages/platform/tlds/create.tsx',
            'modules/Platform/resources/js/pages/platform/tlds/edit.tsx',
            'modules/Platform/resources/js/pages/platform/tlds/show.tsx',
            'modules/Platform/resources/js/pages/platform/websites/index.tsx',
            'modules/Platform/resources/js/pages/platform/websites/create.tsx',
            'modules/Platform/resources/js/pages/platform/websites/edit.tsx',
            'modules/Platform/resources/js/pages/platform/websites/show.tsx',
        ];

        foreach ($paths as $path) {
            $this->assertFileExists(base_path($path));
        }
    }

    public function test_platform_settings_controller_uses_inertia_and_legacy_settings_blade_views_are_removed(): void
    {
        $contents = file_get_contents(base_path('modules/Platform/app/Http/Controllers/SettingsController.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("Inertia::render('platform/settings/index'", $contents);
        $this->assertStringNotContainsString("view(self::MODULE_PATH.'.settings'", $contents);
        $this->assertStringNotContainsString('App\\Models\\Group', $contents);
        $this->assertFileDoesNotExist(base_path('modules/Platform/resources/views/settings/settings.blade.php'));
        $this->assertFileDoesNotExist(base_path('modules/Platform/resources/views/settings/settings_nav.blade.php'));
        $this->assertFileDoesNotExist(base_path('modules/Platform/resources/views/settings/partials/general.blade.php'));
    }

    public function test_platform_services_use_current_scaffold_datagrid_api(): void
    {
        $services = [
            'AgencyService',
            'DomainDnsRecordService',
            'ProviderService',
            'SecretService',
            'ServerService',
            'WebsiteService',
        ];

        foreach ($services as $service) {
            $contents = file_get_contents(base_path("modules/Platform/app/Services/{$service}.php"));

            $this->assertIsString($contents);
            $this->assertStringNotContainsString('getDataGridConfig', $contents);
            $this->assertStringNotContainsString('$this->scaffold()->toDataGridConfig()', $contents);
        }

        $this->assertFileDoesNotExist(base_path('modules/Platform/resources/js/lib/helpers.ts'));
        $this->assertFileDoesNotExist(base_path('modules/CMS/resources/js/lib/helpers.ts'));
    }
}
