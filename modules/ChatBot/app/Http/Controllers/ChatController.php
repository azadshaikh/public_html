<?php

declare(strict_types=1);

namespace Modules\ChatBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\Error as StreamError;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Modules\ChatBot\Ai\ChatBotAgent;
use Modules\ChatBot\Services\ChatBotService;
use Modules\ChatBot\Services\ToolPermissionService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatBotService $service,
        private readonly ToolPermissionService $permissions,
    ) {}

    /**
     * Show the chat — auto-resumes the most recent conversation.
     * Visiting /admin/chatbot always lands on the last active thread.
     */
    public function index(): Response|RedirectResponse
    {
        abort_unless(Auth::user()->can('use_chatbot'), 403);

        $latest = $this->service->latestConversation();

        if ($latest) {
            return redirect()->route('app.chatbot.show', $latest->id);
        }

        return $this->renderChat();
    }

    /**
     * Start a blank new chat (explicit action from the "New Chat" button).
     */
    public function newChat(): Response
    {
        abort_unless(Auth::user()->can('use_chatbot'), 403);

        return $this->renderChat();
    }

    /**
     * Show a specific conversation.
     */
    public function show(string $conversationId): Response|RedirectResponse
    {
        abort_unless(Auth::user()->can('use_chatbot'), 403);

        $conversation = $this->service->getConversation($conversationId);

        if (! $conversation) {
            return redirect()->route('app.chatbot.index')
                ->with('flash_message', ['type' => 'warning', 'message' => 'Conversation not found.']);
        }

        $messages = $this->service->getMessages($conversationId);

        return $this->renderChat($conversation, $messages);
    }

    /**
     * Build the chat view with conversations sidebar + active state.
     *
     * @param  array<int, object>  $messages
     */
    private function renderChat(?object $activeConversation = null, array $messages = []): Response
    {
        return Inertia::render('chatbot/index', [
            'conversations' => $this->serializeConversations($this->service->listConversations()),
            'activeConversation' => $activeConversation ? $this->serializeConversation($activeConversation) : null,
            'messages' => $this->serializeMessages($messages),
            'settings' => [
                'chatTitle' => (string) setting('chatbot_chat_title', 'AI Assistant'),
                'placeholder' => (string) setting('chatbot_placeholder', 'Ask me anything...'),
                'showThinking' => filter_var((string) setting('chatbot_show_thinking', false), FILTER_VALIDATE_BOOLEAN),
                'provider' => (string) setting('chatbot_provider', 'AI'),
                'model' => (string) setting('chatbot_model', ''),
                'debugMode' => (bool) config('app.debug'),
            ],
        ]);
    }

    /**
     * @param  array<int, object>  $conversations
     * @return array<int, array<string, mixed>>
     */
    private function serializeConversations(array $conversations): array
    {
        return array_map(fn (object $conversation): array => $this->serializeConversation($conversation), $conversations);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeConversation(object $conversation): array
    {
        $updatedAt = $conversation->updated_at ?? null;

        return [
            'id' => (string) ($conversation->id ?? ''),
            'title' => (string) ($conversation->title ?? ''),
            'updatedAt' => $updatedAt?->toISOString(),
            'updatedAtHuman' => $updatedAt?->diffForHumans(),
        ];
    }

    /**
     * @param  array<int, object>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function serializeMessages(array $messages): array
    {
        return array_map(static fn (object $message): array => [
            'id' => (string) ($message->id ?? ''),
            'role' => (string) ($message->role ?? 'assistant'),
            'status' => (string) ($message->status ?? 'completed'),
            'finishReason' => $message->finishReason ?? null,
            'content' => (string) ($message->content ?? ''),
            'createdAt' => $message->createdAt ?? null,
            'reasoning' => (string) ($message->reasoning ?? ''),
            'toolActivity' => is_array($message->toolActivity ?? null) ? $message->toolActivity : [],
            'toolOrder' => is_array($message->toolOrder ?? null) ? $message->toolOrder : [],
            'maxStepsReached' => (bool) ($message->maxStepsReached ?? false),
            'stepsUsed' => (int) ($message->stepsUsed ?? 0),
            'debugInfo' => $message->debugInfo ?? null,
            'error' => $message->error ?? null,
        ], $messages);
    }

    /**
     * Stream a chat response (SSE via POST).
     * The client reads the response body as a ReadableStream.
     */
    public function stream(Request $request): StreamedResponse
    {
        abort_unless(Auth::user()->can('use_chatbot'), 403);

        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'conversation_id' => ['nullable', 'string', 'size:36'],
            'internal' => ['nullable', 'boolean'],
            'assistant_message_id' => ['nullable', 'string', 'size:36'],
        ]);

        $conversationId = $request->input('conversation_id');
        $message = $request->input('message');
        $isInternal = $request->boolean('internal');
        $assistantMessageId = $request->input('assistant_message_id');

        // Validate conversation ownership if continuing one
        if ($conversationId && ! $this->service->getConversation($conversationId)) {
            abort(404, 'Conversation not found.');
        }

        if ($isInternal && ! $conversationId) {
            abort(422, 'Internal continuation requires an existing conversation.');
        }

        $conversation = $conversationId
            ? $this->service->getConversation($conversationId)
            : $this->service->createConversation();

        if (! $conversation) {
            abort(404, 'Conversation not found.');
        }

        $history = $this->service->loadConversationHistory($conversation->id);
        if (! $isInternal) {
            $this->service->createUserMessage($conversation->id, $message, ChatBotAgent::class);
            $this->service->ensureImmediateConversationTitle($conversation->id, $message);
            $conversation = $this->service->getConversation($conversation->id) ?? $conversation;
        }

        if ($isInternal && is_string($assistantMessageId) && $assistantMessageId !== '') {
            $assistantDraft = $this->service->getConversationMessage($assistantMessageId, $conversation->id);

            if (! $assistantDraft || $assistantDraft->role !== 'assistant') {
                abort(404, 'Assistant draft not found.');
            }

            $this->service->markAssistantMessageStreaming($assistantDraft->id);
        } else {
            $assistantDraft = $this->service->createAssistantDraft($conversation->id, ChatBotAgent::class);
        }

        $this->service->recordMessageEvent($conversation->id, $assistantDraft->id, 'stream_started', [
            'provider' => $this->service->provider() ?? 'default',
            'model' => $this->service->model() ?? 'default',
            'started_at' => now()->toISOString(),
            'internal' => $isInternal,
        ]);

        $conversationId = $conversation->id;
        $agent = $this->service->makeAgent($conversationId, $history);
        $prompt = $message;
        if (! $isInternal) {
            $interruptionContext = $this->service->interruptionContextNote($conversationId);
            if (is_string($interruptionContext) && $interruptionContext !== '') {
                $prompt = implode("\n\n", [
                    $interruptionContext,
                    'Current user message:',
                    $message,
                ]);
            }
        }
        $conversationContext = config('app.debug')
            ? $this->buildConversationContext($conversationId)
            : [];

        if (config('app.debug')) {
            Log::debug('[ChatBot] Sending message', [
                'provider' => $this->service->provider() ?? 'default',
                'model' => $this->service->model() ?? 'default',
                'conversation_id' => $conversationId,
                'user_id' => Auth::id(),
                'message' => $message,
                'message_length' => mb_strlen($message),
                'message_word_count' => str_word_count($message),
                'conversation_context' => $conversationContext,
            ]);
        }

        $streamable = $agent->stream(
            prompt: $prompt,
            provider: $this->service->provider(),
            model: $this->service->model(),
        );

        // Stream events; send conversation_id AFTER iteration.
        // Note: $streamable->conversationId is NOT set by laravel/ai middleware;
        // the conversation ID is set on the agent instance after the stream is consumed.
        return response()->stream(function () use ($agent, $streamable, $conversation, $assistantDraft, $conversationContext) {
            ignore_user_abort(true);

            $fullResponse = '';
            $reasoningResponse = '';
            $toolCallOrder = [];
            $toolCalls = [];
            $toolResults = [];
            $maxStepsReached = false;
            $finishReason = null;
            $approvalRequired = false;
            $streamStopped = false;
            $lastPartialPersistAt = microtime(true);

            $persistPartialState = function (bool $force = false) use (
                &$fullResponse,
                &$reasoningResponse,
                &$toolCallOrder,
                &$toolCalls,
                &$toolResults,
                &$maxStepsReached,
                &$lastPartialPersistAt,
                $assistantDraft
            ): void {
                if (! $force && (microtime(true) - $lastPartialPersistAt) < 0.5) {
                    return;
                }

                $this->service->persistAssistantMessageState(
                    $assistantDraft->id,
                    $fullResponse,
                    $reasoningResponse,
                    $toolCallOrder,
                    array_values($toolCalls),
                    array_values($toolResults),
                    status: 'streaming',
                    finishReason: null,
                    maxStepsReached: $maxStepsReached,
                    stepsUsed: count($toolCalls),
                    capturedToolActivity: $this->buildLiveToolActivity(
                        array_values($toolCalls),
                        array_values($toolResults),
                    ),
                );

                $lastPartialPersistAt = microtime(true);
            };

            echo 'data: '.json_encode([
                'type' => 'conversation_id',
                'conversation_id' => $conversation->id,
                'conversation_title' => $conversation->title,
            ])."\n\n";
            echo 'data: '.json_encode([
                'type' => 'assistant_state',
                'message_id' => $assistantDraft->id,
                'status' => 'streaming',
                'finish_reason' => null,
            ])."\n\n";
            if (ob_get_level()) {
                ob_flush();
            }
            flush();

            try {
                foreach ($streamable as $event) {
                    $payload = (string) $event;
                    $decoded = json_decode($payload, true);
                    $eventType = $decoded['type'] ?? null;

                    if ($eventType === 'text_delta' && isset($decoded['delta'])) {
                        $fullResponse .= $decoded['delta'];
                    }

                    if ($eventType === 'reasoning_delta' && isset($decoded['delta'])) {
                        $reasoningResponse .= $decoded['delta'];
                    }

                    if ($eventType === 'tool_call' && isset($decoded['tool_id'])) {
                        $toolCallOrder[] = [
                            'tool_id' => $decoded['tool_id'],
                            'text_offset' => mb_strlen($fullResponse),
                        ];
                        $toolCalls[$decoded['tool_id']] = [
                            'id' => $decoded['tool_id'],
                            'name' => $decoded['tool_name'] ?? 'unknown',
                            'arguments' => $decoded['arguments'] ?? '',
                        ];
                        $this->service->recordMessageEvent($conversation->id, $assistantDraft->id, 'tool_called', [
                            'tool_id' => $decoded['tool_id'],
                            'tool_name' => $decoded['tool_name'] ?? 'unknown',
                            'arguments' => $decoded['arguments'] ?? [],
                        ]);
                    }

                    if ($eventType === 'tool_result' && isset($decoded['tool_id'])) {
                        $toolResults[$decoded['tool_id']] = [
                            'id' => $decoded['tool_id'],
                            'name' => $decoded['tool_name'] ?? 'unknown',
                            'result' => $decoded['result'] ?? '',
                            'successful' => $decoded['successful'] ?? false,
                            'error' => $decoded['error'] ?? null,
                        ];
                        $this->service->recordMessageEvent($conversation->id, $assistantDraft->id, 'tool_result', [
                            'tool_id' => $decoded['tool_id'],
                            'tool_name' => $decoded['tool_name'] ?? 'unknown',
                            'successful' => $decoded['successful'] ?? false,
                            'error' => $decoded['error'] ?? null,
                        ]);
                    }

                    if ($eventType === 'stream_end') {
                        $finishReason = $decoded['reason'] ?? null;
                        $maxStepsReached = ($decoded['reason'] ?? '') === 'tool-calls';
                    }

                    $persistPartialState(
                        $eventType === 'tool_call'
                        || $eventType === 'tool_result'
                        || $eventType === 'stream_end'
                    );

                    echo "data: {$payload}\n\n";
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();

                    if ($this->service->getConversationMessage($assistantDraft->id, $conversation->id)?->status === 'stopped') {
                        $streamStopped = true;

                        break;
                    }

                    if (connection_aborted() === 1) {
                        $this->service->persistAssistantMessageState(
                            $assistantDraft->id,
                            $fullResponse !== '' ? $fullResponse : '(Stream interrupted)',
                            $reasoningResponse,
                            $toolCallOrder,
                            array_values($toolCalls),
                            array_values($toolResults),
                            status: 'stopped',
                            finishReason: 'stopped',
                            maxStepsReached: $maxStepsReached,
                            stepsUsed: count($toolCalls),
                            errorCode: 'client_disconnected',
                            errorMessage: 'The client disconnected before the stream completed.',
                            capturedToolActivity: $this->buildLiveToolActivity(
                                array_values($toolCalls),
                                array_values($toolResults),
                            ),
                        );
                        $this->service->recordMessageEvent($conversation->id, $assistantDraft->id, 'stream_stopped', [
                            'stopped_by' => 'disconnect',
                        ]);

                        return;
                    }
                }
            } catch (Throwable $e) {
                $permission = $this->permissions->resolvePayloadFromText($e->getMessage());

                if (is_array($permission) && isset($permission['id'], $permission['tool'])) {
                    $approvalRequired = true;
                    $finishReason = 'approval_required';
                    $toolName = (string) $permission['tool'];
                    $toolId = (string) ($permission['toolInvocationId'] ?? $permission['id']);
                    foreach (array_reverse($toolCalls, true) as $existingToolId => $existingTool) {
                        if (($existingTool['name'] ?? null) === $toolName) {
                            $toolId = (string) $existingToolId;
                            break;
                        }
                    }
                    $toolArguments = $permission['arguments'] ?? [];
                    $toolError = $e->getMessage();

                    if (! isset($toolCalls[$toolId])) {
                        $toolCallOrder[] = [
                            'tool_id' => $toolId,
                            'text_offset' => mb_strlen($fullResponse),
                        ];
                        $toolCalls[$toolId] = [
                            'id' => $toolId,
                            'name' => $toolName,
                            'arguments' => $toolArguments,
                        ];

                        echo 'data: '.json_encode([
                            'type' => 'tool_call',
                            'tool_id' => $toolId,
                            'tool_name' => $toolName,
                            'arguments' => $toolArguments,
                        ])."\n\n";
                    }

                    $toolResults[$toolId] = [
                        'id' => $toolId,
                        'name' => $toolName,
                        'result' => $toolError,
                        'successful' => false,
                        'error' => $toolError,
                    ];

                    $this->service->recordMessageEvent($conversation->id, $assistantDraft->id, 'tool_approval_requested', [
                        'tool_id' => $toolId,
                        'tool_name' => $toolName,
                        'permission' => $permission,
                    ]);

                    echo 'data: '.json_encode([
                        'type' => 'tool_result',
                        'tool_id' => $toolId,
                        'tool_name' => $toolName,
                        'result' => $toolError,
                        'successful' => false,
                        'error' => $toolError,
                    ])."\n\n";
                    echo 'data: '.json_encode([
                        'type' => 'assistant_state',
                        'message_id' => $assistantDraft->id,
                        'status' => 'approval_required',
                        'finish_reason' => 'approval_required',
                    ])."\n\n";

                    $persistPartialState(true);

                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                } else {
                    $this->service->persistAssistantMessageState(
                        $assistantDraft->id,
                        $fullResponse,
                        $reasoningResponse,
                        $toolCallOrder,
                        array_values($toolCalls),
                        array_values($toolResults),
                        status: 'error',
                        finishReason: 'error',
                        maxStepsReached: $maxStepsReached,
                        stepsUsed: count($toolCalls),
                        errorCode: class_basename($e),
                        errorMessage: $e->getMessage(),
                    );
                    $this->service->recordMessageEvent($conversation->id, $assistantDraft->id, 'stream_error', [
                        'error_code' => class_basename($e),
                        'error_message' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            }

            if ($streamStopped) {
                echo 'data: '.json_encode([
                    'type' => 'assistant_state',
                    'message_id' => $assistantDraft->id,
                    'status' => 'stopped',
                    'finish_reason' => 'stopped',
                ])."\n\n";
                echo "data: [DONE]\n\n";

                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

                return;
            }

            if (! $approvalRequired && $finishReason === null) {
                $finishReason = 'stop';
            }

            if ($approvalRequired) {
                $this->service->persistAssistantMessageState(
                    $assistantDraft->id,
                    $fullResponse,
                    $reasoningResponse,
                    $toolCallOrder,
                    array_values($toolCalls),
                    array_values($toolResults),
                    status: 'approval_required',
                    finishReason: 'approval_required',
                    maxStepsReached: $maxStepsReached,
                    stepsUsed: count($toolCalls),
                    errorCode: 'approval_required',
                    errorMessage: 'User approval is required before this tool can run.',
                    capturedToolActivity: $this->buildLiveToolActivity(
                        array_values($toolCalls),
                        array_values($toolResults),
                    ),
                );
                $this->service->recordMessageEvent($conversation->id, $assistantDraft->id, 'stream_error', [
                    'error_code' => 'approval_required',
                    'error_message' => 'User approval is required before this tool can run.',
                ]);
            }

            // Notify the frontend that the tool-step limit was reached
            if ($maxStepsReached) {
                echo 'data: '.json_encode([
                    'type' => 'max_steps_reached',
                    'steps_used' => count($toolCalls),
                ])."\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }

            $resolvedConversationId = $agent->currentConversation();

            // Build + emit per-response debug telemetry (debug mode only)
            $debugInfo = [];
            if (config('app.debug') && ! $approvalRequired) {
                $debugInfo = $this->buildFrontendDebugInfo($agent, $streamable, $fullResponse, $toolCalls, $maxStepsReached);
                echo 'data: '.json_encode(['type' => 'debug_info', ...$debugInfo])."\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }

            $finalStatus = $approvalRequired
                ? 'approval_required'
                : ($maxStepsReached ? 'max_steps' : 'completed');
            $finalFinishReason = $approvalRequired
                ? 'approval_required'
                : ($maxStepsReached ? 'tool-calls' : ($finishReason ?? 'stop'));

            $this->service->persistAssistantMessageState(
                $assistantDraft->id,
                $fullResponse,
                $reasoningResponse,
                $toolCallOrder,
                array_values($toolCalls),
                array_values($toolResults),
                status: $finalStatus,
                finishReason: $finalFinishReason,
                maxStepsReached: $maxStepsReached,
                stepsUsed: count($toolCalls),
                debugInfo: $debugInfo,
                usage: $this->safeStreamableUsage($streamable),
                capturedToolActivity: $this->buildLiveToolActivity(
                    array_values($toolCalls),
                    array_values($toolResults),
                ),
            );
            $updatedConversation = $this->service->getConversation($conversation->id);
            if ($updatedConversation) {
                echo 'data: '.json_encode([
                    'type' => 'conversation_title',
                    'conversation_id' => $updatedConversation->id,
                    'conversation_title' => $updatedConversation->title,
                ])."\n\n";
            }
            echo 'data: '.json_encode([
                'type' => 'assistant_state',
                'message_id' => $assistantDraft->id,
                'status' => $finalStatus,
                'finish_reason' => $finalFinishReason,
            ])."\n\n";
            $this->service->recordMessageEvent($conversation->id, $assistantDraft->id, 'stream_completed', [
                'status' => $finalStatus,
                'finish_reason' => $finalFinishReason,
                'steps_used' => count($toolCalls),
            ]);

            echo "data: [DONE]\n\n";
            if (ob_get_level()) {
                ob_flush();
            }
            flush();

            if (config('app.debug')) {
                Log::debug('[ChatBot] Response complete', [
                    'provider' => $this->service->provider() ?? 'default',
                    'model' => $this->service->model() ?? 'default',
                    'conversation_id' => $resolvedConversationId,
                    'response' => $fullResponse,
                    ...$this->buildResponseDebugContext($streamable, $fullResponse, $conversationContext ?? []),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Delete a conversation.
     */
    public function destroy(Request $request, string $conversationId): RedirectResponse|JsonResponse
    {
        abort_unless(Auth::user()->can('use_chatbot'), 403);

        $conversation = $this->service->getConversation($conversationId);

        if (! $conversation) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Not found.'], 404);
            }

            return redirect()->route('app.chatbot.index');
        }

        $this->service->deleteConversation($conversationId);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('app.chatbot.index')
            ->with('flash_message', ['type' => 'success', 'message' => 'Conversation deleted.']);
    }

    public function approveToolPermission(Request $request, string $conversationId, string $permissionId): JsonResponse
    {
        abort_unless(Auth::user()->can('use_chatbot'), 403);

        if (! $this->service->getConversation($conversationId)) {
            abort(404, 'Conversation not found.');
        }

        $request->validate([
            'assistant_message_id' => ['nullable', 'string', 'size:36'],
            'tool_id' => ['nullable', 'string', 'max:255'],
        ]);

        $permission = $this->permissions->approve($conversationId, $permissionId, Auth::id());
        $execution = $this->permissions->executeApprovedRequest($permission);
        $assistantMessageId = $request->string('assistant_message_id')->toString();
        $toolId = $request->string('tool_id')->toString();

        if ($assistantMessageId !== '' && $toolId !== '') {
            $arguments = is_array($permission->arguments) ? $permission->arguments : [];
            $this->service->recordApprovedToolExecution(
                $assistantMessageId,
                $toolId,
                $permission->tool_name,
                $arguments,
                (string) ($execution['result'] ?? ''),
                (bool) ($execution['successful'] ?? false),
                $execution['error'] ?? null,
            );

            $this->service->recordMessageEvent($conversationId, $assistantMessageId, 'tool_permission_resolved', [
                'tool_id' => $toolId,
                'tool_name' => $permission->tool_name,
                'status' => $execution['successful'] ? 'approved' : 'failed',
            ]);
        }

        return response()->json([
            'success' => true,
            'permission' => $this->permissions->payloadForRequest($permission),
            'execution' => $execution,
        ]);
    }

    public function denyToolPermission(Request $request, string $conversationId, string $permissionId): JsonResponse
    {
        abort_unless(Auth::user()->can('use_chatbot'), 403);

        if (! $this->service->getConversation($conversationId)) {
            abort(404, 'Conversation not found.');
        }

        $request->validate([
            'assistant_message_id' => ['nullable', 'string', 'size:36'],
            'tool_id' => ['nullable', 'string', 'max:255'],
        ]);

        $permission = $this->permissions->deny($conversationId, $permissionId, Auth::id());
        $assistantMessageId = $request->string('assistant_message_id')->toString();
        $toolId = $request->string('tool_id')->toString();

        if ($assistantMessageId !== '' && $toolId !== '') {
            $arguments = is_array($permission->arguments) ? $permission->arguments : [];
            $payload = $this->permissions->payloadForRequest($permission);

            $this->service->recordDeniedToolDecision(
                $assistantMessageId,
                $toolId,
                $permission->tool_name,
                $arguments,
                $payload,
            );

            $this->service->recordMessageEvent($conversationId, $assistantMessageId, 'tool_permission_resolved', [
                'tool_id' => $toolId,
                'tool_name' => $permission->tool_name,
                'status' => 'denied',
            ]);
        }

        return response()->json([
            'success' => true,
            'permission' => $this->permissions->payloadForRequest($permission),
        ]);
    }

    public function stopAssistantMessage(Request $request, string $conversationId, string $messageId): JsonResponse
    {
        abort_unless(Auth::user()->can('use_chatbot'), 403);

        if (! $this->service->getConversation($conversationId)) {
            abort(404, 'Conversation not found.');
        }

        $request->validate([
            'content' => ['nullable', 'string'],
            'reasoning' => ['nullable', 'string'],
            'tool_activity' => ['nullable', 'array'],
            'tool_order' => ['nullable', 'array'],
            'max_steps_reached' => ['nullable', 'boolean'],
            'steps_used' => ['nullable', 'integer', 'min:0'],
        ]);

        $message = $this->service->getConversationMessage($messageId, $conversationId);

        if (! $message || $message->role !== 'assistant') {
            abort(404, 'Assistant message not found.');
        }

        $this->service->persistStoppedAssistantSnapshot(
            $message->id,
            (string) $request->string('content')->toString(),
            (string) $request->string('reasoning')->toString(),
            $request->input('tool_activity', []),
            $request->input('tool_order', []),
            $request->boolean('max_steps_reached'),
            (int) $request->integer('steps_used'),
        );

        $this->service->recordMessageEvent($conversationId, $message->id, 'stream_stopped', [
            'stopped_by' => 'user',
        ]);

        return response()->json([
            'success' => true,
            'message_id' => $message->id,
            'status' => 'stopped',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConversationContext(?string $conversationId): array
    {
        if (! $conversationId) {
            return [
                'message_count' => 0,
                'user_message_count' => 0,
                'assistant_message_count' => 0,
                'context_characters' => 0,
                'last_message_at' => null,
            ];
        }

        $messages = collect($this->service->getMessages($conversationId));

        return [
            'message_count' => $messages->count(),
            'user_message_count' => $messages->where('role', 'user')->count(),
            'assistant_message_count' => $messages->where('role', 'assistant')->count(),
            'context_characters' => $messages->sum(fn (object $message): int => mb_strlen((string) ($message->content ?? ''))),
            'last_message_at' => optional($messages->last())->created_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $conversationContext
     * @return array<string, mixed>
     */
    private function buildResponseDebugContext(StreamableAgentResponse $streamable, string $fullResponse, array $conversationContext): array
    {
        $events = $streamable->events instanceof Collection
            ? $streamable->events
            : collect();

        /** @var StreamStart|null $streamStart */
        $streamStart = $events->first(fn (mixed $event): bool => $event instanceof StreamStart);
        /** @var StreamEnd|null $streamEnd */
        $streamEnd = $events->first(fn (mixed $event): bool => $event instanceof StreamEnd);

        $usage = $this->safeStreamableUsage($streamable);
        $providerMetadata = $streamStart?->metadata;
        $cost = $this->extractCost($providerMetadata);

        return [
            'user_id' => Auth::id(),
            'invocation_id' => $streamable->invocationId,
            'response_length' => mb_strlen($fullResponse),
            'response_word_count' => str_word_count($fullResponse),
            'finish_reason' => $streamEnd?->reason,
            'usage' => array_merge($usage, [
                'total_tokens' => collect($usage)->filter(fn (mixed $value): bool => is_int($value))->sum(),
            ]),
            'context_usage' => [
                'input_tokens' => $usage['prompt_tokens'] ?? 0,
                'cached_input_tokens' => $usage['cache_read_input_tokens'] ?? 0,
                'cache_write_input_tokens' => $usage['cache_write_input_tokens'] ?? 0,
                'output_tokens' => $usage['completion_tokens'] ?? 0,
                'reasoning_tokens' => $usage['reasoning_tokens'] ?? 0,
            ],
            'conversation_context' => $conversationContext,
            'stream' => [
                'event_count' => $events->count(),
                'event_types' => $events
                    ->map(fn (mixed $event): string => $event->type())
                    ->countBy()
                    ->all(),
                'reasoning_delta_count' => $events->whereInstanceOf(ReasoningDelta::class)->count(),
                'tool_call_count' => $events->whereInstanceOf(ToolCall::class)->count(),
                'tool_result_count' => $events->whereInstanceOf(ToolResult::class)->count(),
                'started_at' => $streamStart?->timestamp,
                'completed_at' => $streamEnd?->timestamp,
            ],
            'stream_start' => $streamStart?->toArray(),
            'stream_end' => $streamEnd?->toArray(),
            'provider_metadata' => $providerMetadata,
            'cost' => $cost,
            'cost_available' => $cost !== null,
            'tool_calls' => $events
                ->whereInstanceOf(ToolCall::class)
                ->map(fn (ToolCall $event): array => $event->toArray())
                ->values()
                ->all(),
            'tool_results' => $events
                ->whereInstanceOf(ToolResult::class)
                ->map(fn (ToolResult $event): array => $event->toArray())
                ->values()
                ->all(),
            'errors' => $events
                ->whereInstanceOf(StreamError::class)
                ->map(fn (StreamError $event): array => $event->toArray())
                ->values()
                ->all(),
        ];
    }

    /**
     * Build a lightweight debug-info payload to send to the frontend (debug mode only).
     *
     * Contains model/provider info, token usage, step counts, timing, stream events, and cost.
     *
     * @param  array<string, mixed>  $toolCalls
     * @return array<string, mixed>
     */
    private function buildFrontendDebugInfo(
        ChatBotAgent $agent,
        StreamableAgentResponse $streamable,
        string $fullResponse,
        array $toolCalls,
        bool $maxStepsReached,
    ): array {
        $events = $streamable->events instanceof Collection
            ? $streamable->events
            : collect();

        /** @var StreamStart|null $streamStart */
        $streamStart = $events->first(fn (mixed $e): bool => $e instanceof StreamStart);
        /** @var StreamEnd|null $streamEnd */
        $streamEnd = $events->first(fn (mixed $e): bool => $e instanceof StreamEnd);

        $usage = $this->safeStreamableUsage($streamable);
        $providerMetadata = $streamStart?->metadata;
        $cost = $this->extractCost($providerMetadata);

        // Read the #[MaxSteps] attribute value from the agent class
        $reflection = new \ReflectionClass($agent);
        $maxStepsAttrs = $reflection->getAttributes(MaxSteps::class);
        $maxStepsValue = ! empty($maxStepsAttrs) ? $maxStepsAttrs[0]->newInstance()->value : null;

        $startTs = $streamStart?->timestamp;
        $endTs = $streamEnd?->timestamp;
        $elapsedMs = ($startTs && $endTs) ? (int) (($endTs - $startTs) * 1000) : null;

        return [
            'provider' => $streamStart?->provider ?? ($this->service->provider() ?? 'default'),
            'model' => $streamStart?->model ?? ($this->service->model() ?? 'default'),
            'invocation_id' => $streamable->invocationId,
            'finish_reason' => $streamEnd?->reason,
            'steps_used' => count($toolCalls),
            'max_steps' => $maxStepsValue,
            'max_steps_reached' => $maxStepsReached,
            'usage' => [
                'input_tokens' => $usage['prompt_tokens'] ?? 0,
                'output_tokens' => $usage['completion_tokens'] ?? 0,
                'reasoning_tokens' => $usage['reasoning_tokens'] ?? 0,
                'cached_input_tokens' => $usage['cache_read_input_tokens'] ?? 0,
                'cache_write_input_tokens' => $usage['cache_write_input_tokens'] ?? 0,
                'total_tokens' => collect($usage)->filter(fn (mixed $v): bool => is_int($v))->sum(),
            ],
            'timing' => [
                'elapsed_ms' => $elapsedMs,
                'started_at' => $startTs,
                'completed_at' => $endTs,
            ],
            'response_chars' => mb_strlen($fullResponse),
            'response_words' => str_word_count($fullResponse),
            'stream_events' => $events
                ->map(fn (mixed $e): string => $e->type())
                ->countBy()
                ->all(),
            'cost' => $cost,
            'provider_metadata' => $providerMetadata,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $providerMetadata
     */
    private function extractCost(?array $providerMetadata): mixed
    {
        if (! is_array($providerMetadata)) {
            return null;
        }

        foreach ([
            'cost',
            'usage.cost',
            'pricing.cost',
            'pricing.total_cost',
        ] as $path) {
            $value = data_get($providerMetadata, $path);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeStreamableUsage(StreamableAgentResponse $streamable): array
    {
        if (! isset($streamable->usage)) {
            return [];
        }

        return $streamable->usage?->toArray() ?? [];
    }

    /**
     * @param  array<int, array{id: string, name: string, arguments: mixed}>  $toolCalls
     * @param  array<int, array{id: string, name: string, result: string, successful: bool, error: string|null}>  $toolResults
     * @return array<int, array<string, mixed>>
     */
    private function buildLiveToolActivity(array $toolCalls, array $toolResults): array
    {
        $resultsById = [];
        $approvalBlockingId = null;

        foreach ($toolResults as $result) {
            if (! is_array($result) || ! isset($result['id'])) {
                continue;
            }

            $toolId = (string) $result['id'];
            $resultsById[$toolId] = $result;

            $permission = $this->permissions->resolvePayloadFromText((string) ($result['error'] ?? ''));
            if (is_array($permission) && (($permission['status'] ?? 'pending') === 'pending')) {
                $approvalBlockingId = $toolId;
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
            $errorText = is_array($result) ? $this->extractToolErrorFromResult($result) : null;
            $permission = is_string($errorText) ? $this->permissions->resolvePayloadFromText($errorText) : null;
            $failed = is_array($result) && (((bool) ($result['successful'] ?? false)) === false || $errorText !== null);

            $status = 'running';
            if (is_array($permission) && (($permission['status'] ?? 'pending') === 'pending')) {
                $status = 'approval';
            } elseif ($result !== null) {
                $status = $failed ? 'failed' : 'completed';
            } elseif ($approvalBlockingId !== null && $approvalBlockingId !== $toolId) {
                $status = 'blocked';
            }

            $arguments = $call['arguments'] ?? null;
            $argumentsText = is_array($arguments) || is_object($arguments)
                ? json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                : (string) ($arguments ?? '');

            $activity[] = [
                'toolId' => $toolId,
                'name' => (string) ($call['name'] ?? 'unknown'),
                'status' => $status,
                'arguments' => $argumentsText,
                'result' => $status === 'completed' ? $this->summarizeToolResult($resultText) : null,
                'fullResult' => $status === 'completed' ? $resultText : '',
                'error' => $failed ? $errorText : null,
                'permission' => is_array($permission) ? $permission : null,
            ];
        }

        return $activity;
    }

    /**
     * @param  array{id: string, name: string, result: string, successful: bool, error: string|null}  $result
     */
    private function extractToolErrorFromResult(array $result): ?string
    {
        $explicitError = $result['error'] ?? null;
        if (is_string($explicitError) && trim($explicitError) !== '') {
            return trim($explicitError);
        }

        $resultText = trim((string) ($result['result'] ?? ''));
        if ($resultText !== '' && preg_match('/^Error\b[^\n]*:/u', $resultText) === 1) {
            return $resultText;
        }

        return null;
    }

    private function summarizeToolResult(string $result): string
    {
        $summary = trim($result);

        if ($summary === '') {
            return 'Completed.';
        }

        return mb_strlen($summary) > 200
            ? rtrim(mb_substr($summary, 0, 200)).'...'
            : $summary;
    }
}
