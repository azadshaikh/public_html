<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_are_redirected_from_the_users_index(): void
    {
        $this->get(route('users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permissions_cannot_view_the_users_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_administrators_can_view_the_users_index(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create([
            'name' => 'Casey Editor',
            'email' => 'casey@example.com',
            'active' => true,
        ]);
        $managedUser->assignRole(Role::query()->where('name', 'staff')->firstOrFail());

        $this->actingAs($administrator)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/index')
                ->where('stats.total', 2)
                ->where('stats.active', 2)
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
                ->where('filters.per_page', 10)
                ->where('filters.view', 'table')
                ->where('users.total', 2)
                ->has('users.data', 2)
                ->where('users.data', fn (Collection $users): bool => $users->contains(
                    fn (array $user): bool => $user['email'] === 'casey@example.com'
                        && collect($user['roles'])->contains(
                            fn (array $role): bool => $role['name'] === 'staff'
                        )
                )));
    }

    public function test_administrators_can_filter_the_users_index(): void
    {
        $administrator = $this->administrator();
        $staffRole = Role::query()->where('name', 'staff')->firstOrFail();

        $inactiveUnverifiedUser = User::factory()->create([
            'name' => 'Morgan Staff',
            'email' => 'morgan@example.com',
            'active' => false,
            'email_verified_at' => null,
        ]);
        $inactiveUnverifiedUser->assignRole($staffRole);

        $activeVerifiedUser = User::factory()->create([
            'name' => 'Taylor Staff',
            'email' => 'taylor@example.com',
            'active' => true,
            'email_verified_at' => now(),
        ]);
        $activeVerifiedUser->assignRole($staffRole);

        $this->actingAs($administrator)
            ->get(route('users.index', [
                'status' => 'inactive',
                'role' => 'staff',
                'verification' => 'unverified',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/index')
                ->where('filters.status', 'inactive')
                ->where('filters.role', 'staff')
                ->where('filters.verification', 'unverified')
                ->where('users.total', 1)
                ->has('users.data', 1)
                ->where('users.data.0.email', 'morgan@example.com'));
    }

    public function test_administrators_can_view_the_user_edit_screen(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create();
        $managedUser->assignRole(Role::query()->where('name', 'user')->firstOrFail());

        $this->actingAs($administrator)
            ->get(route('users.edit', $managedUser))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/edit')
                ->where('user.id', $managedUser->id)
                ->where('initialValues.name', $managedUser->name)
                ->has('availableRoles'));
    }

    public function test_administrators_can_view_the_user_create_screen(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->get(route('users.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/create')
                ->where('initialValues.active', true)
                ->has('availableRoles'));
    }

    public function test_administrators_can_create_a_user_with_roles(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('users.store'), [
                'name' => 'Created User',
                'email' => 'created@example.com',
                'active' => true,
                'roles' => [
                    Role::query()->where('name', 'manager')->value('id'),
                    Role::query()->where('name', 'staff')->value('id'),
                ],
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect(route('users.index'));

        $createdUser = User::query()->where('email', 'created@example.com')->firstOrFail();

        $this->assertSame('Created User', $createdUser->name);
        $this->assertTrue($createdUser->active);
        $this->assertTrue($createdUser->hasRole('manager'));
        $this->assertTrue($createdUser->hasRole('staff'));
        $this->assertNotSame('Password123!', $createdUser->password);
    }

    public function test_administrators_can_update_a_user_role_assignment_and_active_status(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create([
            'active' => true,
        ]);
        $managedUser->assignRole(Role::query()->where('name', 'user')->firstOrFail());

        $this->actingAs($administrator)
            ->put(route('users.update', $managedUser), [
                'name' => 'Updated User',
                'email' => $managedUser->email,
                'active' => false,
                'roles' => [
                    Role::query()->where('name', 'manager')->value('id'),
                    Role::query()->where('name', 'staff')->value('id'),
                ],
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('users.index'));

        $managedUser->refresh();

        $this->assertSame('Updated User', $managedUser->name);
        $this->assertFalse($managedUser->active);
        $this->assertTrue($managedUser->hasRole('manager'));
        $this->assertTrue($managedUser->hasRole('staff'));
        $this->assertFalse($managedUser->hasRole('user'));
    }

    public function test_administrators_can_bulk_delete_eligible_users(): void
    {
        $administrator = $this->administrator();
        $staffRole = Role::query()->where('name', 'staff')->firstOrFail();

        $deletableUser = User::factory()->create([
            'name' => 'Bulk Delete User',
            'email' => 'bulk-delete-user@example.com',
        ]);
        $deletableUser->assignRole($staffRole);

        $protectedSuperUser = User::factory()->create([
            'name' => 'Protected Super User',
            'email' => 'protected-super-user@example.com',
        ]);
        $protectedSuperUser->assignRole(Role::query()->where('name', Role::SUPER_USER)->firstOrFail());

        $this->actingAs($administrator)
            ->delete(route('users.bulk-destroy'), [
                'user_ids' => [$deletableUser->id, $protectedSuperUser->id, $administrator->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('users', [
            'id' => $deletableUser->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $protectedSuperUser->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $administrator->id,
        ]);
    }

    public function test_users_without_delete_permissions_cannot_bulk_delete_users(): void
    {
        $user = User::factory()->create();
        $managedUser = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('users.bulk-destroy'), [
                'user_ids' => [$managedUser->id],
            ])
            ->assertForbidden();
    }

    public function test_the_last_super_user_assignment_cannot_be_removed(): void
    {
        $administrator = $this->administrator();
        $superUser = User::factory()->create();
        $superUser->assignRole(Role::query()->where('name', Role::SUPER_USER)->firstOrFail());

        $this->actingAs($administrator)
            ->put(route('users.update', $superUser), [
                'name' => $superUser->name,
                'email' => $superUser->email,
                'active' => true,
                'roles' => [
                    Role::query()->where('name', 'administrator')->value('id'),
                ],
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect();

        $this->assertTrue($superUser->fresh()->hasRole(Role::SUPER_USER));
    }

    public function test_administrators_can_delete_a_managed_user(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create();
        $managedUser->assignRole(Role::query()->where('name', 'user')->firstOrFail());

        $this->actingAs($administrator)
            ->delete(route('users.destroy', $managedUser))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('users', [
            'id' => $managedUser->id,
        ]);
    }

    public function test_the_last_super_user_account_cannot_be_deleted(): void
    {
        $administrator = $this->administrator();
        $superUser = User::factory()->create();
        $superUser->assignRole(Role::query()->where('name', Role::SUPER_USER)->firstOrFail());

        $this->actingAs($administrator)
            ->delete(route('users.destroy', $superUser))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $superUser->id,
        ]);
    }

    public function test_administrators_cannot_delete_their_own_account_from_the_user_registry(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->delete(route('users.destroy', $administrator))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $administrator->id,
        ]);
    }

    protected function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::query()->where('name', 'administrator')->firstOrFail());

        return $user;
    }
}
