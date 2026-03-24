<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services\Concerns;

use Illuminate\Support\Facades\File;
use RuntimeException;

trait HandlesDirectoryOperations
{
    private const LIST_LIMIT = 100;

    private const BULK_DELETE_MAX_ITEMS = 50;

    /**
     * @var list<string>
     */
    private const PROTECTED_TOP_LEVEL_DIRECTORIES = [
        'app',
        'bootstrap',
        'config',
        'database',
        'docs',
        'lang',
        'modules',
        'public',
        'resources',
        'routes',
        'storage',
        'tests',
        'vendor',
    ];

    /**
     * @var list<string>
     */
    private const LIST_IGNORE_PATTERNS = [
        'node_modules/',
        '__pycache__/',
        '.git/',
        'dist/',
        'build/',
        'target/',
        'vendor/',
        'bin/',
        'obj/',
        '.idea/',
        '.vscode/',
        '.zig-cache/',
        'zig-out',
        '.coverage',
        'coverage/',
        'vendor/',
        'tmp/',
        'temp/',
        '.cache/',
        'cache/',
        'logs/',
        '.venv/',
        'venv/',
        'env/',
    ];

    public function list(?string $path = null, ?array $ignore = null): string
    {
        $searchPath = $path !== null && trim($path) !== '' ? trim($path) : $this->workspaceRoot();
        $absoluteSearchPath = $this->normalizePath($this->toAbsolutePath($searchPath));
        $this->assertInsideWorkspace($absoluteSearchPath);
        $directoryPath = $this->resolveExistingPath($absoluteSearchPath);

        if (! is_dir($directoryPath)) {
            throw new RuntimeException("Not a directory: {$this->relativePath($directoryPath)}");
        }

        $files = $this->listDirectoryFilesWithRipgrep($directoryPath, $ignore);
        $directories = ['.' => true];
        $filesByDirectory = [];

        foreach ($files as $file) {
            $directory = dirname($file);
            $parts = $directory === '.' ? [] : explode('/', $directory);

            for ($i = 0; $i <= count($parts); $i++) {
                $dirPath = $i === 0 ? '.' : implode('/', array_slice($parts, 0, $i));
                $directories[$dirPath] = true;
            }

            $filesByDirectory[$directory] ??= [];
            $filesByDirectory[$directory][] = basename($file);
        }

        $output = $directoryPath."/\n".$this->renderListDirectoryTree('.', 0, array_keys($directories), $filesByDirectory);

        if (count($files) >= self::LIST_LIMIT) {
            return rtrim($output, "\n")."\n";
        }

        return rtrim($output, "\n")."\n";
    }

    /**
     * @param  array<int, string>|null  $ignore
     * @return list<string>
     */
    private function listDirectoryFilesWithRipgrep(string $directoryPath, ?array $ignore = null): array
    {
        $rgBinary = $this->resolveRipgrepBinaryForList();

        if (! function_exists('proc_open')) {
            throw new RuntimeException('proc_open is not available on this server.');
        }

        $command = [$rgBinary, '--files', '--hidden'];
        $ignorePatterns = array_map(fn (string $pattern): string => '!'.$pattern.'*', self::LIST_IGNORE_PATTERNS);

        foreach ($ignore ?? [] as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern !== '') {
                $ignorePatterns[] = '!'.$pattern;
            }
        }

        foreach ($ignorePatterns as $pattern) {
            $command[] = '-g';
            $command[] = $pattern;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $directoryPath, null, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start ripgrep.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $errorOutput = trim((string) $stderr);
            throw new RuntimeException($errorOutput !== '' ? "ripgrep failed: {$errorOutput}" : 'ripgrep failed');
        }

        $files = preg_split('/\r\n|\r|\n/', trim((string) $stdout)) ?: [];
        $files = array_values(array_filter($files, fn (string $file): bool => $file !== ''));
        sort($files);

