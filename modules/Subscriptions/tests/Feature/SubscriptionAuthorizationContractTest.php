<?php

declare(strict_types=1);

namespace Modules\Subscriptions\Tests\Feature;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Modules\Subscriptions\Http\Controllers\PlanController;
use Modules\Subscriptions\Http\Controllers\SubscriptionController;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class SubscriptionAuthorizationContractTest extends TestCase
{
    use InteractsWithModuleManifest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('subscriptions-authorization.json', [
            'Subscriptions' => 'enabled',
        ]);

        $this->ensureSubscriptionsModuleBooted();
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_plan_controller_uses_flat_permission_names_for_standard_crud_middleware(): void
    {
        $middleware = collect(PlanController::middleware())
            ->map(fn ($middleware): string => (string) $middleware->middleware)
            ->all();

        $this->assertContains('permission:view_plans', $middleware);
        $this->assertContains('permission:add_plans', $middleware);
        $this->assertContains('permission:edit_plans', $middleware);
        $this->assertContains('permission:delete_plans', $middleware);
        $this->assertContains('permission:restore_plans', $middleware);
    }

    public function test_subscription_controller_uses_flat_permission_names_for_standard_crud_middleware(): void
    {
        $middleware = collect(SubscriptionController::middleware())
            ->map(fn ($middleware): string => (string) $middleware->middleware)
            ->all();

        $this->assertContains('permission:view_subscriptions', $middleware);
        $this->assertContains('permission:add_subscriptions', $middleware);
        $this->assertContains('permission:edit_subscriptions', $middleware);
        $this->assertContains('permission:delete_subscriptions', $middleware);
        $this->assertContains('permission:restore_subscriptions', $middleware);
    }

    public function test_subscription_lifecycle_routes_require_their_own_permissions(): void
    {
        $this->ensureSubscriptionsRoutesBooted();

        $this->assertRouteHasMiddleware('subscriptions.subscriptions.cancel', 'permission:cancel_subscriptions');
        $this->assertRouteHasMiddleware('subscriptions.subscriptions.resume', 'permission:resume_subscriptions');
        $this->assertRouteHasMiddleware('subscriptions.subscriptions.pause', 'permission:pause_subscriptions');
    }

    private function ensureSubscriptionsModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());
        $this->ensureSubscriptionsRoutesBooted();
    }

    private function ensureSubscriptionsRoutesBooted(): void
    {
        if (! RouteFacade::has('subscriptions.plans.index')) {
            RouteFacade::middleware('web')->group(base_path('modules/Subscriptions/routes/web.php'));
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
