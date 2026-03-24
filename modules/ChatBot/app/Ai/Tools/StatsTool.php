<?php

declare(strict_types=1);

namespace Modules\ChatBot\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\ChatBot\Ai\Tools\Concerns\HasToolArgumentParsing;
use Modules\ChatBot\Services\FileToolService;
use Stringable;

class StatsTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'stats';
    }

    public function description(): Stringable|string
    {
        return implode(' ', [
            'Get aggregated workspace stats for a directory: total files, subdirectories, disk usage, lines of code, and top file types.',
            'Excludes gitignored paths and may stop early on very large trees.',
            'Useful for understanding the overall project shape before drilling into specific files.',
        ]);
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $path = trim((string) ($data['path'] ?? '.')) ?: '.';

        return $this->executeWithLogging(
            arguments: ['path' => $path],
            operation: fn () => app(FileToolService::class)->stats(path: $path),
            errorPrefix: 'Error getting stats',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('Directory to summarize (relative or absolute). Defaults to the current workspace root.')
                ->default('.'),
        ];
    }
}
