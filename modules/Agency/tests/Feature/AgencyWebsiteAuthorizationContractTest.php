<?php

declare(strict_types=1);

namespace Modules\Agency\Tests\Feature;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Modules\Agency\Http\Controllers\WebsiteManageController;
use Modules\Agency\Providers\AgencyServiceProvider;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class AgencyWebsiteAuthorizationContractTest extends TestCase
{
    use InteractsWithModuleManifest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('agency-website-authorization.json', [
            'Agency' => 'enabled',
        ]);

        $this->ensureAgencyModuleBooted();
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_website_manage_controller_exports_the_expected_standard_permission_middleware(): void
    {
        $middleware = collect(WebsiteManageController::middleware())
            ->map(fn ($middleware): string => (string) $middleware->middleware)
            ->all();

        $this->assertContains('permission:view_agency_websites', $middleware);
        $this->assertContains('permission:add_agency_websites', $middleware);
        $this->assertContains('permission:edit_agency_websites', $middleware);
        $this->assertContains('permission:delete_agency_websites', $middleware);
        $this->assertContains('permission:restore_agency_websites', $middleware);
    }

    public function test_agency_website_routes_require_permissions_for_custom_actions_and_show_routes(): void
    {
        $this->ensureAgencyRoutesBooted();

        $this->assertRouteHasMiddleware('agency.admin.websites.show', 'permission:view_agency_websites');
        $this->assertRouteHasMiddleware('agency.admin.websites.destroy', 'permission:delete_agency_websites');
        $this->assertRouteHasMiddleware('agency.admin.websites.restore', 'permission:restore_agency_websites');
        $this->assertRouteHasMiddleware('agency.admin.websites.force-delete', 'permission:delete_agency_websites');
        $this->assertRouteHasMiddleware('agency.admin.websites.suspend', 'permission:edit_agency_websites');
        $this->assertRouteHasMiddleware('agency.admin.websites.unsuspend', 'permission:edit_agency_websites');
        $this->assertRouteHasMiddleware('agency.admin.websites.sync', 'permission:edit_agency_websites');
        $this->assertRouteHasMiddleware('agency.admin.websites.retry-provision', 'permission:edit_agency_websites');
    }

    private function ensureAgencyModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! RouteFacade::has('agency.admin.websites.index')) {
            app()->register(AgencyServiceProvider::class);
        }

        $this->ensureAgencyRoutesBooted();
    }

    private function ensureAgencyRoutesBooted(): void
    {
        if (! RouteFacade::has('agency.admin.websites.index')) {
            RouteFacade::middleware('web')->group(base_path('modules/Agency/routes/web.php'));
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }
    }

    private function assertRouteHasMiddleware(string $routeName, string $expectedMiddleware): void
    {
        $route = RouteFacade::getRoutes()->getByName($routeName);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertContains($expectedMiddleware, $route->gatherMiddleware());
    }
}
