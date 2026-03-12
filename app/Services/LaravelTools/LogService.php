<?php

namespace App\Services\LaravelTools;

use Illuminate\Support\Facades\File;

class LogService
{
    /**
     * Log levels with styling
     */
    protected array $levels = [
        'emergency' => 'danger',
        'alert' => 'danger',
        'critical' => 'danger',
        'error' => 'danger',
        'warning' => 'warning',
        'notice' => 'info',
        'info' => 'info',
        'debug' => 'secondary',
    ];

    /**
     * Get log files
     */
    public function getFiles(): array
    {
        $logPath = storage_path('logs');
        $files = [];

        if (File::isDirectory($logPath)) {
            $logFiles = File::files($logPath);

            foreach ($logFiles as $file) {
                if ($file->getExtension() === 'log') {
                    $files[] = [
                        'name' => $file->getFilename(),
                        'size' => $file->getSize(),
                        'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                        'size_formatted' => $this->formatBytes($file->getSize()),
                    ];
                }
            }
        }

        usort($files, fn (array $a, array $b): int => strcmp($b['modified'], $a['modified']));

        return $files;
    }

    /**
     * Get log entries
     */
    public function getEntries(string $filename, string $level = 'all', int $lines = 100): array
    {
        $logPath = storage_path('logs/'.$filename);

        if (! File::exists($logPath) || ! str_ends_with($filename, '.log')) {
            return [
                'success' => false,
                'error' => __('Log file not found.'),
            ];
        }

        $content = $this->tailFile($logPath, $lines);
        $entries = $this->parseLogEntries($content, $level, $lines);

        return [
            'success' => true,
            'entries' => $entries,
            'levels' => $this->levels,
        ];
    }

    /**
     * Delete log file
     */
    public function delete(string $filename): array
    {
        $logPath = storage_path('logs/'.$filename);

        if (! File::exists($logPath) || ! str_ends_with($filename, '.log')) {
            return [
                'success' => false,
                'error' => __('Log file not found.'),
            ];
        }

        if ($filename === 'laravel.log') {
            File::put($logPath, '');

            return [
                'success' => true,
                'message' => __('Log file cleared successfully.'),
            ];
        }

        File::delete($logPath);

        return [
            'success' => true,
            'message' => __('Log file deleted successfully.'),
        ];
    }

    /**
     * Tail a file (get last N lines)
     */
    public function tailFile(string $path, int $lines = 100): string
    {
        if (! File::exists($path)) {
            return '';
        }

        // For large files, read from the end
        $fileSize = filesize($path);
        if ($fileSize === 0) {
            return '';
        }

        // Read last 500KB or whole file if smaller
        $chunkSize = min($fileSize, 500 * 1024);
        $handle = fopen($path, 'r');
        fseek($handle, max(0, $fileSize - $chunkSize));
        $content = fread($handle, $chunkSize);
        fclose($handle);

        return $content;
    }

    /**
     * Parse log entries
     */
    public function parseLogEntries(string $content, string $level, int $limit): array
    {
        $pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*?)(?=\[\d{4}-\d{2}-\d{2}|$)/s';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $entries = [];
        foreach (array_reverse($matches) as $match) {
            $entryLevel = strtolower($match[3]);

            if ($level !== 'all' && $entryLevel !== $level) {
                continue;
            }

            $entries[] = [
                'datetime' => $match[1],
                'environment' => $match[2],
                'level' => $entryLevel,
                'message' => trim($match[4]),
                'color' => $this->levels[$entryLevel] ?? 'secondary',
            ];

            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << 10 * $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }
}
