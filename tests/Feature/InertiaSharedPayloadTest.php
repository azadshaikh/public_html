<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaSharedPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_shared_runtime_payload_omits_internal_module_metadata_and_unused_user_timestamps(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('super_user', 'web'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('dashboard')
                ->has('auth.user', fn (Assert $authUser): Assert => $authUser
                    ->where('id', $user->id)
                    ->where('name', $user->name)
                    ->where('email', $user->email)
                    ->where('avatar', fn (string $avatar): bool => $avatar !== '')
                    ->missing('email_verified_at')
                    ->missing('created_at')
                    ->missing('updated_at')
                    ->etc()
                )
                ->has('modules.items.0', fn (Assert $module): Assert => $module
                    ->hasAll(['name', 'slug', 'inertiaNamespace'])
                    ->missing('version')
                    ->missing('description')
                    ->missing('author')
                    ->missing('homepage')
                    ->missing('icon')
                    ->missing('url')
                    ->missing('provider')
                    ->missing('providerPath')
                    ->missing('pageRootPath')
                    ->missing('routeFiles')
                    ->missing('abilitiesPath')
                    ->missing('navigationPath')
                    ->missing('databaseSeederClass')
                    ->missing('databaseSeederPath')
                )
                ->has('auth.abilities', fn (Assert $abilities): Assert => $abilities
                    ->where('manageModules', true)
                    ->missing('addUsers')
                    ->missing('addPosts')
                    ->etc()
                ));
    }

    public function test_shared_runtime_payload_scopes_abilities_to_the_current_route_family(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('administrator', 'web'));

        $this->actingAs($user)
            ->get(route('app.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('roles/index')
                ->has('auth.abilities', fn (Assert $abilities): Assert => $abilities
                    ->where('manageModules', false)
                    ->where('addRoles', true)
                    ->where('editRoles', true)
                    ->where('deleteRoles', true)
                    ->where('restoreRoles', true)
                    ->missing('addUsers')
                    ->missing('addPosts')
                    ->missing('addTodos')
                    ->etc()
                ));
    }

    public function test_module_routes_receive_only_the_module_abilities_their_pages_consume(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('super_user', 'web'));

        $this->actingAs($user)
            ->get(route('cms.posts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('cms/posts/index')
                ->has('auth.abilities', fn (Assert $abilities): Assert => $abilities
                    ->where('manageModules', true)
                    ->where('addPosts', true)
                    ->where('editPosts', true)
                    ->where('deletePosts', true)
                    ->where('restorePosts', true)
                    ->missing('addUsers')
                    ->missing('addRoles')
                    ->missing('addTodos')
                    ->etc()
                ));
    }

    public function test_module_management_page_still_receives_rich_management_metadata(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
        ]);
        $user->assignRole(Role::findByName('super_user', 'web'));

        $this->actingAs($user)
            ->get(route('app.masters.modules.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('modules/index')
                ->where('managedModules', fn ($modules): bool => collect($modules)
                    ->isNotEmpty()
                    && collect($modules)->every(fn (array $module): bool => isset(
                        $module['provider'],
                        $module['providerPath'],
                        $module['pageRootPath'],
                        $module['routeFiles'],
                        $module['databaseSeederClass'],
                        $module['databaseSeederPath'],
                        $module['status'],
                        $module['enabled'],
                    ))));
    }
}
