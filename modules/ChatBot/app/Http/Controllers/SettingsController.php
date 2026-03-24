<?php

declare(strict_types=1);

namespace Modules\ChatBot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const array TOOL_KEYS = [
        'chatbot_tool_list',
        'chatbot_tool_read',
        'chatbot_tool_write',
        'chatbot_tool_apply_patch',
        'chatbot_tool_edit',
        'chatbot_tool_multiedit',
        'chatbot_tool_delete',
        'chatbot_tool_move',
        'chatbot_tool_grep',
        'chatbot_tool_glob',
        'chatbot_tool_bash',
        'chatbot_tool_lsp',
        'chatbot_tool_copy',
        'chatbot_tool_stats',
    ];

    /**
     * Show the settings page.
     */
    public function settings(Request $request): Response|RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_chatbot_settings'), 403);

        if (! $request->has('section')) {
            return to_route('app.chatbot.settings.index', ['section' => 'general']);
        }

        return Inertia::render('chatbot/settings/index', [
            'section' => $request->query('section', 'general'),
            'initialValues' => [
                'general' => [
                    'chatbot_system_prompt' => (string) setting('chatbot_system_prompt', 'You are a helpful, concise, and friendly AI assistant. Answer clearly and accurately.'),
                    'chatbot_chat_title' => (string) setting('chatbot_chat_title', 'AI Assistant'),
                    'chatbot_placeholder' => (string) setting('chatbot_placeholder', 'Ask me anything...'),
                    'chatbot_show_thinking' => filter_var((string) setting('chatbot_show_thinking', false), FILTER_VALIDATE_BOOLEAN),
                    'chatbot_max_tool_steps' => (int) setting('chatbot_max_tool_steps', 25),
                ],
                'provider' => [
                    'chatbot_provider' => (string) setting('chatbot_provider', ''),
                    'chatbot_model' => (string) setting('chatbot_model', ''),
                    'chatbot_api_key' => '',
                ],
                'tools' => collect(self::TOOL_KEYS)
                    ->mapWithKeys(fn (string $key): array => [
                        $key => filter_var((string) setting($key, $this->toolDefault($key)), FILTER_VALIDATE_BOOLEAN),
                    ])
                    ->all(),
            ],
            'providerOptions' => $this->providerOptions(),
            'providerRegistry' => $this->providerRegistryProps(),
            'toolGroups' => $this->toolGroups(),
        ]);
    }

    /**
     * Update general settings (enabled toggle, system prompt, appearance).
     */
    public function updateGeneral(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_chatbot_settings'), 403);

        $request->validate([
            'chatbot_system_prompt' => ['nullable', 'string', 'max:5000'],
            'chatbot_chat_title' => ['nullable', 'string', 'max:100'],
            'chatbot_placeholder' => ['nullable', 'string', 'max:200'],
            'chatbot_show_thinking' => ['nullable', 'boolean'],
            'chatbot_max_tool_steps' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $userId = Auth::id();

        $fields = [
            'chatbot_system_prompt' => [(string) $request->input('chatbot_system_prompt', ''), 'string'],
            'chatbot_chat_title' => [(string) $request->input('chatbot_chat_title', 'AI Assistant'), 'string'],
            'chatbot_placeholder' => [(string) $request->input('chatbot_placeholder', 'Ask me anything…'), 'string'],
            'chatbot_show_thinking' => [$request->boolean('chatbot_show_thinking') ? 'true' : 'false', 'boolean'],
            'chatbot_max_tool_steps' => [(string) $request->integer('chatbot_max_tool_steps', 25), 'integer'],
        ];

        foreach ($fields as $key => [$value, $type]) {
            Settings::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'type' => $type, 'updated_by' => $userId]
            );
        }

        settings_cache()->refresh();

        return to_route('app.chatbot.settings.index', ['section' => 'general'])
            ->with('success', 'General settings updated.');
    }

    /**
     * Update AI provider settings.
     */
    public function updateProvider(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_chatbot_settings'), 403);

        $request->validate([
            'chatbot_provider' => ['nullable', 'string', 'max:50'],
            'chatbot_model' => ['nullable', 'string', 'max:100'],
            'chatbot_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = Auth::id();

        $fields = [
            'chatbot_provider' => (string) $request->input('chatbot_provider', ''),
            'chatbot_model' => (string) $request->input('chatbot_model', ''),
        ];

        foreach ($fields as $key => $value) {
            Settings::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => 'string', 'updated_by' => $userId]
            );
        }

        // Only update API key if a new value was provided (blank = leave unchanged).
        // Use filled() because ConvertEmptyStringsToNull middleware turns '' into null,
        // which would make ($apiKey !== '') evaluate to true and clear the stored key.
        $apiKey = $request->input('chatbot_api_key');
        if (filled($apiKey)) {
            Settings::query()->updateOrCreate(
                ['key' => 'chatbot_api_key'],
                ['value' => Crypt::encryptString((string) $apiKey), 'type' => 'string', 'updated_by' => $userId]
            );
        }

        settings_cache()->refresh();

        return to_route('app.chatbot.settings.index', ['section' => 'provider'])
            ->with('success', 'Provider settings updated.');
    }

    /**
     * Update tool enablement settings.
     */
    public function updateTools(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_chatbot_settings'), 403);

        $rules = [];
        foreach (self::TOOL_KEYS as $key) {
            $rules[$key] = ['nullable', 'boolean'];
        }

        $request->validate($rules);

        $userId = Auth::id();

        foreach (self::TOOL_KEYS as $key) {
            Settings::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $request->boolean($key) ? 'true' : 'false', 'type' => 'boolean', 'updated_by' => $userId]
            );
        }

        settings_cache()->refresh();

        return to_route('app.chatbot.settings.index', ['section' => 'tools'])
            ->with('success', 'Tool settings updated.');
    }

    /**
     * Available AI provider options.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function providerOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Default (from ai.php config)'],
            ['value' => 'openai', 'label' => 'OpenAI'],
            ['value' => 'anthropic', 'label' => 'Anthropic (Claude)'],
            ['value' => 'gemini', 'label' => 'Google Gemini'],
            ['value' => 'azure', 'label' => 'Azure OpenAI'],
            ['value' => 'xai', 'label' => 'xAI (Grok)'],
            ['value' => 'openrouter', 'label' => 'OpenRouter'],
            ['value' => 'groq', 'label' => 'Groq'],
            ['value' => 'deepseek', 'label' => 'DeepSeek'],
            ['value' => 'mistral', 'label' => 'Mistral'],
            ['value' => 'cohere', 'label' => 'Cohere'],
            ['value' => 'ollama', 'label' => 'Ollama (local)'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function providerRegistryProps(): array
    {
        $isAiRegistryEnabled = function_exists('module_enabled') && module_enabled('AIRegistry');

        if ($isAiRegistryEnabled && Route::has('ai-registry.api.v1.providers.index')) {
            return [
                'providersUrl' => route('ai-registry.api.v1.providers.index'),
                'modelsBaseUrl' => route('ai-registry.api.v1.providers.models', ['providerSlug' => '__PROVIDER__']),
                'manageModelsUrl' => Route::has('ai-registry.models.index') ? route('ai-registry.models.index') : null,
                'createModelUrl' => Route::has('ai-registry.models.create') ? route('ai-registry.models.create') : null,
            ];
        }

        $platformUrl = rtrim((string) (config('agency.platform_api_url') ?: 'https://platform.astero.net.in'), '/');

        return [
            'providersUrl' => $platformUrl.'/api/ai-registry/v1/providers',
            'modelsBaseUrl' => $platformUrl.'/api/ai-registry/v1/providers/__PROVIDER__/models',
            'manageModelsUrl' => null,
            'createModelUrl' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolGroups(): array
    {
        return [
            [
                'title' => 'Reading & Search',
                'tools' => [
                    $this->toolDefinition('chatbot_tool_list', 'list', 'List', 'List files and directories as a rooted tree', 'Kilocode-style list using system ripgrep. Approval metadata and structured results are deferred for now.'),
                    $this->toolDefinition('chatbot_tool_read', 'read', 'Read', 'Read files or directories inside this Laravel project', 'Reads numbered file content or directory entries. Image/PDF attachment parity is deferred for now.'),
                    $this->toolDefinition('chatbot_tool_glob', 'glob', 'Glob', 'Find files by glob pattern using ripgrep', 'Kilocode-style glob search for filenames, capped at 100 results and sorted by modification time.'),
                    $this->toolDefinition('chatbot_tool_lsp', 'lsp', 'LSP', 'Code intelligence for PHP, JavaScript, and CSS', 'Kilocode-style lsp schema with strongest support for PHP and best-effort support for JavaScript and CSS.'),
                    $this->toolDefinition('chatbot_tool_stats', 'stats', 'Stats', 'Get aggregated workspace stats for a directory tree', 'Summarizes non-gitignored files with totals, size, text line count, and top file types.'),
                    $this->toolDefinition('chatbot_tool_grep', 'grep', 'Grep', 'Search file contents with regex using ripgrep', 'Regex-only content search using system ripgrep.'),
                ],
            ],
            [
                'title' => 'Writing & Editing',
                'tools' => [
                    $this->toolDefinition('chatbot_tool_write', 'write', 'Write', 'Write a file to the local filesystem', 'Read-before-write enforcement is deferred. PHP syntax diagnostics are appended when available.'),
                    $this->toolDefinition('chatbot_tool_apply_patch', 'apply_patch', 'Apply Patch', 'Apply stripped-down file-oriented patches across files', 'Uses Kilocode-style patch text with Begin/End markers and Add/Update/Delete headers.'),
                    $this->toolDefinition('chatbot_tool_edit', 'edit', 'Edit', 'Edit a file using exact string replacement', 'Exact-string replacement tool; read-before-edit enforcement and approval metadata are deferred.'),
                    $this->toolDefinition('chatbot_tool_multiedit', 'multiedit', 'MultiEdit', 'Perform multiple exact string replacements on one file atomically', 'Multiple sequential exact-string replacements on one file.'),
                ],
            ],
            [
                'title' => 'File & Directory Operations',
                'tools' => [
                    $this->toolDefinition('chatbot_tool_delete', 'delete', 'Delete', 'Delete files or directories with guardrails', 'Unified extension tool with automatic file/directory detection and protected top-level directory guardrails.'),
                    $this->toolDefinition('chatbot_tool_copy', 'copy', 'Copy', 'Copy files or directories with bounded recursion', 'Recursive directory copy is capped at 500 files with a 10s limit.'),
                    $this->toolDefinition('chatbot_tool_move', 'move', 'Move', 'Move or rename files or directories with guardrails', 'Supports single or bulk mode with protected top-level directory guardrails.'),
                ],
            ],
            [
                'title' => 'Command Execution',
                'tools' => [
                    $this->toolDefinition('chatbot_tool_bash', 'bash', 'Bash', 'Run one-shot bash commands with timeout support', 'Runs one-shot bash commands with bounded output. User approval is required before execution.'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolDefinition(string $key, string $name, string $label, string $description, string $help): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'label' => $label,
            'default' => $this->toolDefault($key),
            'description' => $description,
            'help' => $help,
        ];
    }

    private function toolDefault(string $key): bool
    {
        return $key !== 'chatbot_tool_multiedit';
    }
}
