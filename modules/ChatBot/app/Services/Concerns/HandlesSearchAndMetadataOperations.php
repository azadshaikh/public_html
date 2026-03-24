<?php

declare(strict_types=1);

namespace Modules\ChatBot\Services\Concerns;

use RuntimeException;

trait HandlesSearchAndMetadataOperations
{
    private const int MAX_GLOB_RESULTS = 100;

    private const int MAX_GREP_LINE_LENGTH = 2000;

    private const int MAX_GREP_MATCHES = 100;

    public function glob(string $pattern, ?string $path = null): string
    {
        $searchPath = $path !== null && trim($path) !== '' ? trim($path) : $this->workspaceRoot();
        $absoluteSearchPath = $this->normalizePath($this->toAbsolutePath($searchPath));
        $this->assertInsideWorkspace($absoluteSearchPath);
        $directoryPath = $this->resolveExistingPath($absoluteSearchPath);

        if (! is_dir($directoryPath)) {
            throw new RuntimeException("Not a directory: {$this->relativePath($directoryPath)}");
        }

        $rgBinary = $this->resolveRipgrepBinary();
        $result = $this->runRipgrepGlobCommand(
            binary: $rgBinary,
            pattern: $pattern,
            directoryPath: $directoryPath,
        );

        if ($result['exitCode'] !== 0) {
            $errorOutput = trim($result['stderr']);
            throw new RuntimeException($errorOutput !== '' ? "ripgrep failed: {$errorOutput}" : 'ripgrep failed');
        }

        $paths = preg_split('/\r\n|\r|\n/', trim($result['stdout'])) ?: [];
        $files = [];
        $truncated = false;

        foreach ($paths as $relativeFilePath) {
            if ($relativeFilePath === '') {
                continue;
            }

            if (count($files) >= self::MAX_GLOB_RESULTS) {
                $truncated = true;
                break;
            }

            $fullPath = $this->normalizePath($directoryPath.DIRECTORY_SEPARATOR.$relativeFilePath);
            $modTime = @filemtime($fullPath);

            $files[] = [
                'path' => $fullPath,
                'mtime' => $modTime === false ? 0 : $modTime,
            ];
        }

        usort($files, fn (array $left, array $right): int => $right['mtime'] <=> $left['mtime']);

        if ($files === []) {
            return 'No files found';
        }

        $output = array_map(
            fn (array $file): string => $file['path'],
            $files,
        );

        if ($truncated) {
            $output[] = '';
            $output[] = '(Results are truncated: showing first '.self::MAX_GLOB_RESULTS.' results. Consider using a more specific path or pattern.)';
        }

        return implode("\n", $output);
    }

    public function grep(string $pattern, ?string $path = null, ?string $include = null): string
    {
        $searchPath = $path !== null && trim($path) !== '' ? trim($path) : $this->workspaceRoot();
        $absoluteSearchPath = $this->normalizePath($this->toAbsolutePath($searchPath));
        $this->assertInsideWorkspace($absoluteSearchPath);
        $directoryPath = $this->resolveExistingPath($absoluteSearchPath);

        if (! is_dir($directoryPath)) {
            throw new RuntimeException("Not a directory: {$this->relativePath($directoryPath)}");
        }

        $rgBinary = $this->resolveRipgrepBinary();

        $result = $this->runRipgrepCommand(
            binary: $rgBinary,
            pattern: $pattern,
            directoryPath: $directoryPath,
            include: $include,
        );

        $output = trim($result['stdout']);
        $exitCode = $result['exitCode'];

        if ($exitCode === 1 || ($exitCode === 2 && $output === '')) {
            return 'No files found';
        }

        if ($exitCode !== 0 && $exitCode !== 2) {
            $errorOutput = trim($result['stderr']);
            throw new RuntimeException($errorOutput !== '' ? "ripgrep failed: {$errorOutput}" : 'ripgrep failed');
        }

        $matches = $this->parseGrepMatches($output);

        if ($matches === []) {
            return 'No files found';
        }

        usort($matches, fn (array $left, array $right): int => $right['modTime'] <=> $left['modTime']);

        $totalMatches = count($matches);
        $truncated = $totalMatches > self::MAX_GREP_MATCHES;
        $finalMatches = $truncated ? array_slice($matches, 0, self::MAX_GREP_MATCHES) : $matches;

        if ($finalMatches === []) {
            return 'No files found';
        }

        $outputLines = [
            'Found '.$totalMatches.' matches'.($truncated ? ' (showing first '.self::MAX_GREP_MATCHES.')' : ''),
        ];

        $currentFile = null;

        foreach ($finalMatches as $match) {
            if ($currentFile !== $match['path']) {
                if ($currentFile !== null) {
                    $outputLines[] = '';
                }

                $currentFile = $match['path'];
                $outputLines[] = $match['path'].':';
            }

            $lineText = $match['lineText'];

            if (mb_strlen($lineText) > self::MAX_GREP_LINE_LENGTH) {
                $lineText = mb_substr($lineText, 0, self::MAX_GREP_LINE_LENGTH).'...';
            }

            $outputLines[] = '  Line '.$match['lineNum'].': '.$lineText;
        }

        if ($truncated) {
            $hiddenCount = $totalMatches - self::MAX_GREP_MATCHES;
            $outputLines[] = '';
            $outputLines[] = '(Results truncated: showing '.self::MAX_GREP_MATCHES.' of '.$totalMatches.' matches ('.$hiddenCount.' hidden). Consider using a more specific path or pattern.)';
        }

        if ($exitCode === 2) {
            $outputLines[] = '';
            $outputLines[] = '(Some paths were inaccessible and skipped)';
        }

        return implode("\n", $outputLines);
    }

