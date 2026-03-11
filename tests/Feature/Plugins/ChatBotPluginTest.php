<?php

namespace Tests\Feature\Plugins;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatBotPluginTest extends TestCase
{
    public function test_guests_are_redirected_away_from_the_chatbot_plugin(): void
    {
        $this->get(route('chatbot.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_chatbot_plugin_dashboard(): void
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
