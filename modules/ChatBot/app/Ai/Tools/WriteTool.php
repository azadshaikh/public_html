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

class WriteTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'write';
    }

    public function description(): Stringable|string
    {
        return <<<'DESC'
Writes a file to the local filesystem.

Usage:
- This tool will overwrite the existing file if there is one at the provided path.
- If this is an existing file, you MUST use the Read tool first to read the file's contents. This tool will fail if you did not read the file first.
- ALWAYS prefer editing existing files in the codebase. NEVER write new files unless explicitly required.
- NEVER proactively create documentation files (*.md) or README files. Only create documentation files if explicitly requested by the User.
- Only use emojis if the user explicitly requests it. Avoid writing emojis to files unless asked.
DESC;
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $filePath = trim((string) ($data['filePath'] ?? ''));
        $content = (string) ($data['content'] ?? '');

        return $this->executeWithLogging(
            arguments: ['filePath' => $filePath],
            operation: function () use ($filePath, $content, $data) {
                if ($filePath === '') {
                    throw new RuntimeException('Missing required "filePath" parameter.');
                }

                if (! array_key_exists('content', $data)) {
                    throw new RuntimeException('Missing required "content" parameter.');
                }

                return app(FileToolService::class)->write($filePath, $content);
            },
            errorPrefix: 'Error writing file',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()
                ->description('The content to write to the file')
                ->required(),
            'filePath' => $schema->string()
                ->description('The absolute path to the file to write (must be absolute, not relative)')
                ->required(),
        ];
    }
}
