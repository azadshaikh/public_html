<?php

declare(strict_types=1);

namespace Modules\ReleaseManager\Tests\Feature;

use Tests\TestCase;

class ReleaseCrudMigrationTest extends TestCase
{
    public function test_releasemanager_routes_are_registered(): void
    {
        $contents = file_get_contents(base_path('modules/ReleaseManager/routes/web.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("\$registerReleaseRoutes('application', 'application', 'application');", $contents);
        $this->assertStringContainsString("\$registerReleaseRoutes('module', 'module', 'module');", $contents);
        $this->assertStringContainsString("->defaults('type', \$type)->name('index');", $contents);
        $this->assertStringContainsString("admin_slug'), '/').'/releasemanager/application", $contents);
    }

    public function test_release_form_uses_supported_frontend_dependencies(): void
    {
        $contents = file_get_contents(base_path('modules/ReleaseManager/resources/js/components/release-form.tsx'));
        $indexPage = file_get_contents(base_path('modules/ReleaseManager/resources/js/pages/releasemanager/releases/index.tsx'));

        $this->assertIsString($contents);
        $this->assertIsString($indexPage);
        $this->assertStringContainsString('const routeNamespace = type === \'module\' ? \'releasemanager.module\' : \'releasemanager.application\';', $contents);
        $this->assertStringContainsString('type="date"', $contents);
        $this->assertStringContainsString('put(submitUrl);', $contents);
        $this->assertStringNotContainsString("import axios from 'axios';", $contents);
        $this->assertStringNotContainsString('@/components/ui/date-picker', $contents);
        $this->assertStringContainsString('route(`${routeNamespace}.index`)', $indexPage);
    }

    public function test_release_backend_preserves_type_query_and_release_parameter_names(): void
    {
        $controller = file_get_contents(base_path('modules/ReleaseManager/app/Http/Controllers/ReleaseController.php'));
        $resource = file_get_contents(base_path('modules/ReleaseManager/app/Http/Resources/ReleaseResource.php'));
        $navigation = file_get_contents(base_path('modules/ReleaseManager/config/navigation.php'));

        $this->assertIsString($controller);
        $this->assertIsString($resource);
        $this->assertIsString($navigation);

        $this->assertStringContainsString('private function routeNamespace(): string', $controller);
        $this->assertStringContainsString("request()->route('type')", $controller);
        $this->assertStringContainsString("route(\$this->routeNamespace(\$type).'.show'", $resource);
        $this->assertStringContainsString("'active_patterns' => [\$routeGroup.'.*']", $navigation);
        $this->assertStringContainsString("'sections' => [", $navigation);
    }
}
