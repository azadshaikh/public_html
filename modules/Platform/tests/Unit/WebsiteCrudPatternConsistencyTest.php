<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class WebsiteCrudPatternConsistencyTest extends TestCase
{
    public function test_website_secret_reveal_requires_edit_permission(): void
    {
        $controllerPath = base_path('modules/Platform/app/Http/Controllers/WebsiteController.php');
        $controllerContents = file_get_contents($controllerPath);

        $this->assertNotFalse($controllerContents, 'Failed to read modules/Platform/app/Http/Controllers/WebsiteController.php');
        $this->assertStringNotContainsString("new Middleware('permission:view_websites', only: ['revealSecret'])", $controllerContents);
        $this->assertStringContainsString("'reprovision', 'revealSecret'])", $controllerContents);
    }

    public function test_website_secret_reveal_route_uses_post_and_throttle(): void
    {
        $routesPath = base_path('modules/Platform/routes/web.php');
        $routesContents = file_get_contents($routesPath);

        $this->assertNotFalse($routesContents, 'Failed to read modules/Platform/routes/web.php');
        $this->assertStringContainsString("Route::post('/{website}/secrets/{secret}/reveal'", $routesContents);
        $this->assertStringContainsString("->middleware('throttle:20,1')", $routesContents);
        $this->assertStringNotContainsString("Route::get('/{website}/secrets/{secret}/reveal'", $routesContents);
    }

    public function test_website_recache_route_uses_post(): void
    {
        $routesPath = base_path('modules/Platform/routes/web.php');
        $routesContents = file_get_contents($routesPath);

        $this->assertNotFalse($routesContents, 'Failed to read modules/Platform/routes/web.php');
        $this->assertStringContainsString("Route::post('/{website}/recache-application'", $routesContents);
        $this->assertStringNotContainsString("Route::get('/{website}/recache-application'", $routesContents);
    }

    public function test_website_recache_action_requires_edit_permission(): void
    {
        $controllerPath = base_path('modules/Platform/app/Http/Controllers/WebsiteController.php');
        $controllerContents = file_get_contents($controllerPath);

        $this->assertNotFalse($controllerContents, 'Failed to read modules/Platform/app/Http/Controllers/WebsiteController.php');
        $this->assertStringContainsString("'syncWebsite', 'recacheApplication', 'retryProvision'", $controllerContents);
        $this->assertStringContainsString("Artisan::call('platform:hestia:recache-application'", $controllerContents);
    }

    public function test_website_reveal_response_has_no_cache_headers_and_activity_log(): void
    {
        $controllerPath = base_path('modules/Platform/app/Http/Controllers/WebsiteController.php');
        $controllerContents = file_get_contents($controllerPath);

        $this->assertNotFalse($controllerContents, 'Failed to read modules/Platform/app/Http/Controllers/WebsiteController.php');
        $this->assertStringContainsString('Revealed website secret', $controllerContents);
        $this->assertStringContainsString("->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')", $controllerContents);
        $this->assertStringContainsString("->header('Pragma', 'no-cache')", $controllerContents);
        $this->assertStringContainsString("->header('Expires', '0');", $controllerContents);
    }

    public function test_website_show_only_loads_secrets_for_editors(): void
    {
        $controllerPath = base_path('modules/Platform/app/Http/Controllers/WebsiteController.php');
        $controllerContents = file_get_contents($controllerPath);

        $this->assertNotFalse($controllerContents, 'Failed to read modules/Platform/app/Http/Controllers/WebsiteController.php');
        $this->assertStringContainsString("auth()->user()?->can('edit_websites')", $controllerContents);
        $this->assertStringContainsString("'secrets' => (\$canRevealSecrets ? \$website->secrets()->orderBy('key')->get() : collect())", $controllerContents);
    }

    public function test_website_show_secret_reveal_fetch_uses_post_json_requests(): void
    {
        $viewPath = base_path('modules/Platform/resources/js/pages/platform/websites/show.tsx');
        $viewContents = file_get_contents($viewPath);

        $this->assertNotFalse($viewContents, 'Failed to read modules/Platform/resources/js/pages/platform/websites/show.tsx');
        $this->assertStringContainsString("method: 'POST'", $viewContents);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $viewContents);
        $this->assertStringNotContainsString("method: 'GET'", $viewContents);
    }
}
