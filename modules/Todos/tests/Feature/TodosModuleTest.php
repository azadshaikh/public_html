<?php

namespace Modules\Todos\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Todos\Models\TodoTask;
use Tests\TestCase;

class TodosModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_away_from_the_todos_module(): void
    {
        $this->get(route('todos.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_and_filter_tasks(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        TodoTask::query()->create($this->validPayload([
            'title' => 'Launch checklist',
            'slug' => 'launch-checklist',
            'status' => 'in_progress',
        ]));

        TodoTask::query()->create($this->validPayload([
            'title' => 'Backlog cleanup',
            'slug' => 'backlog-cleanup',
            'status' => 'backlog',
        ]));

        $this->actingAs($user)
            ->get(route('todos.index', [
                'status' => 'in_progress',
                'search' => 'launch',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('todos/index')
                ->where('module.name', 'Todos')
                ->where('filters.status', 'in_progress')
                ->where('filters.search', 'launch')
                ->has('tasks.data')
                ->where('tasks.data', fn ($tasks): bool => collect($tasks)
                    ->pluck('slug')
                    ->contains('launch-checklist')));
    }

    public function test_authenticated_users_can_create_a_task(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('todos.store'), $this->validPayload())
            ->assertRedirect(route('todos.index'));

        $this->assertDatabaseHas('todo_tasks', [
            'title' => 'Launch checklist',
            'slug' => 'launch-checklist',
            'status' => 'in_progress',
        ]);
    }

    public function test_authenticated_users_can_update_a_task(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $task = TodoTask::query()->create($this->validPayload());

        $this->actingAs($user)
            ->patch(route('todos.update', $task), $this->validPayload([
                'title' => 'Release checklist',
                'slug' => 'release-checklist',
                'is_blocked' => false,
            ]))
            ->assertRedirect(route('todos.index'));

        $this->assertDatabaseHas('todo_tasks', [
            'id' => $task->id,
            'title' => 'Release checklist',
            'slug' => 'release-checklist',
            'is_blocked' => false,
        ]);
    }

    public function test_authenticated_users_can_delete_a_task(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $task = TodoTask::query()->create($this->validPayload());

        $this->actingAs($user)
            ->delete(route('todos.destroy', $task))
            ->assertRedirect(route('todos.index'));

        $this->assertDatabaseMissing('todo_tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_task_creation_requires_a_unique_slug(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        TodoTask::query()->create($this->validPayload());

        $this->actingAs($user)
            ->from(route('todos.create'))
            ->post(route('todos.store'), $this->validPayload())
            ->assertRedirect(route('todos.create'))
            ->assertSessionHasErrors(['slug']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Launch checklist',
            'slug' => 'launch-checklist',
            'details' => 'Track the remaining launch work for the sample module so it is easy to grow into projects, comments, and automation later.',
            'status' => 'in_progress',
            'priority' => 'high',
            'owner' => 'Operations',
            'due_date' => '2026-03-18',
            'is_blocked' => true,
        ], $overrides);
    }
}
