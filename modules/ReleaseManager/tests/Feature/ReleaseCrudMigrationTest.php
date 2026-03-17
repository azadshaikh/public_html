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
        $this->assertStringContainsString("'prefix' => 'releases/{type}'", $contents);
        $this->assertStringContainsString("'as' => 'releases.'", $contents);
        $this->assertStringContainsString("'where' => ['type' => \$releaseTypePattern]", $contents);
        $this->assertStringContainsString("->where('status', \$statusPattern)", $contents);
    }

    public function test_release_form_uses_supported_frontend_dependencies(): void
    {
        $contents = file_get_contents(base_path('modules/ReleaseManager/resources/js/components/release-form.tsx'));
        $indexPage = file_get_contents(base_path('modules/ReleaseManager/resources/js/pages/releasemanager/releases/index.tsx'));

        $this->assertIsString($contents);
        $this->assertIsString($indexPage);
        $this->assertStringContainsString('route(`${routeNamespace}.next-version`', $contents);
        $this->assertStringContainsString('type="date"', $contents);
        $this->assertStringContainsString('put(submitUrl);', $contents);
        $this->assertStringNotContainsString("import axios from 'axios';", $contents);
        $this->assertStringNotContainsString('@/components/ui/date-picker', $contents);
        $this->assertStringContainsString("route('releasemanager.releases.index'", $indexPage);
    }

    public function test_release_backend_preserves_type_query_and_release_parameter_names(): void
    {
        $controller = file_get_contents(base_path('modules/ReleaseManager/app/Http/Controllers/ReleaseController.php'));
        $resource = file_get_contents(base_path('modules/ReleaseManager/app/Http/Resources/ReleaseResource.php'));
        $navigation = file_get_contents(base_path('modules/ReleaseManager/config/navigation.php'));

        $this->assertIsString($controller);
        $this->assertIsString($resource);
        $this->assertIsString($navigation);

        $this->assertStringContainsString('private function releaseRouteParameters(array $parameters = []): array', $controller);
        $this->assertStringContainsString("request()->route('type')", $controller);
        $this->assertStringContainsString("route('releasemanager.releases.show'", $resource);
        $this->assertStringContainsString("['route' => 'releasemanager.releases.index', 'params' => ['type' => \$typeValue]]", $navigation);
        $this->assertStringContainsString("'sections' => [", $navigation);
    }

    public function test_release_controller_uses_type_scoped_method_signatures_for_nested_routes(): void
    {
        $controller = file_get_contents(base_path('modules/ReleaseManager/app/Http/Controllers/ReleaseController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString('public function show(int|string $id, int|string|null $release = null): Response', $controller);
        $this->assertStringContainsString('public function edit(int|string $id, int|string|null $release = null): Response', $controller);
        $this->assertStringContainsString('public function update(Request $request, int|string $id, int|string|null $release = null): RedirectResponse', $controller);
        $this->assertStringContainsString('public function destroy(int|string $id, int|string|null $release = null): RedirectResponse', $controller);
        $this->assertStringContainsString('public function restore(int|string $id, int|string|null $release = null): RedirectResponse', $controller);
        $this->assertStringContainsString('public function forceDelete(int|string $id, int|string|null $release = null): RedirectResponse', $controller);
        $this->assertStringContainsString('private function resolveReleaseId(int|string $id, int|string|null $release = null): int|string', $controller);
    }
}
