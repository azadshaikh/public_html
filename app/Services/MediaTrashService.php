<?php

namespace App\Services;

use App\Models\CustomMedia;
use App\Support\Media\MediaPathGenerator;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaTrashService
{
    protected MediaPathGenerator $pathGenerator;

    public function __construct()
    {
        $this->pathGenerator = new MediaPathGenerator;
    }

    /**
     * Trash a media item (no-op for files).
     *
     * Files stay in their original location. Only the DB record is soft-deleted.
     * This prevents broken thumbnails/URLs caused by failed directory moves on
     * remote storage (FTP, S3, BunnyCDN).
     */
    public function moveToTrash(CustomMedia $media): array
    {
        Log::info('Media trashed (files unchanged)', [
            'media_id' => $media->id,
            'disk' => $media->disk,
        ]);

        return ['success' => true, 'moved_files' => [], 'errors' => []];
    }

    /**
     * Restore a media item (no-op for files, unless legacy trash move exists).
     *
     * Files normally stay in their original location. However, if files were
     * previously moved to the trash folder by older code, this migrates them
     * back to the regular location.
     */
    public function restoreFromTrash(CustomMedia $media): array
    {
        $results = ['success' => true, 'moved_files' => [], 'errors' => []];

        try {
            $disk = Storage::disk($media->disk);
            $trashPath = rtrim($this->pathGenerator->getTrashBasePath($media), '/');
            $regularPath = rtrim($this->pathGenerator->getNonTrashBasePath($media), '/');

            // If files are stuck in the old trash location, move them back
            if ($disk->exists($trashPath)) {
                $results = $this->moveDirectoryAtomic($disk, $trashPath, $regularPath, $results);

                Log::info('Migrated media files from legacy trash path', [
                    'media_id' => $media->id,
                    'from' => $trashPath,
                    'to' => $regularPath,
                    'method' => $results['method'] ?? 'unknown',
                ]);
            } else {
                Log::info('Media restored (files already at regular path)', [
                    'media_id' => $media->id,
                ]);
            }
        } catch (Exception $exception) {
            $results['errors'][] = 'Failed to restore media files: '.$exception->getMessage();
            Log::error('Error restoring media from trash', [
                'media_id' => $media->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Check if media files exist in trash
     */
    public function existsInTrash(CustomMedia $media): bool
    {
        try {
            $disk = Storage::disk($media->disk);
            $trashPath = $this->pathGenerator->getTrashBasePath($media);

            return $disk->exists($trashPath);
        } catch (Exception $exception) {
            Log::error('Error checking if media exists in trash', [
                'media_id' => $media->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if media files exist in regular location
     */
    public function existsInRegularLocation(CustomMedia $media): bool
    {
        try {
            $disk = Storage::disk($media->disk);
            $regularPath = $this->pathGenerator->getNonTrashBasePath($media);

            return $disk->exists($regularPath);
        } catch (Exception $exception) {
            Log::error('Error checking if media exists in regular location', [
                'media_id' => $media->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Move a directory atomically using rename, with file-by-file fallback.
     *
     * Tries a single rename/move operation first (works for local and FTP).
     * Falls back to file-by-file move if the atomic operation fails.
     */
    protected function moveDirectoryAtomic($disk, string $sourcePath, string $destinationPath, array $results): array
    {
        // Try a direct directory rename via the filesystem adapter
        // Local: uses PHP rename(); FTP: uses ftp_rename() — both support directories.
        try {
            $disk->move($sourcePath, $destinationPath);
            $results['success'] = true;
            $results['method'] = 'directory_rename';
            $results['moved_files'][] = sprintf('%s -> %s', $sourcePath, $destinationPath);

            return $results;
        } catch (Exception $exception) {
            Log::info('Directory-level move failed, falling back to file-by-file', [
                'source' => $sourcePath,
                'destination' => $destinationPath,
                'reason' => $exception->getMessage(),
            ]);
        }

        // Fallback: move files one-by-one
        $files = $disk->allFiles($sourcePath);

        if (empty($files)) {
            $results['success'] = true;
            $results['method'] = 'fallback_empty';

            return $results;
        }

        $moveSuccessful = true;

        foreach ($files as $file) {
            try {
                $relativePath = str_replace($sourcePath.'/', '', $file);
                $destinationFile = $destinationPath.'/'.$relativePath;

                $destinationDir = dirname($destinationFile);
                if (! $disk->exists($destinationDir)) {
                    $disk->makeDirectory($destinationDir);
                }

                $disk->move($file, $destinationFile);
                $results['moved_files'][] = sprintf('%s -> %s', $file, $destinationFile);
            } catch (Exception $e) {
                $results['errors'][] = sprintf('Error moving file %s: ', $file).$e->getMessage();
                $moveSuccessful = false;
            }
        }

        // Clean up empty source directory
        try {
            $remaining = $disk->allFiles($sourcePath);
            if (empty($remaining)) {
                $disk->deleteDirectory($sourcePath);
            }
        } catch (Exception) {
            // Non-critical
        }

        $results['success'] = $moveSuccessful && empty($results['errors']);
        $results['method'] = 'fallback_file_by_file';

        return $results;
    }
}
