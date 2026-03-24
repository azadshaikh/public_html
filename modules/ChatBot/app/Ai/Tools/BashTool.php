<?php

declare(strict_types=1);

namespace Modules\ChatBot\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\ChatBot\Ai\Tools\Concerns\HasToolArgumentParsing;
use Modules\ChatBot\Services\FileToolService;
use RuntimeException;
use Stringable;

class BashTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'bash';
    }

    public function description(): Stringable|string
    {
        return sprintf(<<<'DESC'
Executes a given bash command with optional timeout, ensuring proper handling and security measures.

All commands run in %s by default. Use the `workdir` parameter if you need to run the command in a different directory. AVOID using `cd <directory> && <command>` patterns - use `workdir` instead.

IMPORTANT: This tool is for terminal operations like git, npm, composer, artisan, and build tooling. DO NOT use it for file operations (reading, writing, editing, searching, finding files) - use the specialized tools for this instead.

Usage notes:
- command is required.
- description is required and should clearly explain what the command does in 5-10 words.
- timeout is optional and uses milliseconds. If omitted, the command times out after 120000ms (2 minutes).
- workdir is optional. Defaults to the current workspace root.
- If the output exceeds 2000 lines or 50KB, it will be truncated and the full output will be written to a file. Use Read with offset/limit or Grep to inspect the saved output.
- Avoid using Bash with find, grep, cat, head, tail, sed, awk, or echo unless they are truly necessary for the task. Prefer the dedicated tools instead.
- When commands are independent, prefer multiple Bash tool calls in parallel. When commands depend on each other, use a single Bash call with `&&`.

Current backend scope:
- User approval is required before execution.
- Structured streaming metadata is deferred.
- Commands currently run as one-shot bash processes, not a true persistent shell session.
DESC, base_path());
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $command = trim((string) ($data['command'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $workdir = isset($data['workdir']) ? trim((string) $data['workdir']) : null;
        $timeout = array_key_exists('timeout', $data) ? (int) $data['timeout'] : null;

        return $this->executeWithLogging(
            arguments: [
                'command' => $command,
                'description' => $description,
                'workdir' => $workdir,
                'timeout' => $timeout,
            ],
            operation: function () use ($command, $description, $workdir, $timeout): string {
                if ($command === '') {
                    throw new RuntimeException('Missing required "command" parameter.');
                }

                if ($description === '') {
                    throw new RuntimeException('Missing required "description" parameter.');
                }

                if ($timeout !== null && $timeout <= 0) {
                    throw new RuntimeException('Invalid "timeout" parameter. Timeout must be greater than 0 milliseconds.');
                }

                return app(FileToolService::class)->bash(
                    command: $command,
                    description: $description,
                    workdir: $workdir,
                    timeout: $timeout,
                );
            },
            errorPrefix: 'Error running bash command',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()
                ->description('The command to execute')
                ->required(),
            'timeout' => $schema->integer()
                ->description('Optional timeout in milliseconds'),
            'workdir' => $schema->string()
                ->description(sprintf('The working directory to run the command in. Defaults to %s. Use this instead of cd commands.', base_path())),
            'description' => $schema->string()
                ->description("Clear, concise description of what this command does in 5-10 words. Examples:\nInput: ls\nOutput: Lists files in current directory\n\nInput: git status\nOutput: Shows working tree status\n\nInput: composer install\nOutput: Installs PHP dependencies\n\nInput: mkdir foo\nOutput: Creates directory foo")
                ->required(),
        ];
    }
}
