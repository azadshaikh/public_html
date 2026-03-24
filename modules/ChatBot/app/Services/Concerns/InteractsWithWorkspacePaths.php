<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services\Concerns;

use Illuminate\Support\Facades\File;
use RuntimeException;

trait InteractsWithWorkspacePaths
{
    private const string WORKSPACE_ACCESS_DENIED_MESSAGE = 'Access denied: path is outside the current workspace.';

    private function workspaceRoot(): string
    {
        return $this->normalizePath(base_path());
    }

    private function resolveExistingPath(string $path): string
    {
        $absolutePath = $this->normalizePath($this->toAbsolutePath($path));
        $this->assertInsideWorkspace($absolutePath);
        $resolvedPath = realpath($absolutePath);

        if ($resolvedPath === false) {
            throw new RuntimeException("Path not found: {$path}");
        }

        $resolvedPath = $this->normalizePath($resolvedPath);
        $this->assertInsideWorkspace($resolvedPath);

        return $resolvedPath;
    }

    private function resolveWritablePath(string $path): string
    {
        $absolutePath = $this->normalizePath($this->toAbsolutePath($path));
        $this->assertInsideWorkspace($absolutePath);
        $parentPath = $this->normalizePath(dirname($absolutePath));
        $this->assertInsideWorkspace($parentPath);
        $parentDirectory = realpath($parentPath);

        if ($parentDirectory === false) {
            $nearestExistingDirectory = $this->findNearestExistingDirectory($parentPath);
            $parentDirectory = $nearestExistingDirectory !== null ? realpath($nearestExistingDirectory) : false;
        }

        if ($parentDirectory === false) {
            throw new RuntimeException('The target directory does not exist and could not be resolved.');
        }

        $normalizedParent = $this->normalizePath($parentDirectory);
        $this->assertInsideWorkspace($normalizedParent);

        return $absolutePath;
    }

    private function toAbsolutePath(string $path): string
    {
        $trimmedPath = trim($path);

        if ($trimmedPath === '') {
            throw new RuntimeException('A file or directory path is required.');
        }

        if (str_starts_with($trimmedPath, DIRECTORY_SEPARATOR)) {
            return $trimmedPath;
        }

        return $this->workspaceRoot().DIRECTORY_SEPARATOR.ltrim($trimmedPath, DIRECTORY_SEPARATOR);
    }

    private function relativePath(string $path): string
    {
        $normalizedPath = $this->normalizePath($path);
        $workspaceRoot = $this->workspaceRoot();

        if ($normalizedPath === $workspaceRoot) {
            return '.';
        }

        return ltrim(substr($normalizedPath, strlen($workspaceRoot)), '/');
    }

    private function isInsideWorkspace(string $path): bool
    {
        $workspaceRoot = $this->workspaceRoot();

        return $path === $workspaceRoot || str_starts_with($path, $workspaceRoot.'/');
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $isAbsolute = str_starts_with($path, '/');
        $parts = explode('/', $path);
        $resolved = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if ($resolved !== [] && end($resolved) !== '..') {
                    array_pop($resolved);
                } elseif (! $isAbsolute) {
                    $resolved[] = '..';
                }

                continue;
            }

