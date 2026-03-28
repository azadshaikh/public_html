<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class ModuleAdminRouteMiddlewareTest extends TestCase
{
    use InteractsWithModuleManifest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('module-admin-route-middleware.json');
        $this->withEnabledModules($this->enabledModules());
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function adminModuleRoutesProvider(): array
    {
        return [
            'agency admin settings' => ['Agency', 'agency.admin.settings.index', 'modules/Agency/routes/web.php'],
            'ai registry providers' => ['AIRegistry', 'ai-registry.providers.index', 'modules/AIRegistry/routes/web.php'],
            'billing invoices' => ['Billing', 'app.billing.invoices.index', 'modules/Billing/routes/web.php'],
            'cms posts' => ['CMS', 'cms.posts.index', 'modules/CMS/routes/web.php'],
            'chatbot conversations' => ['ChatBot', 'app.chatbot.index', 'modules/ChatBot/routes/web.php'],
            'customers' => ['Customers', 'app.customers.index', 'modules/Customers/routes/web.php'],
            'helpdesk tickets' => ['Helpdesk', 'helpdesk.tickets.index', 'modules/Helpdesk/routes/web.php'],
            'orders' => ['Orders', 'app.orders.index', 'modules/Orders/routes/web.php'],
            'platform websites' => ['Platform', 'platform.websites.index', 'modules/Platform/routes/web.php'],
            'release manager releases' => ['ReleaseManager', 'releasemanager.releases.index', 'modules/ReleaseManager/routes/web.php'],
            'subscription plans' => ['Subscriptions', 'subscriptions.plans.index', 'modules/Subscriptions/routes/web.php'],
        ];
    }

    #[DataProvider('adminModuleRoutesProvider')]
    public function test_module_admin_routes_use_the_protected_admin_baseline(
        string $module,
        string $routeName,
        string $routePath,
    ): void {
        $this->ensureModuleRoutesBooted($module, $routeName, $routePath);

        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertInstanceOf(LaravelRoute::class, $route, sprintf('Route [%s] should be registered.', $routeName));

        $middleware = $route->gatherMiddleware();

        $this->assertContains('auth', $middleware, sprintf('Route [%s] should require auth.', $routeName));
        $this->assertContains('user.status', $middleware, sprintf('Route [%s] should enforce account status.', $routeName));
        $this->assertContains('verified', $middleware, sprintf('Route [%s] should require verified email.', $routeName));
        $this->assertContains('profile.completed', $middleware, sprintf('Route [%s] should require a completed profile.', $routeName));
    }

    /**
     * @return array<int, string>
     */
    private function enabledModules(): array
    {
        return [
            'Agency',
            'AIRegistry',
            'Billing',
            'CMS',
            'ChatBot',
            'Customers',
            'Helpdesk',
            'Orders',
            'Platform',
            'ReleaseManager',
            'Subscriptions',
        ];
    }

    private function ensureModuleRoutesBooted(string $module, string $routeName, string $routePath): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has($routeName)) {
            Route::middleware('web')->group(base_path($routePath));
            app('router')->getRoutes()->refreshNameLookups();
            app('router')->getRoutes()->refreshActionLookups();
        }

        $this->assertTrue(
            Route::has($routeName),
            sprintf('Route [%s] from module [%s] should be booted for this test.', $routeName, $module),
        );
    }
}
