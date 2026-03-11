<?php

namespace Modules\ChatBot\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\ChatBot\Models\PromptTemplate;
use Tests\TestCase;

class ChatBotModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_away_from_the_chatbot_module(): void
    {
        $this->get(route('chatbot.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_and_filter_prompt_templates(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        PromptTemplate::query()->create($this->validPayload([
            'name' => 'Support concierge',
            'slug' => 'support-concierge',
            'status' => 'active',
        ]));

        PromptTemplate::query()->create($this->validPayload([
            'name' => 'Internal triage',
            'slug' => 'internal-triage',
            'status' => 'draft',
        ]));

        $this->actingAs($user)
            ->get(route('chatbot.index', [
                'status' => 'active',
                'search' => 'support',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('chatbot/index')
                ->where('module.name', 'ChatBot')
                ->where('filters.status', 'active')
                ->where('filters.search', 'support')
                ->has('prompts.data', 1)
                ->where('prompts.data.0.slug', 'support-concierge'));
    }

    public function test_authenticated_users_can_create_a_prompt_template(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('chatbot.store'), $this->validPayload())
            ->assertRedirect(route('chatbot.index'));

        $this->assertDatabaseHas('prompt_templates', [
            'name' => 'Support concierge',
            'slug' => 'support-concierge',
            'status' => 'active',
        ]);
    }

    public function test_authenticated_users_can_update_a_prompt_template(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $prompt = PromptTemplate::query()->create($this->validPayload());

        $this->actingAs($user)
            ->patch(route('chatbot.update', $prompt), $this->validPayload([
                'name' => 'Escalation concierge',
                'slug' => 'escalation-concierge',
                'is_default' => false,
            ]))
            ->assertRedirect(route('chatbot.index'));

        $this->assertDatabaseHas('prompt_templates', [
            'id' => $prompt->id,
            'name' => 'Escalation concierge',
            'slug' => 'escalation-concierge',
            'is_default' => false,
        ]);
    }

    public function test_authenticated_users_can_delete_a_prompt_template(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $prompt = PromptTemplate::query()->create($this->validPayload());

        $this->actingAs($user)
            ->delete(route('chatbot.destroy', $prompt))
            ->assertRedirect(route('chatbot.index'));

        $this->assertDatabaseMissing('prompt_templates', [
            'id' => $prompt->id,
        ]);
    }

    public function test_prompt_template_creation_requires_a_unique_slug(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        PromptTemplate::query()->create($this->validPayload());

        $this->actingAs($user)
            ->from(route('chatbot.create'))
            ->post(route('chatbot.store'), $this->validPayload())
            ->assertRedirect(route('chatbot.create'))
            ->assertSessionHasErrors(['slug']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Support concierge',
            'slug' => 'support-concierge',
            'purpose' => 'Handle customer support handoffs with a calm, branded response style.',
            'model' => 'gpt-4.1-mini',
            'tone' => 'supportive',
            'system_prompt' => 'You are a calm, clear support concierge for an internal SaaS product. Ask focused follow-up questions, summarize the current issue, and keep every answer actionable and easy to hand off to a human teammate when needed.',
            'notes' => 'This template is a good base for support, onboarding, and escalation assistants.',
            'status' => 'active',
            'is_default' => true,
        ], $overrides);
    }
}