            $resolved[] = $part;
        }

        $result = implode('/', $resolved);

        return $isAbsolute ? '/'.$result : ($result !== '' ? $result : '.');
    }

    private function findNearestExistingDirectory(string $path): ?string
    {
        $currentPath = $this->normalizePath($path);
        $workspaceRoot = $this->workspaceRoot();

        while (
            $currentPath !== ''
            && $currentPath !== '.'
            && $currentPath !== '/'
            && ($currentPath === $workspaceRoot || str_starts_with($currentPath, $workspaceRoot.'/'))
        ) {
            if (@is_dir($currentPath)) {
                return $currentPath;
            }

            $parentPath = dirname($currentPath);

            if ($parentPath === $currentPath) {
                break;
            }

            $currentPath = $this->normalizePath($parentPath);
        }

        return @is_dir($workspaceRoot) ? $workspaceRoot : null;
    }

    private function assertInsideWorkspace(string $path): void
    {
        if (! $this->isInsideWorkspace($path)) {
            throw new RuntimeException(self::WORKSPACE_ACCESS_DENIED_MESSAGE);
        }
    }

    /**
     * @return array<int, string>
     */
    private function allDirectoriesRecursive(string $directory): array
    {
        $directories = [];

        foreach (File::directories($directory) as $subDir) {
            $directories[] = $subDir;

            foreach ($this->allDirectoriesRecursive($subDir) as $nested) {
                $directories[] = $nested;
            }
        }

        return $directories;
    }

    /**
     * Check if a filename matches any of the given extensions.
     *
     * Handles compound extensions like "blade.php" and "twig" correctly
     * by checking if the filename ends with ".{ext}" — pathinfo() only
     * returns the last segment (e.g. "php" for "foo.blade.php").
     *
     * @param  array<int, string>  $extensions  Normalized lowercase extensions without leading dots.
     */
    private function fileMatchesExtension(string $filename, array $extensions): bool
    {
        $lowerFilename = strtolower($filename);

        foreach ($extensions as $ext) {
            if (str_ends_with($lowerFilename, '.'.$ext)) {
                return true;
            }
        }

        return false;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), 2).' '.$units[$power];
    }

    private function isBinaryFile(string $filePath, int $headerSize = 8192): bool
    {
        $header = file_get_contents($filePath, false, null, 0, $headerSize);

        return $header !== false && str_contains($header, "\0");
    }

    private function ensureTextFile(string $filePath, string $context = ''): void
    {
        if ($this->isBinaryFile($filePath)) {
            throw new RuntimeException(sprintf(
                '%sCannot operate on binary file: %s.',
                $context !== '' ? $context.': ' : '',
                $this->relativePath($filePath),
            ));
        }
    }

    /**
     * @param  array<int, string>  $extensions
     * @return array<int, string>
     */
    private function normalizeExtensions(array $extensions): array
    {
        if ($extensions === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $ext): string => ltrim(strtolower(trim($ext)), '.'),
            $extensions,
        )));
    }

    private function validateRegexPattern(string $pattern, string $label = 'regex'): void
    {
        $testResult = @preg_match('/'.$pattern.'/', '');

        if ($testResult === false) {
            throw new RuntimeException(sprintf(
                'Invalid %s pattern: %s. Provide a valid PCRE pattern without delimiters.',
                $label,
                preg_last_error_msg(),
            ));
        }
    }

    /**
     * Create a recursive file iterator that respects .gitignore exclusions.
     *
     * @param  int  $mode  RecursiveIteratorIterator mode constant (LEAVES_ONLY, SELF_FIRST, etc.)
     */
    private function createGitignoreFilteredIterator(
        string $directoryPath,
        int $mode = \RecursiveIteratorIterator::LEAVES_ONLY,
    ): \RecursiveIteratorIterator {
        $excludedPaths = $this->buildGitignoreExcludedPaths($directoryPath);

        $directoryIterator = new \RecursiveDirectoryIterator(
            $directoryPath,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS,
        );

        $filteredIterator = new \RecursiveCallbackFilterIterator(
            $directoryIterator,
            function (\SplFileInfo $file) use ($excludedPaths): bool {
                if ($file->isDir()) {
                    return ! in_array(
                        $this->normalizePath($file->getPathname()),
                        $excludedPaths,
                        true,
                    );
                }

                return true;
            },
        );

        return new \RecursiveIteratorIterator($filteredIterator, $mode);
    }

    /**
     * Parse .gitignore (and always include .git/) to build a list of absolute directory
     * paths that should be skipped during recursive file search.
     *
     * Only directory patterns are extracted — file-level ignore rules are not applied
     * since the search already filters by extension and binary detection.
     *
     * @return array<int, string>
     */
    private function buildGitignoreExcludedPaths(string $baseDir): array
    {
        $root = $this->workspaceRoot();
        $gitignorePath = $root.'/.gitignore';
        $dirs = ['.git'];

        if (is_file($gitignorePath)) {
            $lines = file($gitignorePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '' || $line[0] === '#' || $line[0] === '!') {
                    continue;
                }

                $pattern = ltrim($line, '/');
                $pattern = rtrim($pattern, '/*');

                if ($pattern === '' || str_contains($pattern, '*') || str_starts_with($pattern, '.')) {
                    $dotDir = ltrim(rtrim(ltrim($line, '/'), '/*'), '/');
                    if ($dotDir !== '' && str_starts_with($dotDir, '.') && ! str_contains($dotDir, '*') && is_dir($root.'/'.$dotDir)) {
                        $dirs[] = $dotDir;
                    }

                    continue;
                }

                if (is_dir($root.'/'.$pattern)) {
                    $dirs[] = $pattern;
                }
            }
        }

        $paths = [];
        foreach (array_unique($dirs) as $dir) {
            $absolute = $this->normalizePath($baseDir.'/'.$dir);
            if (is_dir($absolute)) {
                $paths[] = $absolute;
            }
        }

        return $paths;
    }
}
