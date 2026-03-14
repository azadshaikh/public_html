<?php

namespace Tests\Feature\Roles;

use App\Http\Controllers\RoleController;
use Illuminate\Routing\Route;
use Tests\TestCase;

class RoleRouteRegistrationTest extends TestCase
{
    public function test_role_routes_are_bound_to_the_singular_role_controller(): void
    {
        $expectedActions = [
            'app.roles.index' => RoleController::class.'@index',
            'app.roles.create' => RoleController::class.'@create',
            'app.roles.store' => RoleController::class.'@store',
            'app.roles.edit' => RoleController::class.'@edit',
            'app.roles.update' => RoleController::class.'@update',
            'app.roles.destroy' => RoleController::class.'@destroy',
            'app.roles.bulk-action' => RoleController::class.'@bulkAction',
        ];

        foreach ($expectedActions as $routeName => $expectedAction) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertInstanceOf(Route::class, $route);
            $this->assertSame($expectedAction, $route->getActionName());
        }
    }

    public function test_legacy_plural_role_controller_class_is_not_available(): void
    {
        $this->assertFalse(class_exists('App\\Http\\Controllers\\RolesController'));
    }
}
