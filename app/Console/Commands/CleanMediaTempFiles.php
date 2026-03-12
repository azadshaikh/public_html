<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;

class CleanMediaTempFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:clean-temp 
                            {--older-than=1 : Delete files older than specified hours (default: 1 hour)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up temporary files from Spatie Media Library temp directory';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hoursOld = (int) $this->option('older-than');
        $dryRun = $this->option('dry-run');

        $this->info(sprintf('Cleaning media temporary files older than %d hour(s)...', $hoursOld));

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will actually be deleted');
        }

        // Get the temporary directory path
        $tempPath = config('media-library.temporary_directory_path', storage_path('media-library/temp'));

        if (! File::exists($tempPath)) {
            $this->info('Temporary directory does not exist: '.$tempPath);

            return 0;
        }

        $cutoffTime = Date::now()->subHours($hoursOld);
        $deletedCount = 0;
        $totalSize = 0;
        $errors = 0;

        $this->info('Scanning directory: '.$tempPath);
        $this->info('Cutoff time: '.$cutoffTime->format('Y-m-d H:i:s'));

        try {
            // Get all directories in temp folder
            $directories = File::directories($tempPath);

            if (empty($directories)) {
                $this->info('No temporary directories found.');

                return 0;
            }

            $this->info('Found '.count($directories).' temporary directories');

            foreach ($directories as $directory) {
                try {
                    $directoryTime = Date::createFromTimestamp(File::lastModified($directory));

                    if ($directoryTime->lt($cutoffTime)) {
                        // Calculate directory size before deletion
                        $size = $this->getDirectorySize($directory);
                        $totalSize += $size;

                        $relativePath = str_replace($tempPath.'/', '', $directory);

                        if ($dryRun) {
                            $this->line(sprintf('Would delete: %s (Size: ', $relativePath).$this->formatBytes($size).sprintf(', Modified: %s)', $directoryTime->format('Y-m-d H:i:s')));
                        } else {
                            File::deleteDirectory($directory);
                            $this->line(sprintf('Deleted: %s (Size: ', $relativePath).$this->formatBytes($size).sprintf(', Modified: %s)', $directoryTime->format('Y-m-d H:i:s')));
                        }

                        $deletedCount++;
                    } else {
                        $this->comment('Keeping recent directory: '.basename((string) $directory).sprintf(' (Modified: %s)', $directoryTime->format('Y-m-d H:i:s')));
                    }
                } catch (Exception $e) {
                    $this->error(sprintf('Error processing directory %s: ', $directory).$e->getMessage());
                    $errors++;
                }
            }
        } catch (Exception $exception) {
            $this->error('Error accessing temporary directory: '.$exception->getMessage());

            return 1;
        }

        // Summary
        $this->newLine();
        if ($dryRun) {
            $this->info('DRY RUN SUMMARY:');
            $this->info(sprintf('Would delete %d directories', $deletedCount));
            $this->info('Would free up: '.$this->formatBytes($totalSize));
        } else {
            $this->info('CLEANUP SUMMARY:');
            $this->info(sprintf('Deleted %d directories', $deletedCount));
            $this->info('Freed up: '.$this->formatBytes($totalSize));
        }

        if ($errors > 0) {
            $this->warn('Errors encountered: '.$errors);
        }

        return 0;
    }

    /**
     * Get the total size of a directory in bytes
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        try {
            $files = File::allFiles($path);
            foreach ($files as $file) {
                $size += $file->getSize();
            }
        } catch (Exception) {
            // If we can't calculate size, return 0
            $size = 0;
        }

        return $size;
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $size, int $precision = 2): string
    {
        if ($size === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, $precision).' '.$units[$unitIndex];
    }
}
