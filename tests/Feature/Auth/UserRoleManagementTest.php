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
                ->has('users', 2)
                ->where('users', fn (Collection $users): bool => $users->contains(
                    fn (array $user): bool => $user['email'] === 'casey@example.com'
                        && collect($user['roles'])->contains(
                            fn (array $role): bool => $role['name'] === 'staff'
                        )
                )));
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
            ])
            ->assertRedirect(route('users.index'));

        $managedUser->refresh();

        $this->assertSame('Updated User', $managedUser->name);
        $this->assertFalse($managedUser->active);
        $this->assertTrue($managedUser->hasRole('manager'));
        $this->assertTrue($managedUser->hasRole('staff'));
        $this->assertFalse($managedUser->hasRole('user'));
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
            ])
            ->assertRedirect();

        $this->assertTrue($superUser->fresh()->hasRole(Role::SUPER_USER));
    }

    protected function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::query()->where('name', 'administrator')->firstOrFail());

        return $user;
    }
}
