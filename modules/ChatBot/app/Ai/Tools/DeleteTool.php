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

class DeleteTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'delete';
    }

    public function description(): Stringable|string
    {
        return 'Permanently delete files or directories inside the current workspace. This cannot be undone. Provide exactly one of "path" or "paths". The tool auto-detects whether each target is a file or directory. Protected top-level directories cannot be deleted.';
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $path = trim((string) ($data['path'] ?? ''));
        $rawPaths = $data['paths'] ?? [];

        return $this->executeWithLogging(
            arguments: ['path' => $path, 'paths' => $rawPaths],
            operation: function () use ($path, $rawPaths) {
                if (! is_array($rawPaths)) {
                    throw new RuntimeException('Invalid "paths" parameter. Provide an array of file or directory paths.');
                }

                $paths = array_values(array_filter(array_map(
                    static fn (mixed $item): string => trim((string) $item),
                    $rawPaths,
                ), static fn (string $item): bool => $item !== ''));

                if ($path !== '' && $paths !== []) {
                    throw new RuntimeException('Provide either "path" or "paths", not both.');
                }

                if ($path === '' && $paths === []) {
                    throw new RuntimeException(
                        'Missing delete target. Call this tool with {"path": "path/to/item"} or {"paths": ["a", "b"]}.'
                    );
                }

                if ($paths !== []) {
                    return app(FileToolService::class)->bulkDeletePaths($paths);
                }

                return app(FileToolService::class)->deletePath($path);
            },
            errorPrefix: 'Error deleting target',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()
                ->description('Single file or directory path to delete. Absolute or workspace-relative paths are accepted. Provide this or paths, but not both.'),
            'paths' => $schema->array()
                ->description('Array of file or directory paths to delete in one call (max 50). Provide this or path, but not both.')
                ->items($schema->string())
                ->default([]),
        ];
    }
}
