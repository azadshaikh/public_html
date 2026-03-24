<?php

declare(strict_types=1);

namespace Modules\ChatBot\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\ChatBot\Ai\Tools\Concerns\HasToolArgumentParsing;
use Modules\ChatBot\Services\FileToolService;
use Stringable;

class ListTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'list';
    }

    public function description(): Stringable|string
    {
        return 'Lists files and directories in a given path. The path parameter must be absolute; omit it to use the current workspace directory. You can optionally provide an array of glob patterns to ignore with the ignore parameter. You should generally prefer the Glob and Grep tools, if you know which directories to search.';
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $path = array_key_exists('path', $data) ? trim((string) $data['path']) : null;
        $ignore = is_array($data['ignore'] ?? null) ? array_values(array_map('strval', $data['ignore'])) : null;
        $debug = config('app.debug');
        $startTime = microtime(true);

        if ($debug) {
            Log::debug('[ChatBot Tool] list started', [
                'tool' => 'list',
                'path' => $path,
                'ignore' => $ignore,
            ]);
        }

        try {
            $result = app(FileToolService::class)->list(
                path: $path !== '' ? $path : null,
                ignore: $ignore,
            );

            if ($debug) {
                Log::debug('[ChatBot Tool] list completed', [
                    'tool' => 'list',
                    'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 1),
                    'result_preview' => mb_substr($result, 0, 200),
                ]);
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($debug) {
                Log::warning('[ChatBot Tool] list failed', [
                    'tool' => 'list',
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
            'path' => $schema->string()
                ->description('The absolute path to the directory to list (must be absolute, not relative)'),
            'ignore' => $schema->array(
                $schema->string()->description('A glob pattern to ignore')
            )->description('List of glob patterns to ignore'),
        ];
    }
}