    private function resolveRipgrepBinary(): string
    {
        foreach (['/usr/bin/rg', '/bin/rg'] as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('ripgrep is not installed on this server.');
    }

    /**
     * @return array{stdout: string, stderr: string, exitCode: int}
     */
    private function runRipgrepGlobCommand(string $binary, string $pattern, string $directoryPath): array
    {
        if (! function_exists('proc_open')) {
            throw new RuntimeException('proc_open is not available on this server.');
        }

        $command = [
            $binary,
            '--files',
            '--hidden',
            '--glob',
            $pattern,
            '.',
        ];

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

        return [
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
            'exitCode' => $exitCode,
        ];
    }

    /**
     * @return array{stdout: string, stderr: string, exitCode: int}
     */
    private function runRipgrepCommand(string $binary, string $pattern, string $directoryPath, ?string $include = null): array
    {
        if (! function_exists('proc_open')) {
            throw new RuntimeException('proc_open is not available on this server.');
        }

        $command = [
            $binary,
            '-nH',
            '--hidden',
            '--no-messages',
            '--field-match-separator=|',
            '--regexp',
            $pattern,
        ];

        if ($include !== null && trim($include) !== '') {
            $command[] = '--glob';
            $command[] = trim($include);
        }

        $command[] = $directoryPath;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start ripgrep.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
            'exitCode' => $exitCode,
        ];
    }

    /**
     * @return list<array{path: string, modTime: int, lineNum: int, lineText: string}>
     */
    private function parseGrepMatches(string $output): array
    {
        if ($output === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        $matches = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $parts = explode('|', $line, 3);

            if (count($parts) !== 3) {
                continue;
            }

            [$filePath, $lineNumber, $lineText] = $parts;
            $modTime = @filemtime($filePath);

            if ($modTime === false) {
                continue;
            }

            $matches[] = [
                'path' => $filePath,
                'modTime' => $modTime,
                'lineNum' => (int) $lineNumber,
                'lineText' => $lineText,
            ];
        }

        return $matches;
    }

    private const STATS_MAX_FILES = 50000;

    private const STATS_TIME_LIMIT_SECONDS = 10;

    public function stats(string $path = '.'): string
    {
        $root = $this->workspaceRoot();

        if ($path !== '.' && $path !== '') {
            $directoryPath = $this->resolveExistingPath($path);
            if (! is_dir($directoryPath)) {
                throw new RuntimeException("Not a directory: {$this->relativePath($directoryPath)}");
            }
        } else {
            $directoryPath = $root;
        }

        $iterator = $this->createGitignoreFilteredIterator(
            $directoryPath,
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        $totalFiles = 0;
        $totalSize = 0;
        $totalLines = 0;
        $extensionCounts = [];
        $extensionSizes = [];
        $dirCount = 0;
        $truncated = false;
        $startTime = microtime(true);

        foreach ($iterator as $item) {
            // Safety: abort if we've been scanning too long
            if ((microtime(true) - $startTime) > self::STATS_TIME_LIMIT_SECONDS) {
                $truncated = true;
                break;
            }

            if ($item->isDir()) {
                $dirCount++;

                continue;
            }

            if (! $item->isFile()) {
                continue;
            }

            $totalFiles++;

            // Safety: cap total files to prevent runaway scans
            if ($totalFiles > self::STATS_MAX_FILES) {
                $truncated = true;
                break;
            }

            $size = $item->getSize();
            $totalSize += $size;

            $ext = strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION)) ?: '(no ext)';
            $extensionCounts[$ext] = ($extensionCounts[$ext] ?? 0) + 1;
            $extensionSizes[$ext] = ($extensionSizes[$ext] ?? 0) + $size;

            if ($size > 0 && $size < 512 * 1024) {
                if (! $this->isBinaryFile($item->getPathname(), 512)) {
                    $lineCount = @substr_count(file_get_contents($item->getPathname()) ?: '', "\n");
                    $totalLines += $lineCount + 1;
                }
            }
        }

        arsort($extensionCounts);
        $topExtensions = array_slice($extensionCounts, 0, 15, true);

        $scopeLabel = $directoryPath === $root
            ? basename($root)
            : $this->relativePath($directoryPath);

        $filesLabel = number_format($totalFiles).' (excluding gitignored paths)';
        if ($truncated) {
            $filesLabel .= ' — scan stopped early (time/size limit reached, use a subdirectory path for full stats)';
        }

        $info = [
            'Workspace: '.$scopeLabel,
            'Total files: '.$filesLabel,
            'Total directories: '.number_format($dirCount),
            'Total size: '.$this->formatBytes($totalSize),
            'Total lines: '.number_format($totalLines).' (text files only)',
            '',
            'Top file types:',
        ];

        foreach ($topExtensions as $ext => $count) {
            $size = $this->formatBytes($extensionSizes[$ext] ?? 0);
            $info[] = sprintf('  .%-15s %6d files  %10s', $ext, $count, $size);
        }

        if (count($extensionCounts) > 15) {
            $remaining = count($extensionCounts) - 15;
            $info[] = sprintf('  ... and %d more file types', $remaining);
        }

        return implode("\n", $info);
    }
}
