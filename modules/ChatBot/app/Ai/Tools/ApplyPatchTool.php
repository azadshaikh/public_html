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

class ApplyPatchTool implements Tool
{
    use HasToolArgumentParsing;

    public function name(): string
    {
        return 'apply_patch';
    }

    public function description(): Stringable|string
    {
        return <<<'DESC'
Use the `apply_patch` tool to edit files. Your patch language is a stripped-down, file-oriented diff format designed to be easy to parse and safe to apply. You can think of it as a high-level envelope:

*** Begin Patch
[ one or more file sections ]
*** End Patch

Within that envelope, you get a sequence of file operations.
You MUST include a header to specify the action you are taking.
Each operation starts with one of three headers:

*** Add File: <path> - create a new file. Every following line is a + line (the initial contents).
*** Delete File: <path> - remove an existing file. Nothing follows.
*** Update File: <path> - patch an existing file in place (optionally with a rename).

Example patch:

```
*** Begin Patch
*** Add File: hello.txt
+Hello world
*** Update File: src/app.py
*** Move to: src/main.py
@@ def greet():
-print("Hi")
+print("Hello, world!")
*** Delete File: obsolete.txt
*** End Patch
```

It is important to remember:

- You must include a header with your intended action (Add/Delete/Update)
- You must prefix new lines with `+` even when creating a new file
DESC;
    }

    public function handle(Request $request): Stringable|string
    {
        $data = $this->unwrapArguments($request->toArray());
        $patchText = trim((string) ($data['patchText'] ?? ''));
        $debug = config('app.debug');
        $startTime = microtime(true);

        if ($debug) {
            Log::debug('[ChatBot Tool] apply_patch started', [
                'tool' => 'apply_patch',
                'patch_length' => mb_strlen($patchText),
            ]);
        }

        try {
            if ($patchText === '') {
                throw new RuntimeException('patchText is required');
            }

            $result = app(FileToolService::class)->applyPatch($patchText);

            if ($debug) {
                Log::debug('[ChatBot Tool] apply_patch completed', [
                    'tool' => 'apply_patch',
                    'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 1),
                    'result_preview' => mb_substr($result, 0, 200),
                ]);
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($debug) {
                Log::warning('[ChatBot Tool] apply_patch failed', [
                    'tool' => 'apply_patch',
                    'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 1),
                    'error' => $exception->getMessage(),
                ]);
            }

            $message = $exception->getMessage();

            if (str_starts_with($message, 'apply_patch verification failed:') || str_starts_with($message, 'patch rejected:')) {
                return $message;
            }

            return 'apply_patch verification failed: '.$message;
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'patchText' => $schema->string()
                ->description('The full patch text that describes all changes to be made')
                ->required(),
        ];
    }
}
