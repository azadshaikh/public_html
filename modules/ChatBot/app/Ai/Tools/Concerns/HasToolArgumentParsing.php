<?php

declare(strict_types=1);

namespace Modules\ChatBot\Ai\Tools\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Shared argument-parsing and execution helpers for ChatBot tool classes.
 *
 * Every tool must handle LLM quirks:
 *  - Arguments wrapped in a container key ("schema_definition", "arguments", etc.)
 */
trait HasToolArgumentParsing
{
    /**
     * Unwrap arguments that the LLM may have nested inside a container key.
     *
     * Some models (e.g. DeepSeek) wrap tool arguments in {"schema_definition": {...}}
     * or similar container keys. This method detects and unwraps them.
     *
     * @param  array<string, mixed>  $data  Raw request data (typically from $request->toArray())
     * @return array<string, mixed> Unwrapped argument data
     */
    protected function unwrapArguments(array $data): array
    {
        foreach (['schema_definition', 'arguments', 'params', 'parameters', 'input'] as $key) {
            if (! isset($data[$key])) {
                continue;
            }

            $inner = $data[$key];

            if (is_string($inner)) {
                $decoded = json_decode($inner, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } elseif (is_array($inner)) {
                return $inner;
            }
        }

        return $data;
    }

    /**
     * Execute a tool operation with debug logging (timing, arguments, result summary).
     *
     * When app.debug is true, logs tool start/finish with execution time and
     * a truncated result preview. On failure, logs the error before returning.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments for logging
     * @param  \Closure(): string  $operation  The actual tool logic
     * @param  string  $errorPrefix  Prefix for error messages (e.g. "Error reading file")
     */
    protected function executeWithLogging(array $arguments, \Closure $operation, string $errorPrefix): string
    {
        $toolName = method_exists($this, 'name') ? $this->name() : class_basename($this);
        $debug = config('app.debug');

        if ($debug) {
            Log::debug("[ChatBot Tool] {$toolName} started", [
                'tool' => $toolName,
                'arguments' => $this->sanitizeArguments($arguments),
                'memory' => $this->formatMemory(),
            ]);
        }

        $startTime = microtime(true);

        try {
            $result = $operation();
            $elapsed = round((microtime(true) - $startTime) * 1000, 1);

            if ($debug) {
                Log::debug("[ChatBot Tool] {$toolName} completed", [
                    'tool' => $toolName,
                    'elapsed_ms' => $elapsed,
                    'result_length' => mb_strlen($result),
                    'result_preview' => mb_substr($result, 0, 200),
                    'memory' => $this->formatMemory(),
                ]);
            }

            return $result;
        } catch (\Throwable $exception) {
            $elapsed = round((microtime(true) - $startTime) * 1000, 1);

            if ($debug) {
                Log::warning("[ChatBot Tool] {$toolName} failed", [
                    'tool' => $toolName,
                    'elapsed_ms' => $elapsed,
                    'error' => $exception->getMessage(),
                    'memory' => $this->formatMemory(),
                ]);
            }

            return "{$errorPrefix}: {$exception->getMessage()}";
        }
    }

    /**
     * Truncate large argument values (like file content) to keep logs readable.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function sanitizeArguments(array $arguments): array
    {
        $sanitized = [];
        foreach ($arguments as $key => $value) {
            if (is_string($value) && mb_strlen($value) > 300) {
                $sanitized[$key] = mb_substr($value, 0, 200).'... ('.mb_strlen($value).' chars)';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Format current memory usage for log context.
     */
    private function formatMemory(): string
    {
        return round(memory_get_usage(true) / 1024 / 1024, 1).'MB';
    }
}
