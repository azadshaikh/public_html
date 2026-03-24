<?php

declare(strict_types=1);

namespace Modules\ChatBot\Ai;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Modules\ChatBot\Ai\Tools\ApplyPatchTool;
use Modules\ChatBot\Ai\Tools\BashTool;
use Modules\ChatBot\Ai\Tools\CopyTool;
use Modules\ChatBot\Ai\Tools\DeleteTool;
use Modules\ChatBot\Ai\Tools\EditTool;
use Modules\ChatBot\Ai\Tools\GlobTool;
use Modules\ChatBot\Ai\Tools\GrepTool;
use Modules\ChatBot\Ai\Tools\ListTool;
use Modules\ChatBot\Ai\Tools\LspTool;
use Modules\ChatBot\Ai\Tools\MoveTool;
use Modules\ChatBot\Ai\Tools\MultiEditTool;
use Modules\ChatBot\Ai\Tools\ReadTool;
use Modules\ChatBot\Ai\Tools\StatsTool;
use Modules\ChatBot\Ai\Tools\WriteTool;
use Stringable;

#[MaxSteps(100)]
class ChatBotAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    protected ?string $conversationId = null;

    protected ?object $conversationUser = null;

    /**
     * @var array<int, Message>
     */
    protected array $conversationMessages = [];

    public function instructions(): Stringable|string
    {
        $parts = [
            (string) setting('chatbot_system_prompt', 'You are a helpful, concise, and friendly AI assistant. Answer clearly and accurately.'),
            implode("\n", [
                'Tool usage rules:',
                '- When a task requires reading files, writing files, or inspecting directories, use the available tools.',
                '- Never claim you read, listed, or modified files unless a tool call actually succeeded.',
                '- Never output fake tool syntax or pretend shell commands already ran.',
                '- Tool arguments are plain JSON values. Do NOT embed programming expressions like date(), $variable, or string concatenation (. operator) inside argument values.',
                '- If you need a dynamic value (timestamp, calculation, etc.), compute it first, then pass the final plain text result to the tool.',
                '- Example correct call: write with {"filePath": "notes.txt", "content": "Created on 2026-03-07"}',
                '- Example WRONG call: write with {"content": "Created on " . date("Y-m-d")}  ← this is PHP code, not a valid JSON string.',
                '- Commit to writing: once you have read enough context to proceed, ALWAYS write the changes using write or apply_patch. Never end a turn with a text-only plan or "here is what I would do" summary when the user asked you to make actual changes — always execute the writes.',
            ]),
        ];

        // Only document tools that are actually enabled — never reveal disabled tools
        $toolDocs = array_filter([
            $this->toolEnabled('chatbot_tool_list') ? '- list: path?, ignore? — rooted directory tree listing using system ripgrep; approval metadata and structured results are deferred for now.' : null,
            $this->toolEnabled('chatbot_tool_read') ? '- read: filePath (required), offset (optional), limit (optional) — reads a file or directory; returns line-numbered file content or directory entries. Image/PDF attachment parity is deferred and currently returns a notice.' : null,
            $this->toolEnabled('chatbot_tool_write') ? '- write: filePath (required), content (required) — writes a file to the local filesystem. Read-before-write enforcement is deferred. PHP syntax diagnostics are appended when available; structured diagnostics remain deferred.' : null,
            $this->toolEnabled('chatbot_tool_apply_patch') ? '- apply_patch: patchText (required) — stripped-down patch format with *** Begin Patch / *** End Patch and Add / Update / Delete file sections. PHP syntax diagnostics are appended when available.' : null,
            $this->toolEnabled('chatbot_tool_edit') ? '- edit: filePath (required), oldString (required), newString (required), replaceAll? — exact-string replacement tool; read-before-edit enforcement and approval metadata are deferred. PHP syntax diagnostics are appended when available.' : null,
            $this->toolEnabled('chatbot_tool_multiedit', false) ? '- multiedit: filePath (required), edits (required) — multiple sequential exact-string replacements on one file; read-before-edit enforcement and approval metadata are deferred. PHP syntax diagnostics are appended when available.' : null,
            $this->toolEnabled('chatbot_tool_delete') ? '- delete: path or paths[] (max 50) — deletes files or directories inside the workspace, auto-detects the target type, and blocks protected top-level directories.' : null,
            $this->toolEnabled('chatbot_tool_move') ? '- move: sourcePath, destinationPath, moves[], overwrite (default false) — moves or renames files or directories, supports bulk mode, and blocks protected top-level directory moves.' : null,
            $this->toolEnabled('chatbot_tool_copy') ? '- copy: sourcePath, destinationPath, overwrite (default false) — copies files or directories, with recursive directory copy capped at 500 files and 10s.' : null,
            $this->toolEnabled('chatbot_tool_glob') ? '- glob: pattern (required), path? — fast filename search using glob patterns and ripgrep; approval metadata and structured results are deferred for now.' : null,
            $this->toolEnabled('chatbot_tool_grep') ? '- grep: pattern (required), path?, include? — regex-only content search using system ripgrep; approval metadata and structured results are deferred for now.' : null,
            $this->toolEnabled('chatbot_tool_bash') ? '- bash: command (required), description (required), timeout?, workdir? — runs one-shot bash commands with timeout support and bounded output; user approval is required before execution. Streaming metadata and persistent-shell parity are still deferred.' : null,
            $this->toolEnabled('chatbot_tool_lsp') ? '- lsp: operation, filePath, line, character — code intelligence tool with strongest support for PHP and best-effort support for JavaScript and CSS.' : null,
            $this->toolEnabled('chatbot_tool_stats') ? '- stats: path? — aggregated workspace summary with file counts, directory counts, total size, text line count, and top file types.' : null,
        ]);

        if ($toolDocs !== []) {
            $parts[] = implode("\n", ['Canonical tool parameter names (always use these):', ...$toolDocs]);
        }

        return implode("\n\n", $parts);
    }

    public function tools(): iterable
    {
        $tools = [];

        if ($this->toolEnabled('chatbot_tool_list')) {
            $tools[] = new ListTool;
        }

        if ($this->toolEnabled('chatbot_tool_read')) {
            $tools[] = new ReadTool;
        }

        if ($this->toolEnabled('chatbot_tool_write')) {
            $tools[] = new WriteTool;
        }

        if ($this->toolEnabled('chatbot_tool_apply_patch')) {
            $tools[] = new ApplyPatchTool;
        }

        if ($this->toolEnabled('chatbot_tool_edit')) {
            $tools[] = new EditTool;
        }

        if ($this->toolEnabled('chatbot_tool_multiedit', false)) {
            $tools[] = new MultiEditTool;
        }

        if ($this->toolEnabled('chatbot_tool_delete')) {
            $tools[] = new DeleteTool;
        }

        if ($this->toolEnabled('chatbot_tool_move')) {
            $tools[] = new MoveTool;
        }

        if ($this->toolEnabled('chatbot_tool_copy')) {
            $tools[] = new CopyTool;
        }

        if ($this->toolEnabled('chatbot_tool_glob')) {
            $tools[] = new GlobTool;
        }

        if ($this->toolEnabled('chatbot_tool_grep')) {
            $tools[] = new GrepTool;
        }

        if ($this->toolEnabled('chatbot_tool_bash')) {
            $tools[] = new BashTool;
        }

        if ($this->toolEnabled('chatbot_tool_lsp')) {
            $tools[] = new LspTool;
        }

        if ($this->toolEnabled('chatbot_tool_stats')) {
            $tools[] = new StatsTool;
        }

        return $tools;
    }

    public function forUser($user): static
    {
        $this->conversationUser = $user;

        return $this;
    }

    /**
     * @param  array<int, Message|array{role: string, content: string|null}|object>  $messages
     */
    public function continue(string $conversationId, object $as, array $messages = []): static
    {
        $this->conversationId = $conversationId;
        $this->conversationUser = $as;
        $this->conversationMessages = array_map(
            static fn (mixed $message): Message => Message::tryFrom($message),
            $messages,
        );

        return $this;
    }

    /**
     * @return array<int, Message>
     */
    public function messages(): iterable
    {
        return $this->conversationMessages;
    }

    public function currentConversation(): ?string
    {
        return $this->conversationId;
    }

    public function hasConversationParticipant(): bool
    {
        return $this->conversationUser !== null;
    }

    public function conversationParticipant(): ?object
    {
        return $this->conversationUser;
    }

    private function toolEnabled(string $settingKey, bool $default = true): bool
    {
        return filter_var(setting($settingKey, $default), FILTER_VALIDATE_BOOLEAN);
    }
}
