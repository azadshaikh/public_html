<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolInvoked;
use Modules\ChatBot\Models\ToolPermissionRequest;
use RuntimeException;
use Throwable;

class ToolPermissionService
{
    /**
     * @var list<string>
     */
    private const GUARDED_TOOLS = [
        'bash',
    ];

    public function enforceFromInvocation(InvokingTool $event): void
    {
        $toolName = $this->toolName($event->tool);

        if (! in_array($toolName, self::GUARDED_TOOLS, true)) {
            return;
        }

        $conversationId = $this->conversationId($event->agent);
        $userId = $this->userId($event->agent);

        if ($conversationId === null) {
            throw new RuntimeException('Tool approval requires an active conversation.');
        }

        $fingerprint = $this->fingerprint($toolName, $event->arguments);
        $request = ToolPermissionRequest::query()
            ->where('conversation_id', $conversationId)
            ->where('tool_name', $toolName)
            ->where('request_fingerprint', $fingerprint)
            ->latest('created_at')
            ->first();

        if ($request !== null && $request->status === 'approved' && $request->consumed_at === null) {
            return;
        }

        if ($request === null) {
            $request = ToolPermissionRequest::query()->create([
                'id' => (string) Str::uuid7(),
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'tool_name' => $toolName,
                'tool_invocation_id' => $event->toolInvocationId,
                'request_fingerprint' => $fingerprint,
                'status' => 'pending',
                'arguments' => $this->normalizedArguments($event->arguments),
                'metadata' => $this->metadataForTool($toolName, $event->arguments),
            ]);
        } else {
            $request->forceFill([
                'tool_invocation_id' => $event->toolInvocationId,
            ])->save();
        }

        throw new RuntimeException($this->approvalMessage($request));
    }

    public function markInvocationCompleted(ToolInvoked $event): void
    {
        $toolName = $this->toolName($event->tool);

        if (! in_array($toolName, self::GUARDED_TOOLS, true)) {
            return;
        }

        $conversationId = $this->conversationId($event->agent);

        if ($conversationId === null) {
            return;
        }

        $request = ToolPermissionRequest::query()
            ->where('conversation_id', $conversationId)
            ->where('tool_name', $toolName)
            ->where('request_fingerprint', $this->fingerprint($toolName, $event->arguments))
            ->where('status', 'approved')
            ->whereNull('consumed_at')
            ->latest('created_at')
            ->first();

        if ($request === null) {
            return;
        }

        $request->forceFill([
            'consumed_at' => now(),
            'tool_invocation_id' => $event->toolInvocationId,
        ])->save();
    }

    public function approve(string $conversationId, string $requestId, int|string|null $userId): ToolPermissionRequest
    {
        $request = $this->findConversationRequest($conversationId, $requestId, $userId);

        $request->forceFill([
            'status' => 'approved',
            'approved_at' => now(),
            'denied_at' => null,
            'consumed_at' => null,
        ])->save();

        return $request->refresh();
    }

