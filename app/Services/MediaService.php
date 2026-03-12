<?php

namespace App\Services;

use App\Enums\MediaUploadErrorType;
use App\Models\CustomMedia;
use App\Support\Media\MediaPathGenerator;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    protected $uploadDisk;

    public function __construct()
    {
        $this->uploadDisk = config('media-library.disk_name', 'public');
    }

    /**
     * Handle the upload and creation of a media file, and return a response array.
     */
    public function processMediaUpload($request, $mediaVariationService)
    {
        // Increase limits for large file processing
        $originalTimeLimit = ini_get('max_execution_time');
        $originalMemoryLimit = ini_get('memory_limit');

        try {
            // Set higher limits for large file uploads
            set_time_limit(300); // 5 minutes
            ini_set('memory_limit', '512M');

            if ($request->hasFile('file')) {
                $custom_properties = [];
                if ($request->has('conversion_slug') && ! empty($request->input('conversion_slug'))) {
                    $custom_properties['conversion_slug'] = $request->input('conversion_slug');
                }

                $upload_folder = $request->input('upload_folder', '');

                DB::beginTransaction();
                try {
                    $file = $request->file('file');
                    $org_name = basename((string) $file->getClientOriginalName(), '.'.$file->getClientOriginalExtension());
                    $name = generate_slug('media', 'file_name', $org_name, true, true, true);

                    if (config('media.max_file_name_length', 100) > 0) {
                        $name = substr($name, 0, config('media.max_file_name_length', 100));
                    }

                    $file_name = $name.'.'.$file->getClientOriginalExtension();
                    $media_model = Auth::user();

                    // Pre-read image dimensions from the local temp file BEFORE uploading to remote.
                    // This avoids needing to download the file back from FTP/S3 just to read dimensions.
                    $mimeType = $file->getMimeType() ?? '';
                    if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
                        $localDimensions = @getimagesize($file->getRealPath());
                        if ($localDimensions) {
                            $custom_properties['width'] = $localDimensions[0];
                            $custom_properties['height'] = $localDimensions[1];
                        }
                    }

                    $mediaobj = $media_model->addMedia($file)
                        ->withCustomProperties($custom_properties)
                        ->usingFileName($file_name)
                        ->usingName($org_name)
                        ->toMediaCollection($upload_folder, $this->uploadDisk);

                    CustomMedia::updateCreatedUser($mediaobj->id);

                    $mediaVariations = $mediaVariationService->getMediaVariations($mediaobj->id);
                    $conversionStatus = $mediaVariationService->getConversionStatus($mediaobj->id);

                    $response_data = [
                        'status' => 1,
                        'message' => 'Media uploaded successfully',
                        'variations' => $mediaVariations,
                        'conversion_status' => $conversionStatus,
                        'file' => [
                            'id' => $mediaobj->id,
                            'name' => $mediaobj->name,
                            'file_name' => $mediaobj->file_name,
                            'url' => $mediaobj->getUrl(),
                            'thumb' => $mediaobj->hasGeneratedConversion('thumbnail') ? $mediaobj->getUrl('thumbnail') : $mediaobj->getUrl(),
                            'size' => $mediaobj->size,
                            'type' => $mediaobj->mime_type,
                            'collection' => $mediaobj->collection_name,
                            'alt_text' => $mediaobj->getCustomProperty('alt_text', ''),
                            'caption' => $mediaobj->getCustomProperty('caption', ''),
                            'delete_url' => route('app.media.delete-media', ['media_id' => $mediaobj->id]),
                            'details_url' => route('app.media.details', ['id' => $mediaobj->id]),
                        ],
                    ];

                    DB::commit();

                    // Trigger responsive image generation AFTER transaction is committed
                    // This prevents database timeout issues with large files
                    try {
                        if (str_starts_with((string) $mediaobj->mime_type, 'image/') && $mediaobj->size > 5 * 1024 * 1024) {
                            // For large images, queue the responsive image generation
                            Artisan::call('media-library:regenerate', [
                                '--ids' => [$mediaobj->id],
                                '--force' => true,
                            ]);
                        }
                    } catch (Exception $e) {
                        // Log but don't fail the upload if responsive image generation fails
                        Log::warning('Failed to generate responsive images for large file', [
                            'media_id' => $mediaobj->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    return response()->json($response_data, 200);
                } catch (Exception $e) {
                    DB::rollBack();

                    // Determine error type based on exception
                    $errorType = $this->determineErrorTypeFromException($e);

                    Log::error('Media upload failed', [
                        'error' => $e->getMessage(),
                        'error_type' => $errorType->value,
                        'file' => isset($file) && $file ? [
                            'name' => $file->getClientOriginalName(),
                            'size' => $file->getSize(),
                            'mime' => $file->getMimeType(),
                        ] : 'No file',
                        'custom_properties' => $custom_properties,
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return response()->json([
                        'status' => 0,
                        'error' => 'Failed to upload file: '.$e->getMessage(),
                        'error_type' => $errorType->value,
                    ], 500);
                }
            } else {
                return response()->json(['status' => 0, 'error' => 'No file uploaded'], 400);
            }
        } finally {
            // Restore original PHP limits
            set_time_limit((int) $originalTimeLimit);
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    /**
     * Permanently delete media folder and DB record.
     * Deletes the entire directory in one operation instead of individual files.
     */
    public function permanentlyDeleteMedia(CustomMedia $media): void
    {
        $mediaId = $media->id;
        $mediaName = $media->name;
        $mediaUuid = $media->uuid;
        $deletedPaths = [];
        $errors = [];

        try {
            $disk = Storage::disk($media->disk);
            $pathGenerator = new MediaPathGenerator;

            // Delete the folder from current location (respects trash status)
            $currentBasePath = rtrim($pathGenerator->getBasePath($media), '/');
            if ($disk->exists($currentBasePath)) {
                $disk->deleteDirectory($currentBasePath);
                $deletedPaths[] = $currentBasePath;
            }

            // Also clean up from trash location (safety net)
            $trashBasePath = rtrim($pathGenerator->getTrashBasePath($media), '/');
            if ($trashBasePath !== $currentBasePath && $disk->exists($trashBasePath)) {
                $disk->deleteDirectory($trashBasePath);
                $deletedPaths[] = $trashBasePath;
            }

            // Also clean up from regular location (safety net)
            $regularBasePath = rtrim($pathGenerator->getNonTrashBasePath($media), '/');
            if ($regularBasePath !== $currentBasePath && $disk->exists($regularBasePath)) {
                $disk->deleteDirectory($regularBasePath);
                $deletedPaths[] = $regularBasePath;
            }
        } catch (Exception $exception) {
            $errors[] = 'Folder deletion error: '.$exception->getMessage();
            Log::warning('Folder deletion failed during permanent delete', [
                'media_id' => $mediaId,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $deletedRows = DB::table('media')->where('id', $mediaId)->delete();

            if ($deletedRows > 0) {
                Log::info('Permanently deleted media', [
                    'media_id' => $mediaId,
                    'media_name' => $mediaName,
                    'uuid' => $mediaUuid,
                    'deleted_paths' => $deletedPaths,
                    'errors' => $errors,
                ]);
            } else {
                throw new Exception('No rows were deleted for media ID: '.$mediaId);
            }
        } catch (Exception $exception) {
            Log::error('Database deletion failed for media', [
                'media_id' => $mediaId,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    /**
     * Get filtered, sorted, paginated media list for index/ajax.
     *
     * @requirements 6.3, 6.4 - Real-time search, filtering, and sorting
     */
    public function getFilteredMediaList(array $params = [])
    {
        $status = $params['status'] ?? 'all';
        $search = $params['search'] ?? '';
        $typeFilter = $params['media_type'] ?? $params['type_filter'] ?? '';
        $dateFilter = $params['date_filter'] ?? '';
        $sortBy = $params['sort'] ?? 'latest';
        $limit = (int) ($params['limit'] ?? 20);
        $page = (int) ($params['page'] ?? 1);
        $offset = (int) ($params['offset'] ?? 0);

        if ($offset && ! $params['page']) {
            $page = ($offset / $limit) + 1;
        }

        $query = CustomMedia::with(['owner']);

        if ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ILIKE', '%'.$search.'%')
                    ->orWhere('file_name', 'ILIKE', '%'.$search.'%')
                    ->orWhere('custom_properties->alt_text', 'ILIKE', '%'.$search.'%')
                    ->orWhere('custom_properties->title', 'ILIKE', '%'.$search.'%')
                    ->orWhere('custom_properties->caption', 'ILIKE', '%'.$search.'%')
                    ->orWhere('custom_properties->tags', 'ILIKE', '%'.$search.'%');
            });
        }

        if (! empty($typeFilter) && $typeFilter !== 'all') {
            switch ($typeFilter) {
                case 'image':
                    $query->where('mime_type', 'LIKE', 'image/%');
                    break;
                case 'video':
                    $query->where('mime_type', 'LIKE', 'video/%');
                    break;
                case 'audio':
                    $query->where('mime_type', 'LIKE', 'audio/%');
                    break;
                case 'document':
                    $query->whereIn('mime_type', [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/plain',
                    ]);
                    break;
                case 'archive':
                    $query->where(function ($q): void {
                        $q->where('mime_type', 'LIKE', '%zip%')
                            ->orWhere('mime_type', 'LIKE', '%rar%')
                            ->orWhere('mime_type', 'LIKE', '%tar%');
                    });
                    break;
            }
        }

        if (! empty($dateFilter)) {
            $now = now();
            switch ($dateFilter) {
                case 'today':
                    $query->whereDate('created_at', $now->toDateString());
                    break;
                case 'week':
                    $startOfWeek = now()->startOfWeek();
                    $endOfWeek = now()->endOfWeek();
                    $query->whereBetween('created_at', [$startOfWeek, $endOfWeek]);
                    break;
                case 'month':
                    $query->whereMonth('created_at', $now->month)
                        ->whereYear('created_at', $now->year);
                    break;
                case 'year':
                    $query->whereYear('created_at', $now->year);
                    break;
            }
        }

        // Apply sorting based on sort parameter
        // @requirements 6.4 - Sorting options
        match ($sortBy) {
            'oldest' => $query->oldest(),
            'largest' => $query->orderBy('size', 'desc'),
            'smallest' => $query->orderBy('size', 'asc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            default => $query->latest(),
        };

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * Determine error type from exception
     */
    private function determineErrorTypeFromException(Exception $e): MediaUploadErrorType
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, 'Storage limit') || str_contains($message, 'storage') => MediaUploadErrorType::STORAGE_LIMIT,
            str_contains($message, 'file size') || str_contains($message, 'too large') => MediaUploadErrorType::FILE_SIZE,
            str_contains($message, 'file type') || str_contains($message, 'mime') => MediaUploadErrorType::FILE_TYPE,
            str_contains($message, 'network') || str_contains($message, 'connection') => MediaUploadErrorType::NETWORK,
            default => MediaUploadErrorType::UNKNOWN,
        };
    }
}
