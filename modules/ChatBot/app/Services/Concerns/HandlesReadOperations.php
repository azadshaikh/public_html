<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services\Concerns;

use Illuminate\Support\Facades\File;
use RuntimeException;

trait HandlesReadOperations
{
    private const int DEFAULT_READ_LIMIT = 2000;

    private const int MAX_LINE_LENGTH = 2000;

    private const string MAX_LINE_SUFFIX = '... (line truncated to 2000 chars)';

    private const int MAX_BYTES = 51200;

    private const string MAX_BYTES_LABEL = '50 KB';

    public function read(string $path, ?int $offset = null, ?int $limit = null): string
    {
        if ($offset !== null && $offset < 1) {
            throw new RuntimeException('offset must be greater than or equal to 1');
        }

        $absolutePath = $this->normalizePath($this->toAbsolutePath($path));

        $this->assertInsideWorkspace($absolutePath);

        if (! file_exists($absolutePath)) {
            throw new RuntimeException($this->missingPathMessage($absolutePath));
        }

        $resolvedPath = realpath($absolutePath);
        if ($resolvedPath === false) {
            throw new RuntimeException($this->missingPathMessage($absolutePath));
        }

        $resolvedPath = $this->normalizePath($resolvedPath);

        $this->assertInsideWorkspace($resolvedPath);

        if (is_dir($resolvedPath)) {
            return $this->readDirectory($resolvedPath, $offset, $limit);
        }

        if (! is_file($resolvedPath)) {
            throw new RuntimeException("Not a file or directory: {$this->relativePath($resolvedPath)}");
        }

        if (! File::isReadable($resolvedPath)) {
            throw new RuntimeException("File is not readable: {$this->relativePath($resolvedPath)}");
        }

        return $this->readRegularFile($resolvedPath, $offset, $limit);
    }

    private function readDirectory(string $directoryPath, ?int $offset = null, ?int $limit = null): string
    {
        $entries = collect(scandir($directoryPath) ?: [])
            ->reject(fn (string $entry): bool => in_array($entry, ['.', '..'], true))
            ->map(function (string $entry) use ($directoryPath): string {
                $fullPath = $directoryPath.DIRECTORY_SEPARATOR.$entry;

                if (is_dir($fullPath)) {
                    return $entry.'/';
                }

                if (is_link($fullPath)) {
                    $target = realpath($fullPath);
                    if ($target !== false && is_dir($target)) {
                        return $entry.'/';
                    }
                }

                return $entry;
            })
            ->sort()
            ->values()
            ->all();

        $effectiveLimit = max(1, $limit ?? self::DEFAULT_READ_LIMIT);
        $effectiveOffset = max(1, $offset ?? 1);
        $totalEntries = count($entries);

        if ($totalEntries < $effectiveOffset && ! ($totalEntries === 0 && $effectiveOffset === 1)) {
            throw new RuntimeException("Offset {$effectiveOffset} is out of range for this directory ({$totalEntries} entries)");
        }

        $start = $effectiveOffset - 1;
        $sliced = array_slice($entries, $start, $effectiveLimit);
        $truncated = $start + count($sliced) < $totalEntries;

        return implode("\n", [
            "<path>{$directoryPath}</path>",
            '<type>directory</type>',
            '<entries>',
            implode("\n", $sliced),
            $truncated
                ? "\n(Showing ".count($sliced)." of {$totalEntries} entries. Use 'offset' parameter to read beyond entry ".($effectiveOffset + count($sliced)).')'
                : "\n({$totalEntries} entries)",
            '</entries>',
        ]);
    }

    private function readRegularFile(string $filePath, ?int $offset = null, ?int $limit = null): string
    {
        $mimeType = File::mimeType($filePath) ?: '';
        $isImage = str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml';
        $isPdf = $mimeType === 'application/pdf';

        if ($isImage || $isPdf) {
            $label = $isImage ? 'Image' : 'PDF';

            return implode("\n", [
                "<path>{$filePath}</path>",
                '<type>file</type>',
                '<content>',
                "{$label} read successfully",
                '',
                '(ChatBot deviation: image/PDF attachment support for `read` is deferred. This tool currently returns a notice instead of a file attachment.)',
                '</content>',
            ]);
        }

        $this->ensureTextFile($filePath);
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Failed to read file: {$this->relativePath($filePath)}");
        }

        $effectiveLimit = max(1, $limit ?? self::DEFAULT_READ_LIMIT);
        $effectiveOffset = max(1, $offset ?? 1);
        $raw = [];
        $bytes = 0;
        $truncatedByBytes = false;
        $hasMoreLines = false;
        $totalLines = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $totalLines++;

                if ($totalLines < $effectiveOffset) {
                    continue;
                }

                if (count($raw) >= $effectiveLimit) {
                    $hasMoreLines = true;

                    continue;
                }

                $line = rtrim($line, "\r\n");

                if (mb_strlen($line) > self::MAX_LINE_LENGTH) {
                    $line = mb_substr($line, 0, self::MAX_LINE_LENGTH).self::MAX_LINE_SUFFIX;
                }

                $size = strlen($line) + (count($raw) > 0 ? 1 : 0);
                if ($bytes + $size > self::MAX_BYTES) {
                    $truncatedByBytes = true;
                    $hasMoreLines = true;
                    break;
                }

                $raw[] = "{$totalLines}: {$line}";
                $bytes += $size;
            }

            if (! feof($handle)) {
                throw new RuntimeException("Failed to read file: {$this->relativePath($filePath)}");
            }
        } finally {
            fclose($handle);
        }

        if ($totalLines < $effectiveOffset && ! ($totalLines === 0 && $effectiveOffset === 1)) {
            throw new RuntimeException("Offset {$effectiveOffset} is out of range for this file ({$totalLines} lines)");
        }

        $lastReadLine = $raw === [] ? 0 : $effectiveOffset + count($raw) - 1;
        $nextOffset = max($effectiveOffset, $lastReadLine + 1);

        $output = implode("\n", [
            "<path>{$filePath}</path>",
            '<type>file</type>',
            '<content>',
            implode("\n", $raw),
        ]);

        if ($truncatedByBytes) {
            $output .= "\n\n(Output capped at ".self::MAX_BYTES_LABEL.". Showing lines {$effectiveOffset}-{$lastReadLine}. Use offset={$nextOffset} to continue.)";
        } elseif ($hasMoreLines) {
            $output .= "\n\n(Showing lines {$effectiveOffset}-{$lastReadLine} of {$totalLines}. Use offset={$nextOffset} to continue.)";
        } else {
            $output .= "\n\n(End of file - total {$totalLines} lines)";
        }

        $output .= "\n</content>";

        return $output;
    }

    private function missingPathMessage(string $absolutePath): string
    {
        $directory = dirname($absolutePath);
        $basename = basename($absolutePath);
        $suggestions = [];

        if (is_dir($directory)) {
            $suggestions = collect(scandir($directory) ?: [])
                ->reject(fn (string $entry): bool => in_array($entry, ['.', '..'], true))
                ->filter(function (string $entry) use ($basename): bool {
                    $entryLower = mb_strtolower($entry);
                    $baseLower = mb_strtolower($basename);

                    return str_contains($entryLower, $baseLower) || str_contains($baseLower, $entryLower);
                })
                ->map(fn (string $entry): string => $directory.DIRECTORY_SEPARATOR.$entry)
                ->take(3)
                ->values()
                ->all();
        }

        if ($suggestions !== []) {
            return "File not found: {$absolutePath}\n\nDid you mean one of these?\n".implode("\n", $suggestions);
        }

        return "File not found: {$absolutePath}";
    }
}
