<?php

namespace App\Modules\Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use App\Modules\ModuleManager;
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

        $response = $this->actingAs($user)
            ->get(route('app.masters.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('modules/index')
                ->has('managedModules', app(ModuleManager::class)->all()->count()));

        /** @var array<int, array<string, mixed>> $managedModules */
        $managedModules = $response->inertiaProps('managedModules');
        $expectedManagedModules = app(ModuleManager::class)->managementData();
        $managedModulesByName = collect($managedModules)->keyBy('name');
        $expectedManagedModulesByName = $expectedManagedModules->keyBy('name');

        $this->assertCount($expectedManagedModules->count(), $managedModules);
        $this->assertSame(
            $expectedManagedModules->pluck('name')->all(),
            collect($managedModules)->pluck('name')->all(),
        );

        $this->assertSame($expectedManagedModulesByName->get('CMS'), $managedModulesByName->get('CMS'));
        $this->assertSame($expectedManagedModulesByName->get('ChatBot'), $managedModulesByName->get('ChatBot'));

        $this->assertSame('enabled', $managedModulesByName->get('CMS')['status']);
        $this->assertTrue($managedModulesByName->get('CMS')['enabled']);
        $this->assertSame('enabled', $managedModulesByName->get('ChatBot')['status']);
        $this->assertSame('disabled', $managedModulesByName->get('Platform')['status']);
        $this->assertSame('disabled', $managedModulesByName->get('ReleaseManager')['status']);
        $this->assertSame('disabled', $managedModulesByName->get('Todos')['status']);
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

        $expectedStatuses = app(ModuleManager::class)->all()
            ->mapWithKeys(fn ($module): array => [
                $module->name => match ($module->name) {
                    'CMS' => 'disabled',
                    'ChatBot' => 'enabled',
                    'Todos' => 'enabled',
                    default => 'disabled',
                },
            ])
            ->all();

        $this->assertSame(
            $expectedStatuses,
            json_decode((string) file_get_contents($this->moduleManifestPath), true, 512, JSON_THROW_ON_ERROR)
        );
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
