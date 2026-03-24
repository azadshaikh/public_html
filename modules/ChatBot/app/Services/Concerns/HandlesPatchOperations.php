<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services\Concerns;

use Illuminate\Support\Facades\File;
use RuntimeException;

trait HandlesPatchOperations
{
    private const string PATCH_BEGIN = '*** Begin Patch';

    private const string PATCH_END = '*** End Patch';

    private const string PATCH_ADD = '*** Add File:';

    private const string PATCH_UPDATE = '*** Update File:';

    private const string PATCH_DELETE = '*** Delete File:';

    private const string PATCH_MOVE = '*** Move to:';

    private const string PATCH_END_OF_FILE = '*** End of File';

    public function applyPatch(string $patchText): string
    {
        $hunks = $this->parsePatch($patchText);

        if ($hunks === []) {
            $normalized = $this->normalizePatchText($patchText);

            if ($normalized === self::PATCH_BEGIN."\n".self::PATCH_END) {
                throw new RuntimeException('patch rejected: empty patch');
            }

            throw new RuntimeException('apply_patch verification failed: no hunks found');
        }

        $fileChanges = [];

        foreach ($hunks as $hunk) {
            $relativePath = $hunk['path'];

            if ($hunk['type'] === 'add') {
                $filePath = $this->resolveWritablePath($relativePath);
                $newContent = $this->prepareAddFileContent($hunk['contents']);

                $fileChanges[] = [
                    'type' => 'add',
                    'filePath' => $filePath,
                    'relativePath' => $this->relativePath($filePath),
                    'newContent' => $newContent,
                ];

                continue;
            }

            if ($hunk['type'] === 'update') {
                $filePath = $this->resolvePatchReadableFile($relativePath, 'update');

                try {
                    $newContent = $this->deriveNewContentsFromChunks($filePath, $hunk['chunks']);
                } catch (\Throwable $exception) {
                    throw new RuntimeException('apply_patch verification failed: '.$exception->getMessage());
                }

                $movePath = isset($hunk['movePath']) ? $this->resolveWritablePath($hunk['movePath']) : null;
                $targetPath = $movePath ?? $filePath;

                $fileChanges[] = [
                    'type' => $movePath !== null ? 'move' : 'update',
                    'filePath' => $filePath,
                    'relativePath' => $this->relativePath($targetPath),
                    'newContent' => $newContent,
                    'movePath' => $movePath,
                ];

                continue;
            }

            if ($hunk['type'] === 'delete') {
                $filePath = $this->resolvePatchReadableFile($relativePath, 'delete');
                $this->readPatchFile($filePath, 'delete');

                $fileChanges[] = [
                    'type' => 'delete',
                    'filePath' => $filePath,
                    'relativePath' => $this->relativePath($filePath),
                ];
            }
        }

        foreach ($fileChanges as $change) {
            if ($change['type'] === 'add') {
                File::ensureDirectoryExists(dirname($change['filePath']));
                File::put($change['filePath'], $change['newContent']);

                continue;
            }

            if ($change['type'] === 'update') {
                File::put($change['filePath'], $change['newContent']);

                continue;
            }

            if ($change['type'] === 'move') {
                File::ensureDirectoryExists(dirname($change['movePath']));
                File::put($change['movePath'], $change['newContent']);

                if ($change['movePath'] !== $change['filePath']) {
                    File::delete($change['filePath']);
                }

                continue;
            }

            File::delete($change['filePath']);
        }

        $summaryLines = array_map(function (array $change): string {
            if ($change['type'] === 'add') {
                return 'A '.$change['relativePath'];
            }

            if ($change['type'] === 'delete') {
                return 'D '.$change['relativePath'];
            }

            return 'M '.$change['relativePath'];
        }, $fileChanges);

        $diagnosticPaths = array_values(array_filter(array_map(
            fn (array $change): ?string => in_array($change['type'], ['add', 'update', 'move'], true)
                ? ($change['movePath'] ?? $change['filePath'])
                : null,
            $fileChanges,
        )));

        return "Success. Updated the following files:\n"
            .implode("\n", $summaryLines)
            .$this->diagnosticsSummary($diagnosticPaths);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parsePatch(string $patchText): array
    {
        $cleaned = $this->stripHeredoc($this->normalizePatchText($patchText));
        $lines = explode("\n", $cleaned);
        $beginIndex = $this->findMarkerIndex($lines, self::PATCH_BEGIN);
        $endIndex = $this->findMarkerIndex($lines, self::PATCH_END);

        if ($beginIndex === -1 || $endIndex === -1 || $beginIndex >= $endIndex) {
            throw new RuntimeException('apply_patch verification failed: Invalid patch format: missing Begin/End markers');
        }

        $hunks = [];
        $index = $beginIndex + 1;

        while ($index < $endIndex) {
            $header = $this->parsePatchHeader($lines, $index);

            if ($header === null) {
                $index++;

                continue;
            }

            if ($header['type'] === 'add') {
                [$content, $nextIndex] = $this->parseAddFileContent($lines, $header['nextIndex']);
                $hunks[] = [
                    'type' => 'add',
                    'path' => $header['filePath'],
                    'contents' => $content,
                ];
                $index = $nextIndex;

                continue;
            }

            if ($header['type'] === 'delete') {
                $hunks[] = [
                    'type' => 'delete',
                    'path' => $header['filePath'],
                ];
                $index = $header['nextIndex'];

                continue;
            }

            [$chunks, $nextIndex] = $this->parseUpdateFileChunks($lines, $header['nextIndex']);
            $updateHunk = [
                'type' => 'update',
                'path' => $header['filePath'],
                'chunks' => $chunks,
            ];

            if ($header['movePath'] !== null) {
                $updateHunk['movePath'] = $header['movePath'];
            }

            $hunks[] = $updateHunk;
            $index = $nextIndex;
        }

        return $hunks;
    }

    private function stripHeredoc(string $input): string
    {
        if (preg_match('/^(?:cat\s+)?<<[\'"]?(\w+)[\'"]?\s*\n([\s\S]*?)\n\1\s*$/', $input, $matches) === 1) {
            return $matches[2];
        }

        return $input;
    }

    private function findMarkerIndex(array $lines, string $marker): int
    {
        foreach ($lines as $index => $line) {
            if (trim($line) === $marker) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{type: string, filePath: string, movePath: ?string, nextIndex: int}|null
     */
    private function parsePatchHeader(array $lines, int $startIndex): ?array
    {
        $line = $lines[$startIndex] ?? '';

        if (str_starts_with($line, self::PATCH_ADD)) {
            $filePath = trim(substr($line, strlen(self::PATCH_ADD)));

            return $filePath === '' ? null : [
                'type' => 'add',
                'filePath' => $filePath,
                'movePath' => null,
                'nextIndex' => $startIndex + 1,
            ];
        }

        if (str_starts_with($line, self::PATCH_DELETE)) {
            $filePath = trim(substr($line, strlen(self::PATCH_DELETE)));

            return $filePath === '' ? null : [
                'type' => 'delete',
                'filePath' => $filePath,
                'movePath' => null,
                'nextIndex' => $startIndex + 1,
            ];
        }

        if (str_starts_with($line, self::PATCH_UPDATE)) {
            $filePath = trim(substr($line, strlen(self::PATCH_UPDATE)));
            $movePath = null;
            $nextIndex = $startIndex + 1;

            if (($lines[$nextIndex] ?? null) !== null && str_starts_with($lines[$nextIndex], self::PATCH_MOVE)) {
                $movePath = trim(substr($lines[$nextIndex], strlen(self::PATCH_MOVE)));
                $nextIndex++;
            }

            return $filePath === '' ? null : [
                'type' => 'update',
                'filePath' => $filePath,
                'movePath' => $movePath !== '' ? $movePath : null,
                'nextIndex' => $nextIndex,
            ];
        }

        return null;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{0: string, 1: int}
     */
    private function parseAddFileContent(array $lines, int $startIndex): array
    {
        $content = '';
        $index = $startIndex;

        while ($index < count($lines) && ! $this->startsPatchBoundary($lines[$index])) {
            $line = $lines[$index];

            if (! str_starts_with($line, '+')) {
                throw new RuntimeException("apply_patch verification failed: Invalid Add File line (missing '+'): {$line}");
            }

            $content .= substr($line, 1)."\n";
            $index++;
        }

        if (str_ends_with($content, "\n")) {
            $content = substr($content, 0, -1);
        }

        return [$content, $index];
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function parseUpdateFileChunks(array $lines, int $startIndex): array
    {
        $chunks = [];
        $index = $startIndex;

        while ($index < count($lines) && ! $this->startsPatchBoundary($lines[$index])) {
            if (! str_starts_with($lines[$index], '@@')) {
                $index++;

                continue;
            }

            $contextLine = trim(substr($lines[$index], 2));
            $index++;
            $oldLines = [];
            $newLines = [];
            $isEndOfFile = false;

            while ($index < count($lines) && ! str_starts_with($lines[$index], '@@') && ! $this->startsPatchBoundary($lines[$index])) {
                $changeLine = $lines[$index];

                if ($changeLine === self::PATCH_END_OF_FILE) {
                    $isEndOfFile = true;
                    $index++;
                    break;
                }

                if (str_starts_with($changeLine, ' ')) {
                    $content = substr($changeLine, 1);
                    $oldLines[] = $content;
                    $newLines[] = $content;
                } elseif (str_starts_with($changeLine, '-')) {
                    $oldLines[] = substr($changeLine, 1);
                } elseif (str_starts_with($changeLine, '+')) {
                    $newLines[] = substr($changeLine, 1);
                }

                $index++;
            }

            $chunk = [
                'old_lines' => $oldLines,
                'new_lines' => $newLines,
            ];

            if ($contextLine !== '') {
                $chunk['change_context'] = $contextLine;
            }

            if ($isEndOfFile) {
                $chunk['is_end_of_file'] = true;
            }

            $chunks[] = $chunk;
        }

        return [$chunks, $index];
    }

    private function prepareAddFileContent(string $contents): string
    {
        if ($contents === '' || str_ends_with($contents, "\n")) {
            return $contents;
        }

        return $contents."\n";
    }

    private function resolvePatchReadableFile(string $path, string $operation): string
    {
        $absolutePath = $this->normalizePath($this->toAbsolutePath($path));

        try {
            $filePath = $this->resolveExistingPath($path);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'Access denied: path is outside the current workspace.') {
                throw new RuntimeException('apply_patch verification failed: Access denied: path is outside the current workspace.');
            }

            throw new RuntimeException("apply_patch verification failed: Failed to read file to {$operation}: {$absolutePath}");
        }

        if (! is_file($filePath) || ! File::isReadable($filePath)) {
            throw new RuntimeException("apply_patch verification failed: Failed to read file to {$operation}: {$filePath}");
        }

        return $filePath;
    }

    private function readPatchFile(string $filePath, string $operation): string
    {
        try {
            return str_replace(["\r\n", "\r"], "\n", File::get($filePath));
        } catch (\Throwable $exception) {
            throw new RuntimeException("apply_patch verification failed: Failed to read file to {$operation}: {$filePath}", previous: $exception);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $chunks
     */
    private function deriveNewContentsFromChunks(string $filePath, array $chunks): string
    {
        $originalContent = $this->readPatchFile($filePath, 'update');
        $originalLines = explode("\n", $originalContent);

        if ($originalLines !== [] && end($originalLines) === '') {
            array_pop($originalLines);
        }

        $replacements = $this->computeReplacements($originalLines, $filePath, $chunks);
        $newLines = $this->applyReplacements($originalLines, $replacements);

        if ($newLines === [] || end($newLines) !== '') {
            $newLines[] = '';
        }

        return implode("\n", $newLines);
    }

    /**
     * @param  array<int, string>  $originalLines
     * @param  list<array<string, mixed>>  $chunks
     * @return list<array{0: int, 1: int, 2: array<int, string>}>
     */
    private function computeReplacements(array $originalLines, string $filePath, array $chunks): array
    {
        $replacements = [];
        $lineIndex = 0;

        foreach ($chunks as $chunk) {
            if (isset($chunk['change_context'])) {
                $contextIndex = $this->seekSequence($originalLines, [$chunk['change_context']], $lineIndex);

                if ($contextIndex === -1) {
                    throw new RuntimeException("Failed to find context '{$chunk['change_context']}' in {$filePath}");
                }

                $lineIndex = $contextIndex + 1;
            }

            $oldLines = $chunk['old_lines'];
            $newLines = $chunk['new_lines'];
            $isEndOfFile = (bool) ($chunk['is_end_of_file'] ?? false);

            if ($oldLines === []) {
                $replacements[] = [count($originalLines), 0, $newLines];

                continue;
            }

            $pattern = $oldLines;
            $replacement = $newLines;
            $found = $this->seekSequence($originalLines, $pattern, $lineIndex, $isEndOfFile);

            if ($found === -1 && $pattern !== [] && end($pattern) === '') {
                array_pop($pattern);

                if ($replacement !== [] && end($replacement) === '') {
                    array_pop($replacement);
                }

                $found = $this->seekSequence($originalLines, $pattern, $lineIndex, $isEndOfFile);
            }

            if ($found === -1) {
                throw new RuntimeException("Failed to find expected lines in {$filePath}:\n".implode("\n", $oldLines));
            }

            $replacements[] = [$found, count($pattern), $replacement];
            $lineIndex = $found + count($pattern);
        }

        usort($replacements, fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return $replacements;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  list<array{0: int, 1: int, 2: array<int, string>}>  $replacements
     * @return array<int, string>
     */
    private function applyReplacements(array $lines, array $replacements): array
    {
        $result = $lines;

        for ($index = count($replacements) - 1; $index >= 0; $index--) {
            [$startIndex, $oldLength, $newSegment] = $replacements[$index];
            array_splice($result, $startIndex, $oldLength, $newSegment);
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<int, string>  $pattern
     */
    private function seekSequence(array $lines, array $pattern, int $startIndex, bool $endOfFile = false): int
    {
        if ($pattern === []) {
            return -1;
        }

        $exact = $this->tryMatch($lines, $pattern, $startIndex, fn (string $left, string $right): bool => $left === $right, $endOfFile);
        if ($exact !== -1) {
            return $exact;
        }

        $trimEnd = $this->tryMatch(
            $lines,
            $pattern,
            $startIndex,
            fn (string $left, string $right): bool => rtrim($left) === rtrim($right),
            $endOfFile,
        );
        if ($trimEnd !== -1) {
            return $trimEnd;
        }

        $trim = $this->tryMatch(
            $lines,
            $pattern,
            $startIndex,
            fn (string $left, string $right): bool => trim($left) === trim($right),
            $endOfFile,
        );
        if ($trim !== -1) {
            return $trim;
        }

        return $this->tryMatch(
            $lines,
            $pattern,
            $startIndex,
            fn (string $left, string $right): bool => $this->normalizeUnicode(trim($left)) === $this->normalizeUnicode(trim($right)),
            $endOfFile,
        );
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<int, string>  $pattern
     * @param  callable(string, string): bool  $compare
     */
    private function tryMatch(array $lines, array $pattern, int $startIndex, callable $compare, bool $endOfFile): int
    {
        if ($endOfFile) {
            $fromEnd = count($lines) - count($pattern);

            if ($fromEnd >= $startIndex && $this->sequenceMatches($lines, $pattern, $fromEnd, $compare)) {
                return $fromEnd;
            }
        }

        $maxIndex = count($lines) - count($pattern);

        for ($index = $startIndex; $index <= $maxIndex; $index++) {
            if ($this->sequenceMatches($lines, $pattern, $index, $compare)) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<int, string>  $pattern
     * @param  callable(string, string): bool  $compare
     */
    private function sequenceMatches(array $lines, array $pattern, int $offset, callable $compare): bool
    {
        foreach ($pattern as $index => $line) {
            if (! $compare($lines[$offset + $index], $line)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeUnicode(string $value): string
    {
        return str_replace(
            ["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", "\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", "\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2015}", "\u{2026}", "\u{00A0}"],
            ["'", "'", "'", "'", '"', '"', '"', '"', '-', '-', '-', '-', '-', '-', '...', ' '],
            $value,
        );
    }

    private function normalizePatchText(string $patchText): string
    {
        return str_replace(["\r\n", "\r"], "\n", trim($patchText));
    }

    private function startsPatchBoundary(string $line): bool
    {
        return str_starts_with($line, self::PATCH_END)
            || str_starts_with($line, self::PATCH_ADD)
            || str_starts_with($line, self::PATCH_UPDATE)
            || str_starts_with($line, self::PATCH_DELETE);
    }
}
