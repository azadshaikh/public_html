<?php

declare(strict_types=1);

namespace Modules\Todos\Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Todos\Database\Factories\TodoFactory;
use Modules\Todos\Database\Seeders\PermissionSeeder;
use Modules\Todos\Models\Todo;
use Tests\TestCase;

class TodoCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->admin = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole(Role::findByName('administrator', 'web'));
    }

    // =========================================================================
    // INDEX
    // =========================================================================

    public function test_guests_are_redirected_from_todos_index(): void
    {
        $this->get(route('app.todos.index'))
            ->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_todos_index(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('app.todos.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_todos_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.todos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('todos/index')
                ->has('todos')
                ->has('todos.data')
                ->has('statistics')
                ->has('filters')
            );
    }

    public function test_todos_index_returns_paginated_results(): void
    {
        TodoFactory::new()->count(5)->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get(route('app.todos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('todos.data', 5)
                ->has('statistics.total')
            );
    }

    // =========================================================================
    // CREATE / STORE
    // =========================================================================

    public function test_guests_are_redirected_from_create_page(): void
    {
        $this->get(route('app.todos.create'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_access_create_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.todos.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('todos/create')
                ->has('initialValues')
                ->has('statusOptions')
                ->has('priorityOptions')
                ->has('visibilityOptions')
                ->has('assigneeOptions')
            );
    }

    public function test_admin_can_create_a_todo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.todos.store'), [
                'title' => 'Test Todo Task',
                'description' => 'A test description.',
                'status' => 'pending',
                'priority' => 'medium',
                'visibility' => 'private',
                'is_starred' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('todos', [
            'title' => 'Test Todo Task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.todos.store'), [])
            ->assertSessionHasErrors(['title', 'status', 'priority', 'visibility']);
    }

    // =========================================================================
    // SHOW
    // =========================================================================

    public function test_admin_can_view_a_todo(): void
    {
        $todo = TodoFactory::new()->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get(route('app.todos.show', $todo->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('todos/show')
                ->has('todo')
                ->where('todo.id', $todo->id)
            );
    }

    // =========================================================================
    // EDIT / UPDATE
    // =========================================================================

    public function test_admin_can_access_edit_page(): void
    {
        $todo = TodoFactory::new()->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get(route('app.todos.edit', $todo->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('todos/edit')
                ->has('todo')
                ->has('initialValues')
                ->has('statusOptions')
            );
    }

    public function test_admin_can_update_a_todo(): void
    {
        $todo = TodoFactory::new()->create([
            'user_id' => $this->admin->id,
            'title' => 'Original Title',
            'status' => 'pending',
            'priority' => 'low',
            'visibility' => 'private',
        ]);

        $this->actingAs($this->admin)
            ->put(route('app.todos.update', $todo->id), [
                'title' => 'Updated Title',
                'status' => 'in_progress',
                'priority' => 'high',
                'visibility' => 'public',
                'is_starred' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'title' => 'Updated Title',
            'status' => 'in_progress',
            'priority' => 'high',
        ]);
    }

    // =========================================================================
    // DESTROY / RESTORE / FORCE DELETE
    // =========================================================================

    public function test_admin_can_soft_delete_a_todo(): void
    {
        $todo = TodoFactory::new()->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->delete(route('app.todos.destroy', $todo->id))
            ->assertRedirect();

        $this->assertSoftDeleted('todos', ['id' => $todo->id]);
    }

    public function test_admin_can_restore_a_trashed_todo(): void
    {
        $todo = TodoFactory::new()->create(['user_id' => $this->admin->id]);
        $todo->delete();

        $this->assertSoftDeleted('todos', ['id' => $todo->id]);

        $this->actingAs($this->admin)
            ->patch(route('app.todos.restore', $todo->id))
            ->assertRedirect();

        $this->assertNotSoftDeleted('todos', ['id' => $todo->id]);
    }

    public function test_admin_can_permanently_delete_a_trashed_todo(): void
    {
        $todo = TodoFactory::new()->create(['user_id' => $this->admin->id]);
        $todo->delete();

        $this->actingAs($this->admin)
            ->delete(route('app.todos.force-delete', $todo->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('todos', ['id' => $todo->id]);
    }

    public function test_cannot_permanently_delete_a_non_trashed_todo(): void
    {
        $todo = TodoFactory::new()->create(['user_id' => $this->admin->id]);

        // Should fail since it's not in trash
        $this->actingAs($this->admin)
            ->delete(route('app.todos.force-delete', $todo->id))
            ->assertRedirect();

        // Todo should still exist
        $this->assertDatabaseHas('todos', ['id' => $todo->id]);
    }

    // =========================================================================
    // BULK ACTIONS
    // =========================================================================

    public function test_admin_can_bulk_delete_todos(): void
    {
        $todos = TodoFactory::new()->count(3)->create(['user_id' => $this->admin->id]);
        $ids = $todos->pluck('id')->toArray();

        $this->actingAs($this->admin)
            ->post(route('app.todos.bulk-action'), [
                'action' => 'delete',
                'ids' => $ids,
            ])
            ->assertRedirect();

        foreach ($ids as $id) {
            $this->assertSoftDeleted('todos', ['id' => $id]);
        }
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    public function test_statistics_reflect_todo_counts(): void
    {
        TodoFactory::new()->count(2)->create([
            'user_id' => $this->admin->id,
            'status' => 'pending',
        ]);
        TodoFactory::new()->count(1)->create([
            'user_id' => $this->admin->id,
            'status' => 'completed',
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.todos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('statistics.total', 3)
                ->where('statistics.pending', 2)
                ->where('statistics.completed', 1)
            );
    }
}
