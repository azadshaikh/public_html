<?php

namespace App\Modules\Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpModuleManifest('modules-management.json', [
            'CMS' => 'enabled',
            'ChatBot' => 'enabled',
            'Todos' => 'disabled',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_guests_are_redirected_from_the_module_management_page(): void
    {
        $this->get(route('app.masters.modules.index'))
            ->assertRedirect(route('login'));
    }

    public function test_super_users_can_view_the_module_management_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'first_name' => 'Module',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('super_user', 'web'));

        $this->actingAs($user)
            ->get(route('app.masters.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('modules/index')
                ->has('managedModules', 5)
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
                ->where('managedModules.2.name', 'Platform')
                ->where('managedModules.2.status', 'disabled')
                ->where('managedModules.3.name', 'ReleaseManager')
                ->where('managedModules.3.status', 'disabled')
                ->where('managedModules.4.name', 'Todos')
                ->where('managedModules.4.status', 'disabled'));
    }

    public function test_super_users_can_update_module_statuses(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'first_name' => 'Module',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('super_user', 'web'));

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
            'Platform' => 'disabled',
            'ReleaseManager' => 'disabled',
            'Todos' => 'enabled',
        ], json_decode((string) file_get_contents($this->moduleManifestPath), true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_non_super_users_cannot_view_the_module_management_page(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'first_name' => 'Module',
            'status' => Status::ACTIVE,
        ]);

        $user->assignRole(Role::findByName('administrator', 'web'));

        $this->actingAs($user)
            ->get(route('app.masters.modules.index'))
            ->assertForbidden();
    }
}
