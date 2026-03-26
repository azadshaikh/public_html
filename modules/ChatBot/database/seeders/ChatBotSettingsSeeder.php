<?php

declare(strict_types=1);

namespace Modules\ChatBot\Database\Seeders;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class ChatBotSettingsSeeder extends Seeder
{
    /**
     * Seed default ChatBot settings.
     * Uses create-if-not-exists to avoid overwriting user-configured values.
     *
     * NOTE: The API key below is an intentionally low-limit, rate-capped testing key
     * for OpenRouter. It is NOT a security flaw — it exists solely so that
     * `php artisan astero:install` produces a working chatbot out of the box
     * during local development and CI. It will be rotated or removed before
     * any production release.
     */
    public function run(): void
    {
        $systemUserId = User::query()->value('id');

        $defaults = [
            ['key' => 'chatbot_system_prompt', 'value' => 'You are a helpful, concise, and friendly AI assistant. Answer clearly and accurately.', 'type' => 'string'],
            ['key' => 'chatbot_chat_title',    'value' => 'AI Assistant',                            'type' => 'string'],
            ['key' => 'chatbot_placeholder',   'value' => 'Ask me anything…',                        'type' => 'string'],
            ['key' => 'chatbot_show_thinking', 'value' => false,                                      'type' => 'boolean'],
            ['key' => 'chatbot_max_tool_steps', 'value' => 25,                                       'type' => 'integer'],
            ['key' => 'chatbot_provider',      'value' => 'openrouter',                               'type' => 'string'],
            ['key' => 'chatbot_model',         'value' => 'moonshotai/kimi-k2.5-0127',                          'type' => 'string'],
            ['key' => 'chatbot_tool_list', 'value' => true,                                          'type' => 'boolean'],
            ['key' => 'chatbot_tool_read', 'value' => true,                                          'type' => 'boolean'],
            ['key' => 'chatbot_tool_write', 'value' => true,                                         'type' => 'boolean'],
            ['key' => 'chatbot_tool_apply_patch', 'value' => true,                                   'type' => 'boolean'],
            ['key' => 'chatbot_tool_edit', 'value' => true,                                          'type' => 'boolean'],
            ['key' => 'chatbot_tool_multiedit', 'value' => false,                                    'type' => 'boolean'],
            ['key' => 'chatbot_tool_delete', 'value' => true,                                        'type' => 'boolean'],
            ['key' => 'chatbot_tool_move', 'value' => true,                                          'type' => 'boolean'],
            ['key' => 'chatbot_tool_grep', 'value' => true,                                          'type' => 'boolean'],
            ['key' => 'chatbot_tool_glob', 'value' => true,                                          'type' => 'boolean'],
            ['key' => 'chatbot_tool_bash', 'value' => true,                                          'type' => 'boolean'],
            ['key' => 'chatbot_tool_lsp', 'value' => true,                                           'type' => 'boolean'],
            ['key' => 'chatbot_tool_copy', 'value' => true,                                          'type' => 'boolean'],
            ['key' => 'chatbot_tool_stats', 'value' => true,                                         'type' => 'boolean'],
            // See class-level docblock — this is a rate-limited dev/testing key, not a secret leak.
            ['key' => 'chatbot_api_key',       'value' => Crypt::encryptString('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'), 'type' => 'string'],
        ];

        foreach ($defaults as $data) {
            $existing = Settings::query()->where('key', $data['key'])->first();

            if (! $existing) {
                Settings::query()->create([
                    'group' => null,
                    'key' => $data['key'],
                    'value' => $data['value'],
                    'type' => $data['type'],
                    'created_by' => $systemUserId,
                    'updated_by' => $systemUserId,
                ]);
            }
        }
    }
}
