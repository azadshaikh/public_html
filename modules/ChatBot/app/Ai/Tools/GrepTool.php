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

class GrepTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'grep';
    }

    public function description(): Stringable|string
    {
        return <<<'DESC'
- Fast content search tool that works with any codebase size
- Searches file contents using regular expressions
- Supports full regex syntax (eg. "log.*Error", "function\s+\w+", etc.)
- Filter files by pattern with the include parameter (eg. "*.js", "*.{ts,tsx}")
- Returns file paths and line numbers with at least one match sorted by modification time
- Use this tool when you need to find files containing specific patterns
- If you need to identify/count the number of matches within files, use the Bash tool with `rg` (ripgrep) directly. Do NOT use `grep`.
- When you are doing an open-ended search that may require multiple rounds of globbing and grepping, use the Task tool instead
DESC;
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $pattern = trim((string) ($data['pattern'] ?? ''));
        $path = array_key_exists('path', $data) ? trim((string) $data['path']) : null;
        $include = array_key_exists('include', $data) ? trim((string) $data['include']) : null;
        $debug = config('app.debug');
        $startTime = microtime(true);

        if ($debug) {
            Log::debug('[ChatBot Tool] grep started', [
                'tool' => 'grep',
                'pattern' => $pattern,
                'path' => $path,
                'include' => $include,
            ]);
        }

        try {
            if ($pattern === '') {
                throw new RuntimeException('pattern is required');
            }

            $result = app(FileToolService::class)->grep(
                pattern: $pattern,
                path: $path !== '' ? $path : null,
                include: $include !== '' ? $include : null,
            );

            if ($debug) {
                Log::debug('[ChatBot Tool] grep completed', [
                    'tool' => 'grep',
                    'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 1),
                    'result_preview' => mb_substr($result, 0, 200),
                ]);
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($debug) {
                Log::warning('[ChatBot Tool] grep failed', [
                    'tool' => 'grep',
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
            'pattern' => $schema->string()
                ->description('The regex pattern to search for in file contents')
                ->required(),
            'path' => $schema->string()
                ->description('The directory to search in. Defaults to the current working directory.'),
            'include' => $schema->string()
                ->description('File pattern to include in the search (e.g. "*.js", "*.{ts,tsx}")'),
        ];
    }
}
