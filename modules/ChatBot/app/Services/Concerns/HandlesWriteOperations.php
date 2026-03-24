<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services\Concerns;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

trait HandlesWriteOperations
{
    /** Maximum files checked for diagnostics after a move/copy directory operation. */
    private const POST_FILE_OPERATION_DIAGNOSTIC_LIMIT = 25;

    /** Maximum files allowed in a recursive copy. */
    private const COPY_DIRECTORY_MAX_FILES = 500;

    /** Time limit in seconds for a recursive copy. */
    private const COPY_DIRECTORY_TIME_LIMIT_SECONDS = 10;

    /** Maximum items in a single bulk move call. */
    private const BULK_MOVE_MAX_ITEMS = 50;

    public function write(string $path, string $content): string
    {
        $filePath = $this->resolveWritablePath($path);

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $content);

        return 'Wrote file successfully.'.$this->diagnosticsSummary([$filePath]);
    }

    public function movePath(string $source, string $destination, bool $overwrite = false): string
    {
        $sourcePath = $this->resolveExistingPath($source);
        $destPath = $this->resolveWritablePath($destination);

        if ($sourcePath === $destPath) {
            throw new RuntimeException('Source and destination are the same path.');
        }

        if (is_dir($sourcePath)) {
            return $this->moveDirectoryPath($sourcePath, $destPath, $overwrite);
        }

        if (is_file($sourcePath)) {
            return $this->moveFilePath($sourcePath, $destPath, $overwrite);
        }

        throw new RuntimeException("Not a file or directory: {$this->relativePath($sourcePath)}");
    }

    /**
     * Move multiple files or directories in a single call.
     *
     * @param  array<int, array{source: string, destination: string}>  $moves
     */
    public function bulkMovePaths(array $moves, bool $overwrite = false): string
    {
        if ($moves === []) {
            throw new RuntimeException('The "moves" array is empty. Provide at least one {source, destination} pair.');
        }

        if (count($moves) > self::BULK_MOVE_MAX_ITEMS) {
            throw new RuntimeException(sprintf('Too many items (%d). Maximum is %d per call.', count($moves), self::BULK_MOVE_MAX_ITEMS));
        }

        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($moves as $index => $move) {
            $source = trim((string) ($move['source'] ?? ''));
            $destination = trim((string) ($move['destination'] ?? ''));

            if ($source === '' || $destination === '') {
                $results[] = sprintf('⨯ [%d] — missing source or destination', $index);
                $failed++;

                continue;
            }

            try {
                $result = $this->movePath($source, $destination, $overwrite);
                $results[] = $result;
                $succeeded++;
            } catch (RuntimeException $e) {
                $results[] = sprintf('⨯ %s → %s — %s', $source, $destination, $e->getMessage());
                $failed++;
            }
        }

        $summary = sprintf(
            "Bulk move: %d succeeded, %d failed.\n\n%s",
            $succeeded,
            $failed,
            implode("\n", $results),
        );

        return $summary;
    }

    private function moveFilePath(string $sourcePath, string $destPath, bool $overwrite): string
    {
        if (file_exists($destPath)) {
            if (! $overwrite) {
                throw new RuntimeException("Destination already exists: {$this->relativePath($destPath)}. Use overwrite=true to replace it.");
            }

            if (! is_file($destPath)) {
                throw new RuntimeException("Destination is a directory, cannot overwrite with a file: {$this->relativePath($destPath)}");
            }

            File::delete($destPath);
        }

        $sourceRelative = $this->relativePath($sourcePath);
        $destRelative = $this->relativePath($destPath);
        $size = $this->formatBytes(File::size($sourcePath));

        $parentDir = dirname($destPath);
        if (! is_dir($parentDir)) {
            File::makeDirectory($parentDir, 0755, true);
        }

        File::move($sourcePath, $destPath);

        return sprintf('File moved: %s → %s (%s)', $sourceRelative, $destRelative, $size)
            .$this->diagnosticsSummary($this->diagnosticPathsForPath($destPath));
    }

    private function moveDirectoryPath(string $sourcePath, string $destPath, bool $overwrite): string
    {
        $sourceRelative = $this->relativePath($sourcePath);
        $destRelative = $this->relativePath($destPath);

        $this->assertDeleteAllowed($sourceRelative);

        if (str_starts_with($destPath.'/', $sourcePath.'/')) {
            throw new RuntimeException('Cannot move a directory into itself.');
        }

        if (file_exists($destPath)) {
            if (! $overwrite) {
                throw new RuntimeException("Destination already exists: {$destRelative}. Use overwrite=true to replace it.");
            }

            if (! is_dir($destPath)) {
                throw new RuntimeException("Destination is a file, cannot overwrite with a directory: {$destRelative}");
            }

            $this->assertDeleteAllowed($destRelative);

            if (! File::deleteDirectory($destPath)) {
                throw new RuntimeException("Failed to overwrite destination directory: {$destRelative}");
            }
        }

        $parentDir = dirname($destPath);
        if (! is_dir($parentDir)) {
            File::makeDirectory($parentDir, 0755, true);
        }

        $fileCount = count(File::allFiles($sourcePath));
        $directoryCount = count($this->allDirectoriesRecursive($sourcePath));

        File::move($sourcePath, $destPath);

        return sprintf(
            'Directory moved: %s → %s (%d file(s), %d subdirectory(ies))',
            $sourceRelative,
            $destRelative,
            $fileCount,
            $directoryCount,
        ).$this->diagnosticsSummary($this->diagnosticPathsForPath($destPath));
    }

    public function copyPath(string $source, string $destination, bool $overwrite = false): string
    {
        $sourcePath = $this->resolveExistingPath($source);
        $destPath = $this->resolveWritablePath($destination);

        if ($sourcePath === $destPath) {
            throw new RuntimeException('Source and destination are the same path.');
        }

        // Directory → recursive copy
        if (is_dir($sourcePath)) {
            return $this->copyDirectory($sourcePath, $destPath, $overwrite);
        }

        if (file_exists($destPath)) {
            if (! $overwrite) {
                throw new RuntimeException("Destination already exists: {$this->relativePath($destPath)}. Use overwrite=true to replace it.");
            }
            if (! is_file($destPath)) {
                throw new RuntimeException("Destination is a directory, cannot overwrite: {$this->relativePath($destPath)}");
            }
        }

        $sourceRelative = $this->relativePath($sourcePath);
        $destRelative = $this->relativePath($destPath);
        $parentDir = dirname($destPath);

        if (! is_dir($parentDir)) {
            File::makeDirectory($parentDir, 0755, true);
        }

        File::copy($sourcePath, $destPath);

        $size = $this->formatBytes(filesize($destPath) ?: 0);

        return sprintf('File copied: %s → %s (%s)', $sourceRelative, $destRelative, $size)
            .$this->diagnosticsSummary($this->diagnosticPathsForPath($destPath));
    }

    /**
     * Recursively copy a directory tree.
     *
     * Pre-counts files to fail fast before copying anything.
     * If a time limit is hit during copying, the partial destination is cleaned up.
     */
    private function copyDirectory(string $sourcePath, string $destPath, bool $overwrite): string
    {
        $sourceRelative = $this->relativePath($sourcePath);

        // Prevent copying a directory into itself
        if (str_starts_with($destPath.'/', $sourcePath.'/')) {
            throw new RuntimeException('Cannot copy a directory into itself.');
        }

        if (file_exists($destPath) && ! $overwrite) {
            throw new RuntimeException("Destination already exists: {$this->relativePath($destPath)}. Use overwrite=true to merge/replace.");
        }

        // Pre-count files to fail fast before copying anything
        $fileCount = 0;
        $countIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($countIterator as $item) {
            if ($item->isFile()) {
                $fileCount++;
            }
            if ($fileCount > self::COPY_DIRECTORY_MAX_FILES) {
                throw new RuntimeException(sprintf(
                    'Source directory %s contains more than %d files. Recursive copy refused to prevent resource exhaustion.',
                    $sourceRelative,
                    self::COPY_DIRECTORY_MAX_FILES,
                ));
            }
        }

        // Track whether we created the destination so we can clean up on failure
        $createdDestination = ! file_exists($destPath);
        $startTime = microtime(true);
        $filesCopied = 0;
        $totalSize = 0;
        $directoriesCreated = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ((microtime(true) - $startTime) > self::COPY_DIRECTORY_TIME_LIMIT_SECONDS) {
                // Clean up partial copy if we created the destination
                if ($createdDestination && is_dir($destPath)) {
                    File::deleteDirectory($destPath);
                }

                throw new RuntimeException(sprintf(
                    'Directory copy of %s timed out after %ds (copied %d of %d files before aborting). Partial copy was cleaned up.',
                    $sourceRelative,
                    self::COPY_DIRECTORY_TIME_LIMIT_SECONDS,
                    $filesCopied,
                    $fileCount,
                ));
            }

            $relativeSuffix = substr($item->getPathname(), strlen($sourcePath));
            $targetPath = $destPath.$relativeSuffix;

            if ($item->isDir()) {
                if (! is_dir($targetPath)) {
                    File::makeDirectory($targetPath, 0755, true);
                    $directoriesCreated++;
                }
            } else {
                if (file_exists($targetPath) && ! $overwrite) {
                    continue; // skip existing files when not overwriting
                }

                $parentDir = dirname($targetPath);
                if (! is_dir($parentDir)) {
                    File::makeDirectory($parentDir, 0755, true);
                    $directoriesCreated++;
                }

                File::copy($item->getPathname(), $targetPath);
                $totalSize += $item->getSize();
                $filesCopied++;
            }
        }

        $destRelative = $this->relativePath($destPath);
        $elapsed = round((microtime(true) - $startTime) * 1000);

        return sprintf(
            'Directory copied: %s → %s (%d files, %d directories, %s, %dms)',
            $sourceRelative,
            $destRelative,
            $filesCopied,
            $directoriesCreated,
            $this->formatBytes($totalSize),
            $elapsed,
        ).$this->diagnosticsSummary($this->diagnosticPathsForPath($destPath));
    }

    /**
     * @return list<string>
     */
    private function diagnosticPathsForPath(string $path): array
    {
        if (is_file($path)) {
            return $this->detectLspLanguage($path) !== null ? [$path] : [];
        }

        if (! is_dir($path)) {
            return [];
        }

        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }

            $candidatePath = $this->normalizePath($item->getPathname());

            if ($this->detectLspLanguage($candidatePath) === null) {
                continue;
            }

            $paths[] = $candidatePath;

            if (count($paths) >= self::POST_FILE_OPERATION_DIAGNOSTIC_LIMIT) {
                break;
            }
        }

        sort($paths);

        return $paths;
    }
}
