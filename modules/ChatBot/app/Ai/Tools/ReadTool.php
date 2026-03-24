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

class ReadTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'read';
    }

    public function description(): Stringable|string
    {
        return <<<'DESC'
Read a file or directory from the local filesystem. If the path does not exist, an error is returned.

Usage:
- The filePath parameter should be an absolute path.
- By default, this tool returns up to 2000 lines from the start of the file.
- The offset parameter is the line number to start from (1-indexed).
- To read later sections, call this tool again with a larger offset.
- If the requested offset is beyond the available lines or directory entries, an error is returned.
- Use the grep tool to find specific content in large files or files with long lines.
- If you are unsure of the correct file path, use the glob tool to look up filenames by glob pattern.
- Contents are returned with each line prefixed by its line number as `<line>: <content>`. For example, if a file has contents "foo\n", you will receive "1: foo\n". For directories, entries are returned one per line (without line numbers) with a trailing `/` for subdirectories.
- Any line longer than 2000 characters is truncated.
- Call this tool in parallel when you know there are multiple files you want to read.
- Avoid tiny repeated slices (30 line chunks). If you need more context, read a larger window.
- Image/PDF attachment support is deferred in this app. For now, those paths return a notice instead of a file attachment.
DESC;
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $filePath = trim((string) ($data['filePath'] ?? ''));
        $offset = array_key_exists('offset', $data) ? (int) $data['offset'] : null;
        $limit = array_key_exists('limit', $data) ? (int) $data['limit'] : null;

        return $this->executeWithLogging(
            arguments: ['filePath' => $filePath, 'offset' => $offset, 'limit' => $limit],
            operation: function () use ($filePath, $offset, $limit) {
                if ($filePath === '') {
                    throw new RuntimeException('Missing required "filePath" parameter.');
                }

                return app(FileToolService::class)->read($filePath, $offset, $limit);
            },
            errorPrefix: 'Error reading path',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'filePath' => $schema->string()
                ->description('The absolute path to the file or directory to read')
                ->required(),
            'offset' => $schema->integer()
                ->description('The line number to start reading from (1-indexed)'),
            'limit' => $schema->integer()
                ->description('The maximum number of lines to read (defaults to 2000)'),
        ];
    }
}
