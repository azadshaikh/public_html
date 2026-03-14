<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Status;
use App\Http\Middleware\EnsureSuperUserAccess;
use App\Models\Role;
use App\Models\User;
use App\Support\Auth\SuperUserAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SuperUserAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superUser = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $this->superUser->assignRole(Role::findByName('super_user', 'web'));

        $this->administrator = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $this->administrator->assignRole(Role::findByName('administrator', 'web'));
    }

    public function test_helper_allows_only_super_users(): void
    {
        $this->assertTrue(SuperUserAccess::allows($this->superUser));
        $this->assertFalse(SuperUserAccess::allows($this->administrator));
        $this->assertFalse(SuperUserAccess::allows(null));
    }

    public function test_middleware_allows_super_users(): void
    {
        $request = Request::create('/admin/test');
        $request->setUserResolver(fn (): User => $this->superUser);

        $response = app(EnsureSuperUserAccess::class)->handle(
            $request,
            fn () => response('ok'),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_middleware_blocks_non_super_users(): void
    {
        $request = Request::create('/admin/test');
        $request->setUserResolver(fn (): User => $this->administrator);

        $this->expectException(HttpException::class);

        app(EnsureSuperUserAccess::class)->handle(
            $request,
            fn () => response('ok'),
        );
    }

    public function test_broadcast_notification_routes_use_common_super_user_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('app.notifications.broadcast.create');

        $this->assertNotNull($route);
        $this->assertContains(EnsureSuperUserAccess::class, $route->gatherMiddleware());
    }
}