        return array_slice($files, 0, self::LIST_LIMIT);
    }

    /**
     * @param  array<int, string>  $directories
     * @param  array<string, array<int, string>>  $filesByDirectory
     */
    private function renderListDirectoryTree(string $directoryPath, int $depth, array $directories, array $filesByDirectory): string
    {
        $indent = str_repeat('  ', $depth);
        $output = '';

        if ($depth > 0) {
            $output .= $indent.basename($directoryPath)."/\n";
        }

        $childIndent = str_repeat('  ', $depth + 1);
        $children = array_values(array_filter($directories, function (string $directory) use ($directoryPath): bool {
            return dirname($directory) === $directoryPath && $directory !== $directoryPath;
        }));
        sort($children);

        foreach ($children as $child) {
            $output .= $this->renderListDirectoryTree($child, $depth + 1, $directories, $filesByDirectory);
        }

        $files = $filesByDirectory[$directoryPath] ?? [];
        sort($files);

        foreach ($files as $file) {
            $output .= $childIndent.$file."\n";
        }

        return $output;
    }

    private function resolveRipgrepBinaryForList(): string
    {
        foreach (['/usr/bin/rg', '/bin/rg'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('ripgrep is not installed on this server.');
    }

    public function deletePath(string $path): string
    {
        $targetPath = $this->resolveExistingPath($path);

        if (is_dir($targetPath)) {
            return $this->deleteDirectoryPath($targetPath);
        }

        if (is_file($targetPath)) {
            return $this->deleteFilePath($targetPath);
        }

        throw new RuntimeException("Not a file or directory: {$this->relativePath($targetPath)}");
    }

    /**
     * @param  array<int, string>  $paths
     */
    public function bulkDeletePaths(array $paths): string
    {
        if ($paths === []) {
            throw new RuntimeException('The "paths" array is empty. Provide at least one file or directory path.');
        }

        if (count($paths) > self::BULK_DELETE_MAX_ITEMS) {
            throw new RuntimeException(sprintf(
                'Too many items (%d). Maximum is %d per call.',
                count($paths),
                self::BULK_DELETE_MAX_ITEMS,
            ));
        }

        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($paths as $index => $path) {
            $path = trim((string) $path);

            if ($path === '') {
                $results[] = sprintf('⨯ [%d] — missing path', $index);
                $failed++;

                continue;
            }

            try {
                $results[] = $this->deletePath($path);
                $succeeded++;
            } catch (RuntimeException $exception) {
                $results[] = sprintf('⨯ %s — %s', $path, $exception->getMessage());
                $failed++;
            }
        }

        return sprintf(
            "Bulk delete: %d succeeded, %d failed.\n\n%s",
            $succeeded,
            $failed,
            implode("\n", $results),
        );
    }

    private function deleteFilePath(string $filePath): string
    {
        $relativePath = $this->relativePath($filePath);
        $size = $this->formatBytes((int) (filesize($filePath) ?: 0));

        if (! File::delete($filePath)) {
            throw new RuntimeException("Failed to delete file: {$relativePath}");
        }

        return sprintf('File deleted: %s (%s)', $relativePath, $size);
    }

    private function deleteDirectoryPath(string $directoryPath): string
    {
        if (! is_dir($directoryPath)) {
            throw new RuntimeException("Not a directory: {$this->relativePath($directoryPath)}");
        }

        $relativePath = $this->relativePath($directoryPath);
        $this->assertDeleteAllowed($relativePath);

        $fileCount = count(File::allFiles($directoryPath));
        $directoryCount = count($this->allDirectoriesRecursive($directoryPath));

        if (! File::deleteDirectory($directoryPath)) {
            throw new RuntimeException("Failed to delete directory: {$relativePath}");
        }

        return sprintf(
            'Directory deleted: %s (%d file(s), %d subdirectory(ies) removed)',
            $relativePath,
            $fileCount,
            $directoryCount,
        );
    }

    private function assertDeleteAllowed(string $relativePath): void
    {
        if ($relativePath === '.' || in_array($relativePath, self::PROTECTED_TOP_LEVEL_DIRECTORIES, true)) {
            throw new RuntimeException("Refusing to delete protected directory: {$relativePath}");
        }
    }
}
