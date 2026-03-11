<?php

namespace Tests\Feature\Plugins;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TodosPluginTest extends TestCase
{
    public function test_guests_are_redirected_away_from_the_todos_plugin(): void
    {
        $this->get(route('todos.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_todos_plugin_dashboard(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('todos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('todos/index')
                ->where('module.name', 'Todos')
                ->where('module.slug', 'todos')
                ->where('module.version', '1.0.0')
                ->has('module.items', 3));
    }
}
