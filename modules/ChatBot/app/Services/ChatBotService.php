<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Messages\Message;
use Modules\ChatBot\Ai\ChatBotAgent;
use Modules\ChatBot\Models\Conversation;
use Modules\ChatBot\Models\ConversationMessage;
use Modules\ChatBot\Models\ConversationMessageEvent;
use Throwable;

class ChatBotService
{
    /**
     * Get all conversations for the current user, ordered newest first.
     *
     * @return array<int, object>
     */
    public function listConversations(): array
    {
        return Conversation::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->get()
            ->all();
    }

    /**
     * Get the most recent conversation for the current user.
     */
    public function latestConversation(): ?object
    {
        return Conversation::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Get a single conversation by ID, ensuring it belongs to the current user.
     */
    public function getConversation(string $conversationId): ?object
    {
        return Conversation::query()
            ->where('id', $conversationId)
            ->where('user_id', Auth::id())
            ->first();
    }

    /**
     * Get messages for a given conversation.
     *
     * @return array<int, object>
     */
    public function getMessages(string $conversationId): array
    {
        return ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->get([
                'id',
                'role',
                'status',
                'finish_reason',
                'content',
                'tool_calls',
                'tool_results',
                'meta',
                'error_code',
                'error_message',
                'created_at',
            ])
            ->map(function (ConversationMessage $message): ?object {
                $meta = $this->decodeJsonObject($message->meta);
                $toolActivity = $this->buildToolActivity(
                    $message->tool_calls ?? '[]',
                    $message->tool_results ?? '{}',
                    $meta,
                );
                $reasoning = $this->extractReasoningContent($meta);

                if (! $this->messageShouldBeVisible($message, $toolActivity, $reasoning, $meta)) {
                    return null;
                }

                return (object) [
                    'id' => $message->id,
                    'role' => $message->role,
                    'status' => $message->status,
                    'finishReason' => $message->finish_reason,
                    'content' => $message->content,
                    'created_at' => $message->created_at?->toDateTimeString(),
                    'createdAt' => $message->created_at?->toISOString(),
                    'reasoning' => $reasoning,
                    'toolActivity' => $toolActivity,
                    'toolOrder' => $this->extractToolCallOrder($meta),
                    'maxStepsReached' => (bool) ($meta['max_steps_reached'] ?? false),
                    'stepsUsed' => (int) ($meta['steps_used'] ?? 0),
                    'debugInfo' => $meta['debug_info'] ?? null,
                    'error' => $message->status === 'error' ? $message->error_message : null,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Persist post-stream metadata (reasoning, tool call ordering, and tool activity)
     * for the latest assistant message.
     *
     * Laravel AI's framework only persists tool_calls/tool_results columns for the
     * first assistant message in a conversation. For subsequent messages, we store
     * the tool data in the meta column as a reliable fallback.
     *
     * @param  array<int, array{tool_id: string, text_offset: int}>  $toolCallOrder
     * @param  array<int, array{id: string, name: string, arguments: mixed}>  $toolCalls
     * @param  array<int, array{id: string, name: string, result: string, successful: bool, error: string|null}>  $toolResults
     */
    public function storeAssistantStreamMeta(
        string $conversationId,
        string $reasoning,
        array $toolCallOrder = [],
        array $toolCalls = [],
        array $toolResults = [],
        bool $maxStepsReached = false,
        int $stepsUsed = 0,
        array $debugInfo = [],
    ): void {
        if ($reasoning === '' && $toolCallOrder === [] && $toolCalls === [] && ! $maxStepsReached && $debugInfo === []) {
            return;
        }

        $assistantMessage = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderByDesc('created_at')
            ->first(['id', 'meta']);

        if (! $assistantMessage) {
            return;
        }

        $meta = $this->decodeJsonObject($assistantMessage->meta);

        if ($reasoning !== '') {
            $meta['reasoning'] = [
                'content' => $reasoning,
            ];
        }

        if ($toolCallOrder !== []) {
            $meta['tool_call_order'] = $toolCallOrder;
        }

        // Store captured tool activity in meta as a fallback for when the
        // framework's tool_calls/tool_results columns are empty (multi-exchange bug).
        if ($toolCalls !== []) {
            $meta['captured_tool_calls'] = $toolCalls;
            $meta['captured_tool_results'] = $toolResults;
        }

        if ($maxStepsReached) {
            $meta['max_steps_reached'] = true;
            $meta['steps_used'] = $stepsUsed;
        }

        if ($debugInfo !== []) {
            $meta['debug_info'] = $debugInfo;
        }

        $assistantMessage->update([
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Soft-delete a conversation and all its messages.
     */
    public function deleteConversation(string $conversationId): void
    {
        ConversationMessageEvent::query()
            ->where('conversation_id', $conversationId)
            ->delete();

        ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->delete();

        Conversation::query()
            ->where('id', $conversationId)
            ->where('user_id', Auth::id())
            ->delete();
    }

    /**
     * Create an agent, optionally continuing an existing conversation.
     * Applies any API key stored in settings to the provider config at runtime.
     *
     * @param  array<int, Message|array{role: string, content: string|null}|object>  $history
     */
    public function makeAgent(?string $conversationId = null, array $history = []): ChatBotAgent
    {
        $this->applyStoredApiKey();

        $agent = new ChatBotAgent;
        $user = Auth::user();

        if ($conversationId) {
            return $agent->continue($conversationId, as: $user, messages: $history);
        }

        return $agent->forUser($user);
    }

    public function createConversation(?string $title = null): Conversation
    {
        $conversationId = (string) Str::uuid7();

        return Conversation::query()->create([
            'id' => $conversationId,
            'user_id' => Auth::id(),
            'title' => $title ?: $this->placeholderConversationTitle($conversationId),
            'metadata' => [
                'title_source' => 'placeholder',
            ],
        ]);
    }

    public function ensureImmediateConversationTitle(string $conversationId, string $firstUserMessage): ?string
    {
        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->first();

        if (! $conversation) {
            return null;
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $titleSource = (string) ($metadata['title_source'] ?? 'placeholder');

        if ($titleSource !== 'placeholder') {
            return $conversation->title;
        }

        $title = $this->generateAiConversationTitle($firstUserMessage);
        if ($title === '') {
            $title = $this->generateConversationTitle($firstUserMessage);
        }

        if ($title === '') {
            return $conversation->title;
        }

        $metadata['title_source'] = 'generated_ai';

        $conversation->update([
            'title' => $title,
            'metadata' => $metadata,
        ]);

        return $title;
    }

    /**
     * @return array<int, Message>
     */
    public function loadConversationHistory(string $conversationId): array
    {
        return ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->where('content', '!=', '')
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn (ConversationMessage $message): Message => new Message($message->role, $message->content))
            ->all();
    }

    public function createUserMessage(string $conversationId, string $content, string $agentClass): ConversationMessage
    {
        return ConversationMessage::query()->create([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => Auth::id(),
            'agent' => $agentClass,
            'role' => 'user',
            'status' => 'completed',
            'content' => $content,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function createAssistantDraft(string $conversationId, string $agentClass): ConversationMessage
    {
        return ConversationMessage::query()->create([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'user_id' => Auth::id(),
            'agent' => $agentClass,
            'role' => 'assistant',
            'status' => 'streaming',
            'content' => '',
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'started_at' => now(),
        ]);
    }

    public function getConversationMessage(string $messageId, string $conversationId): ?ConversationMessage
    {
        return ConversationMessage::query()
            ->where('id', $messageId)
            ->where('conversation_id', $conversationId)
            ->first();
    }

    public function markAssistantMessageStreaming(string $messageId, string $status = 'streaming'): void
    {
        $message = ConversationMessage::query()->find($messageId);

        if (! $message) {
            return;
        }

        $message->update([
            'status' => $status,
            'finish_reason' => null,
            'completed_at' => null,
            'interrupted_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    public function recordApprovedToolExecution(
        string $messageId,
        string $toolId,
        string $toolName,
        array $arguments,
        string $result,
        bool $successful,
        ?string $error = null,
    ): void {
        $message = ConversationMessage::query()->find($messageId);

        if (! $message) {
            return;
        }

        $toolCalls = $this->decodeJsonArray($message->tool_calls);
        $toolResults = $this->decodeJsonArray($message->tool_results);

        $callUpdated = false;
        foreach ($toolCalls as &$call) {
            if (! is_array($call) || (string) ($call['id'] ?? '') !== $toolId) {
                continue;
            }

            $call['name'] = $toolName;
            $call['arguments'] = $arguments;
            $callUpdated = true;
            break;
        }
        unset($call);

        if (! $callUpdated) {
            $toolCalls[] = [
                'id' => $toolId,
                'name' => $toolName,
                'arguments' => $arguments,
            ];
        }

        $resultUpdated = false;
        foreach ($toolResults as &$toolResult) {
            if (! is_array($toolResult) || (string) ($toolResult['id'] ?? '') !== $toolId) {
                continue;
            }

            $toolResult['name'] = $toolName;
            $toolResult['result'] = $result;
            $toolResult['successful'] = $successful;
            $toolResult['error'] = $error;
            $resultUpdated = true;
            break;
        }
        unset($toolResult);

        if (! $resultUpdated) {
            $toolResults[] = [
                'id' => $toolId,
                'name' => $toolName,
                'result' => $result,
                'successful' => $successful,
                'error' => $error,
            ];
        }

        $message->update([
            'tool_calls' => json_encode($toolCalls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tool_results' => json_encode($toolResults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => $successful ? 'resuming' : 'error',
            'finish_reason' => $successful ? null : 'error',
            'completed_at' => $successful ? null : now(),
            'interrupted_at' => $successful ? null : now(),
            'error_code' => $successful ? null : 'tool_execution_failed',
            'error_message' => $successful ? null : $error,
        ]);
    }

    /**
     * @param  array<string, mixed>  $permission
     */
    public function recordDeniedToolDecision(
        string $messageId,
        string $toolId,
        string $toolName,
        array $arguments,
        array $permission,
    ): void {
        $message = ConversationMessage::query()->find($messageId);

        if (! $message) {
            return;
        }

        $toolCalls = $this->decodeJsonArray($message->tool_calls);
        $toolResults = $this->decodeJsonArray($message->tool_results);

        $callUpdated = false;
        foreach ($toolCalls as &$call) {
            if (! is_array($call) || (string) ($call['id'] ?? '') !== $toolId) {
                continue;
            }

            $call['name'] = $toolName;
            $call['arguments'] = $arguments;
            $callUpdated = true;
            break;
        }
        unset($call);

        if (! $callUpdated) {
            $toolCalls[] = [
                'id' => $toolId,
                'name' => $toolName,
                'arguments' => $arguments,
            ];
        }

        $permissionMarker = 'The user denied permission for this tool call.'
            ."\n<tool_permission>"
            .json_encode($permission, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            .'</tool_permission>';

        $resultUpdated = false;
        foreach ($toolResults as &$toolResult) {
            if (! is_array($toolResult) || (string) ($toolResult['id'] ?? '') !== $toolId) {
                continue;
            }

            $toolResult['name'] = $toolName;
            $toolResult['result'] = '';
            $toolResult['successful'] = false;
            $toolResult['error'] = $permissionMarker;
            $resultUpdated = true;
            break;
        }
        unset($toolResult);

        if (! $resultUpdated) {
            $toolResults[] = [
                'id' => $toolId,
                'name' => $toolName,
                'result' => '',
                'successful' => false,
                'error' => $permissionMarker,
            ];
        }

        $message->update([
            'tool_calls' => json_encode($toolCalls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tool_results' => json_encode($toolResults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'resuming',
            'finish_reason' => null,
            'completed_at' => null,
            'interrupted_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    /**
     * @param  array<int, array{tool_id: string, text_offset: int}>  $toolCallOrder
     * @param  array<int, array{id: string, name: string, arguments: mixed}>  $toolCalls
     * @param  array<int, array{id: string, name: string, result: string, successful: bool, error: string|null}>  $toolResults
     * @param  array<string, mixed>  $usage
     * @param  array<string, mixed>  $debugInfo
     */
    public function persistAssistantMessageState(
        string $messageId,
        string $content,
        string $reasoning,
        array $toolCallOrder = [],
        array $toolCalls = [],
        array $toolResults = [],
        string $status = 'completed',
        ?string $finishReason = null,
        bool $maxStepsReached = false,
        int $stepsUsed = 0,
        array $debugInfo = [],
        array $usage = [],
        ?string $errorCode = null,
        ?string $errorMessage = null,
        array $capturedToolActivity = [],
    ): void {
        $message = ConversationMessage::query()->find($messageId);

        if (! $message) {
            return;
        }

        $meta = $this->decodeJsonObject($message->meta);
        $mergedToolCalls = $this->mergeToolRecords(
            $this->decodeJsonArray($message->tool_calls),
            $toolCalls,
        );
        $mergedToolResults = $this->mergeToolRecords(
            $this->decodeJsonArray($message->tool_results),
            $toolResults,
        );

        if ($reasoning !== '') {
            $meta['reasoning'] = [
                'content' => $reasoning,
            ];
        }

        if ($toolCallOrder !== []) {
            $meta['tool_call_order'] = $toolCallOrder;
        }

        if ($maxStepsReached) {
            $meta['max_steps_reached'] = true;
            $meta['steps_used'] = $stepsUsed;
        }

        if ($debugInfo !== []) {
            $meta['debug_info'] = $debugInfo;
        }

        if ($capturedToolActivity !== []) {
            $meta['captured_tool_activity'] = $capturedToolActivity;
        }

        $message->update([
            'content' => $content,
            'status' => $status,
            'finish_reason' => $finishReason,
            'tool_calls' => json_encode($mergedToolCalls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tool_results' => json_encode($mergedToolResults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'usage' => json_encode($usage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'completed_at' => in_array($status, ['completed', 'error', 'stopped', 'approval_required', 'max_steps'], true) ? now() : null,
            'interrupted_at' => in_array($status, ['error', 'stopped', 'approval_required', 'max_steps'], true) ? now() : null,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);

        if (in_array($status, ['completed', 'approval_required', 'max_steps'], true)) {
            $this->refineConversationTitleFromHistory($message->conversation_id);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolActivity
     * @param  array<int, array{toolId: string, textOffset: int}>  $toolOrder
     */
    public function persistStoppedAssistantSnapshot(
        string $messageId,
        string $content,
        string $reasoning,
        array $toolActivity = [],
        array $toolOrder = [],
        bool $maxStepsReached = false,
        int $stepsUsed = 0,
    ): void {
        [$toolCalls, $toolResults] = $this->toolRecordsFromSnapshot($toolActivity);

        $normalizedOrder = array_values(array_map(
            fn (array $entry): array => [
                'tool_id' => (string) ($entry['toolId'] ?? ''),
                'text_offset' => (int) ($entry['textOffset'] ?? 0),
            ],
            array_filter($toolOrder, fn (mixed $entry): bool => is_array($entry) && isset($entry['toolId']))
        ));

        $this->persistAssistantMessageState(
            $messageId,
            $content !== '' ? $content : '(Stopped)',
            $reasoning,
            $normalizedOrder,
            $toolCalls,
            $toolResults,
            status: 'stopped',
            finishReason: 'stopped',
            maxStepsReached: $maxStepsReached,
            stepsUsed: $stepsUsed,
            errorCode: 'stopped_by_user',
            errorMessage: 'The user stopped this assistant turn before it completed.',
            capturedToolActivity: $toolActivity,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordMessageEvent(string $conversationId, string $messageId, string $type, array $payload = []): ConversationMessageEvent
    {
        return ConversationMessageEvent::query()->create([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    /**
     * If a provider + API key are stored in settings, override the provider's config key at runtime
     * so the stored key takes effect without requiring a change to .env.
     */
    private function applyStoredApiKey(): void
    {
        $provider = $this->provider();
        $apiKey = $this->storedApiKey();

        if ($provider && $apiKey !== '') {
            config(["ai.providers.{$provider}.key" => $apiKey]);
        }
    }

    private function storedApiKey(): string
    {
        $apiKey = (string) setting('chatbot_api_key', '');

        if ($apiKey === '') {
            return '';
        }

        try {
            return Crypt::decryptString($apiKey);
        } catch (DecryptException) {
            return $apiKey;
        }
    }

    /**
     * Get the configured AI provider for this module (from settings).
     * Returns null to use the laravel/ai default.
     */
    public function provider(): ?string
    {
        $provider = setting('chatbot_provider', '');

        return $provider !== '' ? $provider : null;
    }

    /**
     * Get the configured model override (from settings).
     * Returns null to use the provider default.
     */
    public function model(): ?string
    {
        $model = setting('chatbot_model', '');

        return $model !== '' ? $model : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function placeholderConversationTitle(string $conversationId): string
    {
        return 'New Conversation #'.strtoupper(substr(str_replace('-', '', $conversationId), 0, 8));
    }

    public function interruptionContextNote(string $conversationId): ?string
    {
        $latestAssistant = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderByDesc('created_at')
            ->first([
                'status',
                'finish_reason',
                'error_message',
            ]);

        if (! $latestAssistant) {
            return null;
        }

        $status = (string) ($latestAssistant->status ?? '');
        $errorMessage = trim((string) ($latestAssistant->error_message ?? ''));

        $context = match ($status) {
            'stopped' => implode("\n", [
                'Conversation runtime note:',
                'The previous assistant turn was stopped by the user before it completed.',
                'Treat the next user message as a continuation after a user-interrupted answer.',
                'Do not assume prior tool calls or prior partial reasoning finished successfully unless a saved tool result already proves that.',
            ]),
            'error' => implode("\n", array_filter([
                'Conversation runtime note:',
                'The previous assistant turn ended with an error before completion.',
                'Treat the next user message as a continuation after a failed assistant turn.',
                'Do not assume prior tool calls or partial output completed successfully.',
                $errorMessage !== '' ? 'Last error: '.$errorMessage : null,
            ])),
            'approval_required' => implode("\n", [
                'Conversation runtime note:',
                'The previous assistant turn paused because a tool call required user approval.',
                'Do not assume that paused tool call executed unless a saved tool result already proves that.',
                'Treat the next user message as continuing after an approval-paused turn.',
            ]),
            'max_steps' => implode("\n", [
                'Conversation runtime note:',
                'The previous assistant turn reached the maximum tool-step limit before it fully completed.',
                'Treat the next user message as a continuation after an incomplete assistant turn.',
            ]),
            default => '',
        };

        if ($context === '') {
            return null;
        }

        return $context;
    }

    private function refineConversationTitleFromHistory(string $conversationId): void
    {
        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->first();

        if (! $conversation) {
            return;
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $titleSource = (string) ($metadata['title_source'] ?? 'placeholder');

        if ($titleSource !== 'placeholder') {
            return;
        }

        $firstUserMessage = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->where('content', '!=', '')
            ->orderBy('created_at')
            ->value('content');

        if (! is_string($firstUserMessage) || trim($firstUserMessage) === '') {
            return;
        }

        $generatedTitle = $this->generateConversationTitle($firstUserMessage);

        if ($generatedTitle === '') {
            return;
        }

        if ($conversation->title === $generatedTitle && $titleSource === 'generated') {
            return;
        }

        $metadata['title_source'] = 'generated';

        $conversation->update([
            'title' => $generatedTitle,
            'metadata' => $metadata,
        ]);
    }

    private function generateConversationTitle(string $message): string
    {
        $title = trim($message);

        $title = preg_replace('/[`*_#>\[\]\(\)]/u', '', $title) ?? $title;
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
        $title = trim($title, " \t\n\r\0\x0B.,:;!?-");

        if ($title === '') {
            return '';
        }

        if (mb_strlen($title) > 72) {
            $title = rtrim(mb_substr($title, 0, 69)).'...';
        }

        return $title;
    }

    private function generateAiConversationTitle(string $message): string
    {
        $this->applyStoredApiKey();

        $prompt = implode("\n", [
            'Generate a concise conversation title for the following first user message.',
            'Rules:',
            '- Return only the title text.',
            '- Use 3 to 8 words.',
            '- No quotes.',
            '- No trailing punctuation.',
            '- Capture the user intent clearly.',
            '',
            'First user message:',
            $message,
        ]);

        try {
            $response = (new AnonymousAgent(
                instructions: 'You create short, clear chat conversation titles.',
                messages: [],
                tools: [],
            ))->prompt(
                prompt: $prompt,
                provider: $this->provider(),
                model: $this->model(),
                timeout: 8000,
            );

            return $this->normalizeGeneratedTitle((string) $response->text);
        } catch (Throwable $e) {
            if (config('app.debug')) {
                Log::debug('[ChatBot] Conversation title generation failed', [
                    'error' => $e->getMessage(),
                ]);
            }

            return '';
        }
    }

    private function normalizeGeneratedTitle(string $title): string
    {
        $normalized = trim($title);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized, " \t\n\r\0\x0B\"'`.,:;!?-");

        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) > 72) {
            $normalized = rtrim(mb_substr($normalized, 0, 69)).'...';
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolActivity
     * @param  array<string, mixed>  $meta
     */
    private function messageShouldBeVisible(
        ConversationMessage $message,
        array $toolActivity,
        string $reasoning,
        array $meta,
    ): bool {
        if ($message->role === 'user') {
            return trim((string) $message->content) !== '';
        }

        if (trim((string) $message->content) !== '') {
            return true;
        }

        if ($toolActivity !== []) {
            return true;
        }

        if ($reasoning !== '') {
            return true;
        }

        if (($meta['max_steps_reached'] ?? false) === true) {
            return true;
        }

        if ($message->status !== 'completed') {
            return true;
        }

        return $message->error_message !== null && $message->error_message !== '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    private function mergeToolRecords(array $existing, array $incoming): array
    {
        if ($existing === []) {
            return array_values($incoming);
        }

        if ($incoming === []) {
            return array_values($existing);
        }

        $merged = [];

        foreach ($existing as $record) {
            if (! is_array($record)) {
                continue;
            }

            $id = (string) ($record['id'] ?? '');

            if ($id === '') {
                $merged[] = $record;

                continue;
            }

            $merged[$id] = $record;
        }

        foreach ($incoming as $record) {
            if (! is_array($record)) {
                continue;
            }

            $id = (string) ($record['id'] ?? '');

            if ($id === '') {
                $merged[] = $record;

                continue;
            }

            $merged[$id] = array_merge($merged[$id] ?? [], $record);
        }

        return array_values($merged);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function extractReasoningContent(array $meta): string
    {
        $reasoning = $meta['reasoning'] ?? null;

        if (is_string($reasoning)) {
            return $reasoning;
        }

        if (is_array($reasoning) && is_string($reasoning['content'] ?? null)) {
            return $reasoning['content'];
        }

        return '';
    }

    /**
     * Build a toolActivity array from stored tool_calls and tool_results JSON columns.
     * The frontend normalizeMessage() consumes this to reconstruct tool call cards.
     *
     * Falls back to captured tool data in meta when the framework's tool_calls
     * column is empty (happens for subsequent messages in multi-exchange conversations).
     *
     * @param  array<string, mixed>  $meta
     * @return array<int, array{toolId: string, name: string, status: string, arguments: string, result: string|null, fullResult: string, error: string|null}>
     */
    private function buildToolActivity(string $toolCallsJson, string $toolResultsJson, array $meta = []): array
    {
        if (isset($meta['captured_tool_activity']) && is_array($meta['captured_tool_activity']) && $meta['captured_tool_activity'] !== []) {
            return $this->buildToolActivityFromSnapshot($meta['captured_tool_activity']);
        }

        $toolCalls = $this->decodeJsonArray($toolCallsJson);
        $toolResultsRaw = $this->decodeJsonObject($toolResultsJson);

        // Fallback: use captured tool data from meta when framework columns are empty
        if ($toolCalls === [] && isset($meta['captured_tool_calls']) && is_array($meta['captured_tool_calls'])) {
            return $this->buildToolActivityFromMeta($meta);
        }

        if ($toolCalls === []) {
            return [];
        }

        // Build a lookup of results by tool call ID
        $resultsById = [];
        foreach ($toolResultsRaw as $resultEntry) {
            if (is_array($resultEntry) && isset($resultEntry['id'])) {
                $resultsById[$resultEntry['id']] = $resultEntry;
            }
        }

        $activity = [];
        foreach ($toolCalls as $call) {
            if (! is_array($call) || ! isset($call['id'])) {
                continue;
            }

            $toolId = (string) $call['id'];
            $result = $resultsById[$toolId] ?? null;
            $resultText = is_array($result) ? (string) ($result['result'] ?? '') : '';
            $hasResult = $resultText !== '';
            $errorText = is_array($result) ? ($result['error'] ?? null) : null;
            $failed = $this->toolResultIndicatesFailure($resultText, $errorText, $hasResult);
            $toolError = $failed ? ($this->extractToolErrorMessage($resultText, $errorText) ?? 'No result recorded.') : null;
            $permission = $this->toolPermissionState($toolError);

            $arguments = $call['arguments'] ?? null;
            $argumentsStr = is_array($arguments) || is_object($arguments)
                ? json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                : (string) ($arguments ?? '');

            $activity[] = [
                'toolId' => $toolId,
                'name' => (string) ($call['name'] ?? 'unknown'),
                'status' => $permission['uiStatus'] ?? ($failed ? 'failed' : 'completed'),
                'arguments' => $argumentsStr,
                'result' => $failed || ! $hasResult ? null : $this->summarizeResult($resultText),
                'fullResult' => $failed ? '' : $resultText,
                'error' => $toolError,
                'permission' => $permission,
            ];
        }

        return $activity;
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolActivity
     * @return array<int, array<string, mixed>>
     */
    private function buildToolActivityFromSnapshot(array $toolActivity): array
    {
        $activity = [];

        foreach ($toolActivity as $item) {
            if (! is_array($item) || ! isset($item['toolId'])) {
                continue;
            }

            $activity[] = [
                'toolId' => (string) ($item['toolId'] ?? ''),
                'name' => (string) ($item['name'] ?? 'unknown'),
                'status' => (string) ($item['status'] ?? 'completed'),
                'arguments' => (string) ($item['arguments'] ?? ''),
                'result' => array_key_exists('result', $item) ? ($item['result'] !== null ? (string) $item['result'] : null) : null,
                'fullResult' => (string) ($item['fullResult'] ?? ''),
                'error' => array_key_exists('error', $item) ? ($item['error'] !== null ? (string) $item['error'] : null) : null,
                'permission' => isset($item['permission']) && is_array($item['permission']) ? $item['permission'] : null,
            ];
        }

        return $activity;
    }

    /**
     * Build tool activity from captured data stored in meta (fallback path).
     *
     * @param  array<string, mixed>  $meta
     * @return array<int, array{toolId: string, name: string, status: string, arguments: string, result: string|null, fullResult: string, error: string|null}>
     */
    private function buildToolActivityFromMeta(array $meta): array
    {
        $calls = $meta['captured_tool_calls'] ?? [];
        $results = $meta['captured_tool_results'] ?? [];

        if (! is_array($calls) || $calls === []) {
            return [];
        }

        // Build a lookup of results by tool call ID
        $resultsById = [];
        foreach ($results as $resultEntry) {
            if (is_array($resultEntry) && isset($resultEntry['id'])) {
                $resultsById[$resultEntry['id']] = $resultEntry;
            }
        }

        $activity = [];
        foreach ($calls as $call) {
            if (! is_array($call) || ! isset($call['id'])) {
                continue;
            }

            $toolId = (string) $call['id'];
            $result = $resultsById[$toolId] ?? null;

            $successful = is_array($result) && ($result['successful'] ?? false);
            $resultText = is_array($result) ? (string) ($result['result'] ?? '') : '';
            $errorText = is_array($result) ? ($result['error'] ?? null) : null;
            $failed = ! $successful || $this->toolResultIndicatesFailure($resultText, $errorText, $resultText !== '');
            $toolError = $failed ? ($this->extractToolErrorMessage($resultText, $errorText) ?? 'No result recorded.') : null;
            $permission = $this->toolPermissionState($toolError);

            $arguments = $call['arguments'] ?? null;
            $argumentsStr = is_array($arguments) || is_object($arguments)
                ? json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                : (string) ($arguments ?? '');

            $activity[] = [
                'toolId' => $toolId,
                'name' => (string) ($call['name'] ?? 'unknown'),
                'status' => $permission['uiStatus'] ?? ($failed ? 'failed' : 'completed'),
                'arguments' => $argumentsStr,
                'result' => $failed ? null : $this->summarizeResult($resultText),
                'fullResult' => $failed ? '' : $resultText,
                'error' => $toolError,
                'permission' => $permission,
            ];
        }

        return $activity;
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolActivity
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function toolRecordsFromSnapshot(array $toolActivity): array
    {
        $toolCalls = [];
        $toolResults = [];

        foreach ($toolActivity as $item) {
            if (! is_array($item) || ! isset($item['toolId'])) {
                continue;
            }

            $toolId = (string) ($item['toolId'] ?? '');
            $toolName = (string) ($item['name'] ?? 'unknown');
            $arguments = $this->decodeJsonObject((string) ($item['arguments'] ?? ''));
            $status = (string) ($item['status'] ?? 'completed');
            $permission = isset($item['permission']) && is_array($item['permission']) ? $item['permission'] : null;

            $toolCalls[] = [
                'id' => $toolId,
                'name' => $toolName,
                'arguments' => $arguments !== [] ? $arguments : (string) ($item['arguments'] ?? ''),
            ];

            $result = (string) ($item['fullResult'] ?? '');
            $error = $item['error'] ?? null;

            if ($status === 'approval' && $permission !== null) {
                $error = 'User approval is required before this tool can run.'
                    ."\n<tool_permission>"
                    .json_encode($permission, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    .'</tool_permission>';
            }

            if ($status === 'denied' && $permission !== null) {
                $error = 'The user denied permission for this tool call.'
                    ."\n<tool_permission>"
                    .json_encode($permission, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    .'</tool_permission>';
            }

            if (in_array($status, ['running', 'blocked'], true) && $error === null) {
                $error = $status === 'running'
                    ? 'This tool call was in progress when the assistant turn was stopped.'
                    : 'This tool call had not started before the assistant turn was stopped.';
            }

            $toolResults[] = [
                'id' => $toolId,
                'name' => $toolName,
                'result' => $result,
                'successful' => $status === 'completed',
                'error' => $error,
            ];
        }

        return [$toolCalls, $toolResults];
    }

    /**
     * Summarize a tool result string for the compact card display.
     */
    private function summarizeResult(string $result): string
    {
        $firstLine = strtok($result, "\n") ?: $result;

        return mb_strlen($firstLine) > 80
            ? mb_substr($firstLine, 0, 77).'…'
            : $firstLine;
    }

    private function toolResultIndicatesFailure(string $resultText, mixed $errorText, bool $hasResult): bool
    {
        if (is_string($errorText) && trim($errorText) !== '') {
            return true;
        }

        if (! $hasResult) {
            return true;
        }

        return preg_match('/^Error\b[^\n]*:/u', ltrim($resultText)) === 1;
    }

    private function extractToolErrorMessage(string $resultText, mixed $errorText): ?string
    {
        if (is_string($errorText) && trim($errorText) !== '') {
            return $errorText;
        }

        $trimmed = trim($resultText);

        return preg_match('/^Error\b[^\n]*:/u', $trimmed) === 1
            ? $trimmed
            : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toolPermissionState(?string $toolError): ?array
    {
        $permission = app(ToolPermissionService::class)->resolvePayloadFromText($toolError);

        if ($permission === null) {
            return null;
        }

        $permission['uiStatus'] = match ($permission['status'] ?? 'pending') {
            'approved' => 'approved',
            'denied' => 'denied',
            default => 'approval',
        };

        return $permission;
    }

    /**
     * Extract the tool call ordering from message meta.
     * Each entry has tool_id + text_offset (character position in content where the tool was called).
     *
     * @param  array<string, mixed>  $meta
     * @return array<int, array{toolId: string, textOffset: int}>
     */
    private function extractToolCallOrder(array $meta): array
    {
        $order = $meta['tool_call_order'] ?? null;

        if (! is_array($order)) {
            return [];
        }

        return array_values(array_map(fn (array $entry): array => [
            'toolId' => (string) ($entry['tool_id'] ?? ''),
            'textOffset' => (int) ($entry['text_offset'] ?? 0),
        ], array_filter($order, fn (mixed $entry): bool => is_array($entry) && isset($entry['tool_id']))));
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
