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

class GlobTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'glob';
    }

    public function description(): Stringable|string
    {
        return <<<'DESC'
- Fast file pattern matching tool that works with any codebase size
- Supports glob patterns like "**/*.js" or "src/**/*.ts"
- Returns matching file paths sorted by modification time
- Use this tool when you need to find files by name patterns
- When you are doing an open-ended search that may require multiple rounds of globbing and grepping, use the Task tool instead
- You have the capability to call multiple tools in a single response. It is always better to speculatively perform multiple searches as a batch that are potentially useful.
DESC;
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $pattern = trim((string) ($data['pattern'] ?? ''));
        $path = array_key_exists('path', $data) ? trim((string) $data['path']) : null;
        $debug = config('app.debug');
        $startTime = microtime(true);

        if ($debug) {
            Log::debug('[ChatBot Tool] glob started', [
                'tool' => 'glob',
                'pattern' => $pattern,
                'path' => $path,
            ]);
        }

        try {
            if ($pattern === '') {
                throw new RuntimeException('pattern is required');
            }

            $result = app(FileToolService::class)->glob(
                pattern: $pattern,
                path: $path !== '' ? $path : null,
            );

            if ($debug) {
                Log::debug('[ChatBot Tool] glob completed', [
                    'tool' => 'glob',
                    'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 1),
                    'result_preview' => mb_substr($result, 0, 200),
                ]);
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($debug) {
                Log::warning('[ChatBot Tool] glob failed', [
                    'tool' => 'glob',
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
                ->description('The glob pattern to match files against')
                ->required(),
            'path' => $schema->string()
                ->description('The directory to search in. If omitted, the current working directory will be used. Must be a valid directory path if provided.'),
        ];
    }
}
