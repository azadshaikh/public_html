<?php

declare(strict_types=1);

namespace Tests\Feature\Masters;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LaravelToolsRouteContractTest extends TestCase
{
    public function test_cache_clear_route_requires_authentication_and_uses_post_semantics(): void
    {
        $route = Route::getRoutes()->getByName('cache.clear');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertContains('auth', $route->gatherMiddleware());
    }
}
