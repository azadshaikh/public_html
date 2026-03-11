<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatBotModuleTest extends TestCase
{
    public function test_guests_are_redirected_away_from_the_chatbot_module(): void
    {
        $this->get(route('chatbot.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_chatbot_module_dashboard(): void
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('chatbot.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('chatbot/index')
                ->where('module.name', 'ChatBot')
                ->where('module.slug', 'chatbot')
                ->where('module.version', '1.0.0'));
    }
}
