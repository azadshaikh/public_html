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

class MoveTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'move';
    }

    public function description(): Stringable|string
    {
        return 'Move or rename files or directories inside the current workspace. Provide either "sourcePath" + "destinationPath" for a single move, or "moves" for bulk mode. The tool auto-detects whether each source is a file or directory. By default, the destination must not already exist. Use overwrite=true to replace an existing destination when it is safe.';
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $source = trim((string) ($data['sourcePath'] ?? ''));
        $destination = trim((string) ($data['destinationPath'] ?? ''));
        $rawMoves = $data['moves'] ?? [];
        $overwrite = filter_var($data['overwrite'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $this->executeWithLogging(
            arguments: ['sourcePath' => $source, 'destinationPath' => $destination, 'moves' => $rawMoves, 'overwrite' => $overwrite],
            operation: function () use ($source, $destination, $rawMoves, $overwrite) {
                if (! is_array($rawMoves)) {
                    throw new RuntimeException(
                        'Invalid "moves" parameter. Provide an array of {source, destination} objects.'
                    );
                }

                $moves = array_values(array_filter($rawMoves, static fn (mixed $move): bool => is_array($move) && $move !== []));

                if ($moves !== [] && ($source !== '' || $destination !== '')) {
                    throw new RuntimeException(
                        'Provide either "sourcePath" + "destinationPath" or "moves", not both.'
                    );
                }

                if ($moves !== []) {
                    return app(FileToolService::class)->bulkMovePaths(moves: $moves, overwrite: $overwrite);
                }

                if ($source === '' && $destination === '') {
                    throw new RuntimeException(
                        'Missing move target. Call this tool with {"sourcePath": "old/path", "destinationPath": "new/path"} or {"moves": [{"source": "a", "destination": "b"}]}.'
                    );
                }

                if ($source === '') {
                    throw new RuntimeException(
                        'Missing required "sourcePath" parameter. Call this tool with {"sourcePath": "old/path", "destinationPath": "new/path"}.'
                    );
                }

                if ($destination === '') {
                    throw new RuntimeException(
                        'Missing required "destinationPath" parameter. Call this tool with {"sourcePath": "old/path", "destinationPath": "new/path"}.'
                    );
                }

                return app(FileToolService::class)->movePath(source: $source, destination: $destination, overwrite: $overwrite);
            },
            errorPrefix: 'Error moving target',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sourcePath' => $schema->string()
                ->description('Source file or directory path for single-move mode. Absolute or workspace-relative paths are accepted. Provide this with destinationPath, or use moves for bulk mode.'),
            'destinationPath' => $schema->string()
                ->description('Destination path for single-move mode. Parent directories are created automatically. Provide this with sourcePath, or use moves for bulk mode.'),
            'moves' => $schema->array()
                ->description('Array of {source, destination} pairs to move in one call (bulk mode, max 50). Provide this or sourcePath/destinationPath, but not both.')
                ->items($schema->object([
                    'source' => $schema->string()
                        ->description('Source file or directory path.')
                        ->required(),
                    'destination' => $schema->string()
                        ->description('Destination file or directory path.')
                        ->required(),
                ]))
                ->default([]),
            'overwrite' => $schema->boolean()
                ->description('If true, overwrite an existing destination when the source and destination types are compatible. Applies to both single and bulk mode.')
                ->default(false),
        ];
    }
}
