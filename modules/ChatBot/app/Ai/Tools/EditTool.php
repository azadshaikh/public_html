<?php

declare(strict_types=1);

namespace Modules\ChatBot\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\ChatBot\Ai\Tools\Concerns\HasToolArgumentParsing;
use Modules\ChatBot\Services\FileToolService;
use RuntimeException;
use Stringable;

class EditTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'edit';
    }

    public function description(): Stringable|string
    {
        return <<<'DESC'
Performs exact string replacements in files. 

Usage:
- You must use your `Read` tool at least once in the conversation before editing. This tool will error if you attempt an edit without reading the file. 
- When editing text from Read tool output, ensure you preserve the exact indentation (tabs/spaces) as it appears AFTER the line number prefix. The line number prefix format is: line number + colon + space (e.g., `1: `). Everything after that space is the actual file content to match. Never include any part of the line number prefix in the oldString or newString.
- ALWAYS prefer editing existing files in the codebase. NEVER write new files unless explicitly required.
- Only use emojis if the user explicitly requests it. Avoid adding emojis to files unless asked.
- The edit will FAIL if `oldString` is not found in the file with an error "oldString not found in content".
- The edit will FAIL if `oldString` is found multiple times in the file with an error "Found multiple matches for oldString. Provide more surrounding lines in oldString to identify the correct match." Either provide a larger string with more surrounding context to make it unique or use `replaceAll` to change every instance of `oldString`. 
- Use `replaceAll` for replacing and renaming strings across the file. This parameter is useful if you want to rename a variable for instance.
DESC;
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $filePath = trim((string) ($data['filePath'] ?? ''));
        $oldString = (string) ($data['oldString'] ?? '');
        $newString = (string) ($data['newString'] ?? '');
        $replaceAll = filter_var($data['replaceAll'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $debug = config('app.debug');
        $startTime = microtime(true);

        if ($debug) {
            Log::debug('[ChatBot Tool] edit started', [
                'tool' => 'edit',
                'filePath' => $filePath,
                'replaceAll' => $replaceAll,
            ]);
        }

        try {
            if ($filePath === '') {
                throw new RuntimeException('filePath is required');
            }

            $result = app(FileToolService::class)->edit(
                path: $filePath,
                oldString: $oldString,
                newString: $newString,
                replaceAll: $replaceAll,
            );

            if ($debug) {
                Log::debug('[ChatBot Tool] edit completed', [
                    'tool' => 'edit',
                    'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 1),
                    'result_preview' => mb_substr($result, 0, 200),
                ]);
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($debug) {
                Log::warning('[ChatBot Tool] edit failed', [
                    'tool' => 'edit',
                    'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 1),
                    'error' => $exception->getMessage(),
                ]);
            }

            return $exception->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filePath' => $schema->string()
                ->description('The absolute path to the file to modify')
                ->required(),
            'oldString' => $schema->string()
                ->description('The text to replace')
                ->required(),
            'newString' => $schema->string()
                ->description('The text to replace it with (must be different from oldString)')
                ->required(),
            'replaceAll' => $schema->boolean()
                ->description('Replace all occurrences of oldString (default false)'),
        ];
    }
}
