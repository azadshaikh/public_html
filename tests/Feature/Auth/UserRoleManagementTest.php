<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_are_redirected_from_the_users_index(): void
    {
        $this->get(route('app.users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permissions_cannot_view_the_users_index(): void
    {
        $user = User::factory()->create([
            'status' => Status::ACTIVE,
            'first_name' => 'Viewer',
            'last_name' => 'User',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('app.users.index'))
            ->assertForbidden();
    }

    public function test_administrators_can_view_the_users_index(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create([
            'name' => 'Casey Editor',
            'email' => 'casey@example.com',
            'status' => Status::ACTIVE,
        ]);
        $managedUser->assignRole(Role::query()->where('name', 'staff')->firstOrFail());

        $this->actingAs($administrator)
            ->get(route('app.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/index')
                ->has('users.data')
                ->has('statistics')
                ->where('filters.sort', 'created_at')
                ->where('filters.direction', 'desc')
                ->where('filters.view', 'table')
                ->where('users.data', fn (Collection $users): bool => $users
                    ->contains(fn (array $item): bool => $item['email'] === 'casey@example.com')));
    }

    public function test_administrators_can_view_the_user_create_screen(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->get(route('app.users.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/create')
                ->where('initialValues.status', 'active')
                ->has('statusOptions')
                ->has('availableRoles'));
    }

    public function test_administrators_can_view_the_user_edit_screen(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create([
            'status' => Status::SUSPENDED,
        ]);
        $managedUser->assignRole(Role::query()->where('name', 'user')->firstOrFail());

        $this->actingAs($administrator)
            ->get(route('app.users.edit', $managedUser))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('users/edit')
                ->where('user.id', $managedUser->id)
                ->where('user.status', Status::SUSPENDED->value)
                ->where('initialValues.status', Status::SUSPENDED->value)
                ->has('statusOptions')
                ->has('availableRoles'));
    }

    public function test_administrators_can_create_a_user_with_roles(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)
            ->post(route('app.users.store'), [
                'name' => 'Created User',
                'email' => 'created@gmail.com',
                'status' => Status::ACTIVE->value,
                'roles' => [
                    Role::query()->where('name', 'manager')->value('id'),
                    Role::query()->where('name', 'staff')->value('id'),
                ],
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertRedirect();

        $createdUser = User::query()->where('email', 'created@gmail.com')->firstOrFail();

        $this->assertSame('Created User', $createdUser->name);
        $this->assertSame(Status::ACTIVE, $createdUser->status);
        $this->assertTrue($createdUser->hasRole('manager'));
        $this->assertTrue($createdUser->hasRole('staff'));
        $this->assertNotSame('Password123!', $createdUser->password);
    }

    public function test_administrators_can_update_a_user_role_assignment_and_status(): void
    {
        $administrator = $this->administrator();
        $managedUser = User::factory()->create([
            'status' => Status::ACTIVE,
            'email' => 'managed.user@gmail.com',
        ]);
        $managedUser->assignRole(Role::query()->where('name', 'user')->firstOrFail());

        $this->actingAs($administrator)
            ->put(route('app.users.update', $managedUser), [
                'name' => 'Updated User',
                'email' => $managedUser->email,
                'status' => Status::SUSPENDED->value,
                'roles' => [
                    Role::query()->where('name', 'manager')->value('id'),
                    Role::query()->where('name', 'staff')->value('id'),
                ],
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect();

        $managedUser->refresh();

        $this->assertSame('Updated User', $managedUser->name);
        $this->assertSame(Status::SUSPENDED, $managedUser->status);
        $this->assertTrue($managedUser->hasRole('manager'));
        $this->assertTrue($managedUser->hasRole('staff'));
        $this->assertFalse($managedUser->hasRole('user'));
    }

    protected function administrator(): User
    {
        $user = User::factory()->create([
            'status' => Status::ACTIVE,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email_verified_at' => now(),
        ]);
        $user->assignRole(Role::findByName('administrator', 'web'));

        return $user;
    }
}