    /**
     * @return array{successful: bool, result: string, error: string|null}
     */
    public function executeApprovedRequest(ToolPermissionRequest $request): array
    {
        try {
            $arguments = is_array($request->arguments) ? $request->arguments : [];

            $result = match ($request->tool_name) {
                'bash' => app(FileToolService::class)->bash(
                    command: trim((string) ($arguments['command'] ?? '')),
                    description: trim((string) ($arguments['description'] ?? '')),
                    workdir: isset($arguments['workdir']) ? trim((string) $arguments['workdir']) : null,
                    timeout: array_key_exists('timeout', $arguments) ? (int) $arguments['timeout'] : null,
                ),
                'apply_patch' => app(FileToolService::class)->applyPatch(
                    trim((string) ($arguments['patchText'] ?? '')),
                ),
                default => throw new RuntimeException('Tool execution is not supported for approval flow.'),
            };

            $request->forceFill([
                'consumed_at' => now(),
            ])->save();

            return [
                'successful' => true,
                'result' => $result,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'successful' => false,
                'result' => '',
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function deny(string $conversationId, string $requestId, int|string|null $userId): ToolPermissionRequest
    {
        $request = $this->findConversationRequest($conversationId, $requestId, $userId);

        $request->forceFill([
            'status' => 'denied',
            'denied_at' => now(),
            'approved_at' => null,
            'consumed_at' => null,
        ])->save();

        return $request->refresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolvePayloadFromText(?string $text): ?array
    {
        if (! is_string($text) || $text === '') {
            return null;
        }

        if (! preg_match('/<tool_permission>(.*?)<\/tool_permission>/s', $text, $matches)) {
            return null;
        }

        $decoded = json_decode($matches[1], true);

        if (! is_array($decoded) || ! isset($decoded['id'])) {
            return null;
        }

        $request = ToolPermissionRequest::query()->find($decoded['id']);

        if ($request === null) {
            return $decoded;
        }

        return $this->payloadForRequest($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadForRequest(ToolPermissionRequest $request): array
    {
        $metadata = is_array($request->metadata) ? $request->metadata : [];

        return [
            'id' => $request->id,
            'conversationId' => $request->conversation_id,
            'tool' => $request->tool_name,
            'status' => $request->status,
            'toolInvocationId' => $request->tool_invocation_id,
            'arguments' => is_array($request->arguments) ? $request->arguments : [],
            'title' => (string) ($metadata['title'] ?? Str::headline($request->tool_name)),
            'summary' => (string) ($metadata['summary'] ?? ''),
            'details' => $metadata['details'] ?? [],
        ];
    }

    private function findConversationRequest(string $conversationId, string $requestId, int|string|null $userId): ToolPermissionRequest
    {
        $query = ToolPermissionRequest::query()
            ->where('id', $requestId)
            ->where('conversation_id', $conversationId);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataForTool(string $toolName, array $arguments): array
    {
        return match ($toolName) {
            'bash' => $this->metadataForBash($arguments),
            'apply_patch' => $this->metadataForApplyPatch($arguments),
            default => [
                'title' => Str::headline($toolName),
                'summary' => 'Approval required before execution.',
                'details' => [],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataForBash(array $arguments): array
    {
        $command = trim((string) ($arguments['command'] ?? ''));
        $description = trim((string) ($arguments['description'] ?? ''));
        $workdir = trim((string) ($arguments['workdir'] ?? '.'));
        $timeout = (int) ($arguments['timeout'] ?? 120000);

        return [
            'title' => $description !== '' ? $description : 'Run bash command',
            'summary' => Str::limit($command, 240),
            'details' => [
                'workdir' => $workdir !== '' ? $workdir : '.',
                'timeout' => $timeout.' ms',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataForApplyPatch(array $arguments): array
    {
        $patchText = (string) ($arguments['patchText'] ?? '');
        preg_match_all('/^\*\*\* (?:Add|Update|Delete) File: (.+)$/m', $patchText, $matches);
        $files = array_values(array_unique(array_map('trim', $matches[1] ?? [])));
        $summary = $files === []
            ? 'Apply patch changes'
            : 'Patch touches '.count($files).' file(s): '.implode(', ', array_slice($files, 0, 3));

        if (count($files) > 3) {
            $summary .= ', ...';
        }

        return [
            'title' => 'Apply patch changes',
            'summary' => Str::limit($summary, 240),
            'details' => [
                'files' => array_slice($files, 0, 10),
            ],
        ];
    }

    private function approvalMessage(ToolPermissionRequest $request): string
    {
        return 'User approval is required before this tool can run.'
            ."\n<tool_permission>"
            .json_encode($this->payloadForRequest($request), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            .'</tool_permission>';
    }

    private function conversationId(Agent $agent): ?string
    {
        if (! method_exists($agent, 'currentConversation')) {
            return null;
        }

        $conversationId = $agent->currentConversation();

        return is_string($conversationId) && $conversationId !== '' ? $conversationId : null;
    }

    private function userId(Agent $agent): int|string|null
    {
        if (! method_exists($agent, 'conversationParticipant')) {
            return null;
        }

        $participant = $agent->conversationParticipant();

        return is_object($participant) && isset($participant->id) ? $participant->id : null;
    }

    private function toolName(object $tool): string
    {
        return method_exists($tool, 'name')
            ? (string) $tool->name()
            : class_basename($tool);
    }

    private function fingerprint(string $toolName, array $arguments): string
    {
        return hash('sha256', json_encode([
            'tool' => $toolName,
            'arguments' => $this->normalizedArguments($arguments),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedArguments(array $arguments): array
    {
        return $this->sortRecursively(Arr::undot($arguments));
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortRecursively($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }

        return $value;
    }
}
