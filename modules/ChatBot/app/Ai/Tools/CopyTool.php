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

class CopyTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'copy';
    }

    public function description(): Stringable|string
    {
        return 'Copy a file or directory to a new location inside the current workspace. The tool auto-detects whether the source is a file or directory. Directory copies are recursive and bounded to 500 files with a 10s time limit. By default, the destination must not already exist. Use overwrite=true to overwrite a file destination or merge/replace files inside a copied directory tree when it is safe.';
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $source = trim((string) ($data['sourcePath'] ?? ''));
        $destination = trim((string) ($data['destinationPath'] ?? ''));
        $overwrite = filter_var($data['overwrite'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return $this->executeWithLogging(
            arguments: ['sourcePath' => $source, 'destinationPath' => $destination, 'overwrite' => $overwrite],
            operation: function () use ($source, $destination, $overwrite) {
                if ($source === '') {
                    throw new RuntimeException(
                        'Missing required "sourcePath" parameter. Call this tool with {"sourcePath": "path/to/file.txt", "destinationPath": "path/to/copy.txt"}.'
                    );
                }

                if ($destination === '') {
                    throw new RuntimeException(
                        'Missing required "destinationPath" parameter. Call this tool with {"sourcePath": "path/to/source", "destinationPath": "path/to/copy"}.'
                    );
                }

                return app(FileToolService::class)->copyPath(source: $source, destination: $destination, overwrite: $overwrite);
            },
            errorPrefix: 'Error copying target',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sourcePath' => $schema->string()
                ->description('Source file or directory path. Absolute or workspace-relative paths are accepted. Directories are copied recursively.')
                ->required(),
            'destinationPath' => $schema->string()
                ->description('Destination path for the copied file or directory. Parent directories are created automatically.')
                ->required(),
            'overwrite' => $schema->boolean()
                ->description('If true, overwrite an existing file destination or merge/replace files inside an existing destination directory when the source is a directory.')
                ->default(false),
        ];
    }
}
