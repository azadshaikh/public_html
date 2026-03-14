<?php

declare(strict_types=1);

namespace Tests\Feature\Logs;

use App\Enums\Status;
use App\Http\Middleware\EnsureSuperUserAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViewerAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    private User $admin;

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

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $this->admin->assignRole(Role::findByName('administrator', 'web'));
    }

    public function test_guests_are_redirected_away_from_log_viewer(): void
    {
        $this->get($this->logViewerPath())
            ->assertRedirect(route('login'));
    }

    public function test_non_super_users_cannot_access_log_viewer(): void
    {
        $this->actingAs($this->admin)
            ->get($this->logViewerPath())
            ->assertForbidden();
    }

    public function test_super_users_can_access_log_viewer(): void
    {
        $this->actingAs($this->superUser)
            ->get($this->logViewerPath())
            ->assertOk();
    }

    public function test_non_super_users_cannot_access_log_viewer_api(): void
    {
        $this->actingAs($this->admin)
            ->getJson($this->logViewerApiPath('/hosts'))
            ->assertForbidden();
    }

    public function test_super_users_can_access_log_viewer_api(): void
    {
        $this->actingAs($this->superUser)
            ->getJson($this->logViewerApiPath('/hosts'))
            ->assertOk();
    }

    public function test_log_viewer_uses_common_super_user_middleware_for_web_and_api_routes(): void
    {
        $this->assertContains(
            EnsureSuperUserAccess::class,
            config('log-viewer.middleware', []),
        );

        $this->assertContains(
            EnsureSuperUserAccess::class,
            config('log-viewer.api_middleware', []),
        );
    }

    private function logViewerPath(): string
    {
        return '/'.trim((string) config('log-viewer.route_path'), '/');
    }

    private function logViewerApiPath(string $path = ''): string
    {
        return rtrim($this->logViewerPath(), '/').'/api'.($path === '' ? '' : '/'.ltrim($path, '/'));
    }
}
