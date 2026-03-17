<?php

namespace Modules\ChatBot\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\ChatBot\Models\PromptTemplate;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the chatbot module tables.
     */
    public function run(): void
    {
        if (! Schema::hasTable('prompt_templates') || PromptTemplate::query()->exists()) {
            return;
        }

        collect([
            [
                'name' => 'Support concierge',
                'slug' => 'support-concierge',
                'purpose' => 'Handle support triage and gather issue details before escalation.',
                'model' => 'gpt-4.1-mini',
                'tone' => 'supportive',
                'system_prompt' => 'You are a support concierge. Clarify the issue, summarize customer intent, and gather the minimum details needed for handoff.',
                'notes' => 'Default support template for chat-based triage.',
                'status' => 'active',
                'is_default' => true,
            ],
            [
                'name' => 'Onboarding coach',
                'slug' => 'onboarding-coach',
                'purpose' => 'Guide new customers through setup and first-run tasks.',
                'model' => 'gpt-4.1-mini',
                'tone' => 'friendly',
                'system_prompt' => 'You are an onboarding coach. Keep steps short, positive, and tailored to first-time users.',
                'notes' => 'Useful for setup flows and account activation follow-up.',
                'status' => 'active',
                'is_default' => false,
            ],
            [
                'name' => 'Escalation triage',
                'slug' => 'escalation-triage',
                'purpose' => 'Prepare structured issue summaries for engineering escalation.',
                'model' => 'gpt-4.1-mini',
                'tone' => 'professional',
                'system_prompt' => 'You are an escalation assistant. Convert raw issue notes into a concise technical summary with clear next actions.',
                'notes' => 'Draft template for engineering and product escalations.',
                'status' => 'draft',
                'is_default' => false,
            ],
        ])->each(fn (array $prompt) => PromptTemplate::query()->create($prompt));
    }
}
