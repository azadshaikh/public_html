<?php

namespace App\Modules\Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use App\Modules\ModuleManager;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manifestPath = storage_path('framework/testing/modules-management.json');

        File::ensureDirectoryExists(dirname($this->manifestPath));
        File::put($this->manifestPath, json_encode([
            'CMS' => 'enabled',
            'ChatBot' => 'enabled',
            'Todos' => 'disabled',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        config()->set('modules.manifest', $this->manifestPath);
        app()->forgetInstance(ModuleManager::class);
        app()->singleton(ModuleManager::class, fn ($app): ModuleManager => new ModuleManager(
            files: $app['files'],
            config: $app['config'],
        ));

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        File::delete($this->manifestPath);

        parent::tearDown();
    }

    public function test_guests_are_redirected_from_the_module_management_page(): void
    {
        $this->get(route('app.masters.modules.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_module_management_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'first_name' => 'Module',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('administrator', 'web'));

        $this->actingAs($user)
            ->get(route('app.masters.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('modules/index')
                ->has('managedModules', 3)
                ->where('managedModules.0.name', 'CMS')
                ->where('managedModules.0.version', '1.0.0')
                ->where('managedModules.0.author', null)
                ->where('managedModules.0.homepage', null)
                ->where('managedModules.0.icon', null)
                ->where('managedModules.0.status', 'enabled')
                ->where('managedModules.0.enabled', true)
                ->missing('managedModules.0.slug')
                ->missing('managedModules.0.url')
                ->missing('managedModules.0.inertiaNamespace')
                ->where('managedModules.1.name', 'ChatBot')
                ->where('managedModules.1.author', 'AsteroDigital')
                ->where('managedModules.1.homepage', 'https://asterodigital.com')
                ->where('managedModules.1.icon', fn (string $icon): bool => str_contains($icon, '<svg'))
                ->where('managedModules.1.status', 'enabled')
                ->where('managedModules.2.name', 'Todos')
                ->where('managedModules.2.status', 'disabled'));
    }

    public function test_authenticated_users_can_update_module_statuses(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'first_name' => 'Module',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('administrator', 'web'));

        $this->actingAs($user)
            ->patch(route('app.masters.modules.update'), [
                'modules' => [
                    'CMS' => 'disabled',
                    'ChatBot' => 'enabled',
                    'Todos' => 'enabled',
                ],
            ])
            ->assertRedirect(route('app.masters.modules.index'))
            ->assertSessionHas('success', 'Module settings updated.');

        $this->assertSame([
            'CMS' => 'disabled',
            'ChatBot' => 'enabled',
            'Todos' => 'enabled',
        ], json_decode((string) File::get($this->manifestPath), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_authenticated_users_without_permission_cannot_view_the_module_management_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'first_name' => 'Module',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('app.masters.modules.index'))
            ->assertForbidden();
    }
}
