<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Requests\UpdateMediaDetailsRequest;
use App\Models\CustomMedia;
use App\Models\User;
use App\Services\MediaService;
use App\Services\MediaTrashService;
use App\Services\MediaVariationService;
use App\Traits\ActivityTrait;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    use ActivityTrait;
    use ResponseTrait;

    private const string MODULE_TITLE = 'Media';

    private const string MODULE_NAME = 'Media';

    private const string MODULE_PATH = 'app.media';

    public $upload_disk;

    public function __construct(
        private readonly CustomMedia $media,
        private readonly MediaVariationService $mediaVariationService,
        private readonly MediaTrashService $mediaTrashService
    ) {
        $this->upload_disk = config('media-library.disk_name', 'public');
    }

    /**
     * Display media management index page
     */
    public function index($status, Request $request): View
    {
        abort_unless(Auth::user()->can('view_media'), 401);

        $view_data = $this->getViewData('List');
        $view_data['filterdata'] = $request->all();

        $sortable_options = [
            'uploaded_on' => [
                'label' => 'Uploaded On',
                'slug' => 'uploaded_on',
                'options' => [
                    'latest_uploaded' => 'Newest First',
                    'oldest_uploaded' => 'Oldest First',
                ],
            ],
            'size' => [
                'label' => 'Size',
                'slug' => 'size',
                'options' => [
                    'largest' => 'Largest First',
                    'smallest' => 'Smallest First',
                ],
            ],
        ];

        $view_data['sortable_options'] = $sortable_options;
        $view_data['status_slug'] = $status;
        $total_files = $this->media->withoutTrashed()->count();
        $trashed_files = $this->media->onlyTrashed()->count();

        $view_data['total_files'] = $total_files;
        $view_data['trashed_files'] = $trashed_files;
        $view_data['current_total'] = $status === 'trash' ? $trashed_files : $total_files;

        // Handle filtering and sorting
        $query = $this->media::query();

        if ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        // Search
        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%'.$request->search.'%');
        }

        // Filter by MIME type
        if ($request->filled('mime_type')) {
            $query->whereIn('mime_type', (array) $request->mime_type);
        }

        // Filter by user
        if ($request->filled('created_by')) {
            $query->whereIn('created_by', (array) $request->created_by);
        }

        // Sorting
        $sort = $request->query('sort', 'latest_uploaded');
        match ($sort) {
            'oldest_uploaded' => $query->oldest(),
            'largest' => $query->orderBy('size', 'desc'),
            'smallest' => $query->orderBy('size', 'asc'),
            default => $query->latest(),
        };

        $view_data['media_list'] = $query->paginate(20)->withQueryString();

        // Prepare header actions for storage stats (displayed in action area)
        $view_data['header_actions'] = [
            [
                'type' => 'span',
                'label' => 'Used: '.($view_data['storage_data']['used_size_readable'] ?? '0 MB'),
                'icon' => 'ri-hard-drive-line',
                'class' => 'text-muted d-flex align-items-center bg-white border rounded px-3',
            ],
            [
                'type' => 'span',
                'label' => 'Limit: '.($view_data['storage_data']['max_size_readable'] ?? 'Unlimited'),
                'icon' => 'ri-hard-drive-2-line',
                'class' => 'text-muted d-flex align-items-center bg-white border rounded px-3',
            ],
        ];

        /** @var view-string $indexView */
        $indexView = self::MODULE_PATH.'.index';

        return view($indexView, $view_data);
    }

    /**
     * Get upload settings for media files
     */
    public function getUploadSettings(): JsonResponse
    {
        if (! Auth::user()->can('add_media')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $max_upload_size = config('media-library.max_file_size') / (1024 * 1024);
        $accepted_file_types = config('media.media_allowed_file_types');

        $allowed_types = explode(',', $accepted_file_types);

        // Categorize file types for simpler display
        $has_images = false;
        $has_videos = false;
        $has_documents = false;

        foreach ($allowed_types as $type) {
            $type = trim($type);
            if (str_starts_with($type, 'image/')) {
                $has_images = true;
            } elseif (str_starts_with($type, 'video/')) {
                $has_videos = true;
            } elseif (str_starts_with($type, 'application/') || str_starts_with($type, 'text/')) {
                $has_documents = true;
            }
        }

        $friendly_categories = [];
        if ($has_images) {
            $friendly_categories[] = 'Images';
        }

        if ($has_videos) {
            $friendly_categories[] = 'Videos';
        }

        if ($has_documents) {
            $friendly_categories[] = 'Documents';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'max_size_mb' => $max_upload_size,
                'max_size_bytes' => config('media-library.max_file_size'),
                'accepted_mime_types' => $accepted_file_types,
                'accepted_types_array' => $allowed_types,
                'friendly_file_types' => implode(', ', $friendly_categories),
                'max_filename_length' => (int) config('media.max_file_name_length', 100),
                'upload_route' => route('app.media.upload-media'),
            ],
        ]);
    }

    /**
     * Get media details for edit modal (API endpoint for unified media library)
     *
     * @requirements 1.2, 7.1, 7.4
     */
    public function getMediaDetails($id): JsonResponse
    {
        if (! Auth::user()->can('view_media')) {
            return response()->json(['status' => 0, 'error' => 'Unauthorized'], 401);
        }

        try {
            $media = $this->media->where('id', $id)->first();
            if (! $media) {
                return response()->json(['status' => 0, 'error' => 'Media not found'], 404);
            }

            // Get media variations and conversion status
            $mediaVariations = $this->mediaVariationService->getMediaVariations($media);
            $conversionStatus = $this->mediaVariationService->getConversionStatus($media);

            // Get responsive data for images
            $responsiveData = [];
            $availableResponsiveSizes = [];
            if (str_starts_with((string) $media->mime_type, 'image/')) {
                $responsiveData = $this->mediaVariationService->getResponsiveImageData($media);
                $availableResponsiveSizes = $this->mediaVariationService->getAvailableResponsiveSizes($media->id); // optimize later if needed
            }

            // Get image dimensions from custom properties
            $width = $media->getCustomProperty('width', 0);
            $height = $media->getCustomProperty('height', 0);
            $owner = User::query()->find($media->created_by);

            return response()->json([
                'status' => 1,
                'message' => 'Media details retrieved successfully',
                'data' => [
                    'id' => $media->id,
                    'name' => $media->name ?? 'Untitled',
                    'file_name' => $media->file_name ?? '',
                    'mime_type' => $media->mime_type ?? 'unknown',
                    'size' => $media->size ?? 0,
                    'human_readable_size' => $media->human_readable_size ?? '0 B',
                    'created_at' => $media->created_at?->toISOString() ?? '',
                    'updated_at' => $media->updated_at?->toISOString() ?? null,
                    'deleted_at' => $media->deleted_at?->toISOString() ?? null,
                    'original_url' => $mediaVariations['original'] ?? '',
                    'thumbnail_url' => $mediaVariations['thumbnail'] ?? $mediaVariations['original'] ?? '',
                    'webp_url' => $mediaVariations['webp'] ?? $mediaVariations['original'] ?? '',
                    'media_url' => $mediaVariations['webp'] ?? $mediaVariations['original'] ?? '',
                    'variations' => $mediaVariations,
                    'conversion_status' => $conversionStatus,
                    'responsive_data' => $responsiveData,
                    'available_responsive_sizes' => $availableResponsiveSizes,
                    'width' => $width,
                    'height' => $height,
                    'is_small_image' => $media->getCustomProperty('is_small_image', false),
                    'owner' => $owner ? [
                        'id' => $owner->id,
                        'name' => $owner->name,
                        'email' => $owner->email,
                    ] : null,
                    // Custom properties (metadata)
                    'alt_text' => $media->getCustomProperty('alt_text', ''),
                    'caption' => $media->getCustomProperty('caption', ''),
                    'tags' => $media->getCustomProperty('tags', ''),
                    'title' => $media->getCustomProperty('title', ''),
                    'description' => $media->getCustomProperty('description', ''),
                    'seo_title' => $media->getCustomProperty('seo_title', ''),
                    'seo_description' => $media->getCustomProperty('seo_description', ''),
                    'copyright' => $media->getCustomProperty('copyright', ''),
                    'license' => $media->getCustomProperty('license', ''),
                    'focal_point' => $media->getCustomProperty('focal_point', null),
                    'is_processing' => $conversionStatus['status'] === 'processing',
                    'processing_failed' => $conversionStatus['status'] === 'failed',
                    'processing_error' => $conversionStatus['error'] ?? null,
                    'uploaded_on' => $media->created_at_formatted ?? '',
                ],
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to get media details', [
                'media_id' => $id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 0,
                'error' => 'Failed to get media details',
            ], 500);
        }
    }

    /**
     * Upload media files
     */
    public function uploadMediaFiles(StoreMediaRequest $request, MediaService $mediaService)
    {
        // Delegate the entire upload process and response to the service
        return $mediaService->processMediaUpload($request, $this->mediaVariationService);
    }

    /**
     * Get all media with pagination and filtering for AJAX requests
     */
    public function getAllMedia(Request $request, MediaService $mediaService)
    {
        try {
            $params = $request->all();
            $mediaList = $mediaService->getFilteredMediaList($params);

            $transformedItems = [];
            foreach ($mediaList->items() as $media) {
                try {
                    $mediaVariations = $this->mediaVariationService->getMediaVariations($media);
                    $conversionStatus = $this->mediaVariationService->getConversionStatus($media);
                    /** @var User|null $owner */
                    $owner = $media->owner;

                    $responsiveData = [];
                    if (str_starts_with((string) $media->mime_type, 'image/')) {
                        $responsiveData = $this->mediaVariationService->getResponsiveImageData($media);
                    }

                    $transformedItems[] = [
                        'id' => $media->id,
                        'name' => $media->name ?? 'Untitled',
                        'file_name' => $media->file_name ?? '',
                        'mime_type' => $media->mime_type ?? 'unknown',
                        'size' => $media->size ?? 0,
                        'human_readable_size' => $media->human_readable_size ?? '0 B',
                        'created_at' => $media->created_at?->toISOString() ?? '',
                        'updated_at' => $media->updated_at?->toISOString() ?? null,
                        'deleted_at' => $media->deleted_at?->toISOString() ?? null,
                        'original_url' => $mediaVariations['original'] ?? '',
                        'thumbnail_url' => $mediaVariations['thumbnail'] ?? $mediaVariations['original'] ?? '',
                        'webp_url' => $mediaVariations['webp'] ?? $mediaVariations['original'] ?? '',
                        'media_url' => $mediaVariations['webp'] ?? $mediaVariations['original'] ?? '',
                        'variations' => $mediaVariations,
                        'conversion_status' => $conversionStatus,
                        'responsive_data' => $responsiveData,
                        'owner' => $owner ? [
                            'id' => $owner->id,
                            'name' => $owner->name,
                            'email' => $owner->email,
                        ] : null,
                        'tags' => $media->getCustomProperty('tags', ''),
                        'caption' => $media->getCustomProperty('caption', ''),
                        'alt_text' => $media->getCustomProperty('alt_text', ''),
                        'is_processing' => $conversionStatus['status'] === 'processing',
                        'processing_failed' => $conversionStatus['status'] === 'failed',
                        'processing_error' => $conversionStatus['error'] ?? null,
                        'uploaded_on' => $media->created_at_formatted ?? '',
                    ];
                } catch (Exception $e) {
                    Log::warning('Error processing media item: '.$e->getMessage(), [
                        'media_id' => $media->id ?? 'unknown',
                    ]);

                    continue;
                }
            }

            $html_view = '';
            if (count($mediaList->items()) > 0) {
                /** @var view-string $mediaListView */
                $mediaListView = 'app.media.media_list';
                $html_view = view($mediaListView, [
                    'media_list' => $mediaList->items(),
                    'media_conversion' => $request->input('media_conversion', 'original'),
                ])->render();
            } else {
                $html_view = '<div class="alert alert-info">No media found</div>';
            }

            return response()->json([
                'status' => 1,
                'message' => $transformedItems !== [] ? 'Media list fetched successfully' : 'No media found',
                'items' => $transformedItems,
                'html_view' => $html_view,
                'current_page' => $mediaList->currentPage(),
                'last_page' => $mediaList->lastPage(),
                'pagination' => [
                    'total' => $mediaList->total(),
                    'per_page' => $mediaList->perPage(),
                    'current_page' => $mediaList->currentPage(),
                    'last_page' => $mediaList->lastPage(),
                    'from' => $mediaList->firstItem(),
                    'to' => $mediaList->lastItem(),
                    'has_more' => $mediaList->hasMorePages(),
                ],
                // Include routes for client-side operations
                'routes' => [
                    'update' => route('app.media.detail.update'),
                    'details' => route('app.media.details', ['id' => ':id']),
                    'destroy' => route('app.media.destroy', ['id' => ':id']),
                    'restore' => route('app.media.restore', ['id' => ':id']),
                    'upload' => route('app.media.upload-media'),
                ],
            ]);
        } catch (Exception $exception) {
            Log::error('Error in getAllMedia: '.$exception->getMessage(), [
                'request_params' => $request->all(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to fetch media list',
                'items' => [],
                'html_view' => '<div class="alert alert-danger">Failed to load media</div>',
                'current_page' => 1,
                'last_page' => 1,
                'pagination' => [
                    'total' => 0,
                    'per_page' => $params['limit'] ?? 20,
                    'current_page' => 1,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                    'has_more' => false,
                ],
            ]);
        }
    }

    /**
     * Update media details
     */
    public function updateDetails(UpdateMediaDetailsRequest $request)
    {
        if ($request->has('media_id') && ! empty($request->input('media_id'))) {
            $mediaobj = CustomMedia::query()->where('id', $request->input('media_id'))->first();
            if (! is_null($mediaobj)) {
                // Check permissions
                if (! Auth::user()->can('edit_media')) {
                    return response()->json(['status' => 0, 'error' => 'Unauthorized'], 403);
                }

                // Prevent editing trashed media
                if (! empty($mediaobj->deleted_at)) {
                    return response()->json(['status' => 0, 'error' => 'Cannot edit trashed media'], 403);
                }

                $input_arr = [
                    'name' => $request->input('media_name'),
                    'alt_text' => $request->input('media_alt'),
                    'updated_by' => Auth::user()->id,
                    'status' => 'active',
                ];

                if ($request->has('media_alt')) {
                    $mediaobj->setCustomProperty('alt_text', $request->input('media_alt'));
                }

                if ($request->has('media_caption')) {
                    $input_arr['caption'] = $request->input('media_caption');
                    // Set custom property for caption
                    $mediaobj->setCustomProperty('caption', $request->input('media_caption'));
                }

                if ($request->has('media_tags')) {
                    // Set custom property for tags
                    $mediaobj->setCustomProperty('tags', $request->input('media_tags'));
                }

                if ($request->has('media_description')) {
                    // Media table does not have a description column; store in custom properties.
                    $mediaobj->setCustomProperty('description', $request->input('media_description'));
                }

                $update_result = $mediaobj->update($input_arr);

                if ($update_result) {
                    // No cache to invalidate - using real-time data access

                    $this->logActivity($mediaobj, ActivityAction::UPDATE, 'Media details updated');

                    // Return updated media with fresh variations
                    $updatedVariations = $this->mediaVariationService->getMediaVariations($mediaobj);
                    $conversionStatus = $this->mediaVariationService->getConversionStatus($mediaobj);

                    return response()->json([
                        'status' => true,
                        'type' => 'toast',
                        'message' => 'Media details have been updated',
                        'media' => array_merge($mediaobj->fresh()->toArray(), [
                            'variations' => $updatedVariations,
                            'conversion_status' => $conversionStatus,
                        ]),
                    ], 200);
                }
            }
        }

        return response()->json(['status' => 0, 'error' => 'Failed to update media details'], 400);
    }

    /**
     * Delete media (soft delete or permanent)
     */
    public function deleteMedia($media_id, MediaService $mediaService): JsonResponse
    {
        if (! Auth::user()->can('delete_media')) {
            return response()->json(['status' => 0, 'error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $mediaobj = CustomMedia::query()->where('id', $media_id)->withTrashed()->first();
            if (! $mediaobj) {
                return $this->errorResponse('Media not found');
            }

            if (! empty($mediaobj->deleted_at)) {
                // Permanent deletion - remove files and database record
                $this->permanentDeleteMedia($mediaobj, $mediaService);
                $message = 'Media permanently deleted successfully';
            } else {
                // Soft delete with physical file movement

                // First move files to trash folder
                $trashResult = $this->mediaTrashService->moveToTrash($mediaobj);

                if (! empty($trashResult['errors'])) {
                    Log::warning('Some errors occurred while moving files to trash', [
                        'media_id' => $mediaobj->id,
                        'errors' => $trashResult['errors'],
                    ]);
                }

                // Perform database soft delete
                $mediaobj->delete();
                $message = 'Media moved to trash successfully';

                // Log the activity
                if (! empty($trashResult['moved_files'])) {
                    Log::info('Media files physically moved to trash', [
                        'media_id' => $mediaobj->id,
                        'moved_files' => count($trashResult['moved_files']),
                    ]);
                }
            }

            $this->logActivity($mediaobj, ActivityAction::DELETE, $message);
            DB::commit();

            $file_counts = [
                'total_files' => $this->media->withoutTrashed()->count(),
                'trashed_files' => $this->media->onlyTrashed()->count(),
                'used_storage' => $this->media->getUsedStorageSize()['used_size_readable'],
            ];

            return $this->successResponse($message, null, $file_counts);
        } catch (Exception $exception) {
            DB::rollback();
            Log::error('Failed to delete media', [
                'media_id' => $media_id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to delete media: '.$exception->getMessage());
        }
    }

    /**
     * Single media deletion (alias for deleteMedia)
     */
    public function destroy($id, MediaService $mediaService): JsonResponse
    {
        return $this->deleteMedia($id, $mediaService);
    }

    /**
     * Restore trashed media
     */
    public function restore($id): JsonResponse
    {
        abort_unless(Auth::user()->can('edit_media'), 401);

        DB::beginTransaction();
        try {
            $media = $this->media->onlyTrashed()->where('id', $id)->first();
            if (! $media) {
                return $this->errorResponse('Media not found');
            }

            // First restore files from trash folder
            $restoreResult = $this->mediaTrashService->restoreFromTrash($media);

            if (! empty($restoreResult['errors'])) {
                Log::warning('Some errors occurred while restoring files from trash', [
                    'media_id' => $media->id,
                    'errors' => $restoreResult['errors'],
                ]);
            }

            // Perform database restore
            $media->restore();

            // Log the activity
            $this->logActivity($media, ActivityAction::RESTORE, 'Media restored from trash');

            if (! empty($restoreResult['moved_files'])) {
                Log::info('Media files physically restored from trash', [
                    'media_id' => $media->id,
                    'moved_files' => count($restoreResult['moved_files']),
                ]);
            }

            DB::commit();

            $file_counts = [
                'total_files' => $this->media->withoutTrashed()->count(),
                'trashed_files' => $this->media->onlyTrashed()->count(),
                'used_storage' => $this->media->getUsedStorageSize()['used_size_readable'],
            ];

            return $this->successResponse('Media restored successfully', null, $file_counts);
        } catch (Exception $exception) {
            DB::rollback();
            Log::error('Failed to restore media', [
                'media_id' => $id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to restore media: '.$exception->getMessage());
        }
    }

    /**
     * Bulk delete media
     *
     * @requirements 1.5, 6.4 - Bulk operations support
     */
    public function bulkDestroy(Request $request, MediaService $mediaService): JsonResponse
    {
        abort_unless(Auth::user()->can('delete_media'), 401);

        DB::beginTransaction();
        try {
            $ids = $request->ids;
            if (! is_array($ids) || $ids === []) {
                return $this->errorResponse('No media selected');
            }

            $trash_count = 0;
            $permanent_delete_count = 0;
            $errors = [];
            $media_items = $this->media->whereIn('id', $ids)->withTrashed()->get();
            $total = $media_items->count();

            foreach ($media_items as $media) {
                try {
                    if (! empty($media->deleted_at)) {
                        // Permanent deletion - remove files and database record
                        $this->permanentDeleteMedia($media, $mediaService);
                        $permanent_delete_count++;
                    } else {
                        // Soft delete with physical file movement
                        $trashResult = $this->mediaTrashService->moveToTrash($media);

                        if (! empty($trashResult['errors'])) {
                            Log::warning('Some errors occurred while moving files to trash during bulk operation', [
                                'media_id' => $media->id,
                                'errors' => $trashResult['errors'],
                            ]);
                        }

                        // Perform database soft delete
                        $media->delete();
                        $trash_count++;

                        // Log successful file movement
                        if (! empty($trashResult['moved_files'])) {
                            Log::debug('Bulk operation: Media files moved to trash', [
                                'media_id' => $media->id,
                                'moved_files' => count($trashResult['moved_files']),
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'id' => $media->id,
                        'name' => $media->name,
                        'error' => $e->getMessage(),
                    ];
                    Log::warning('Failed to delete media item in bulk operation', [
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            $message = sprintf('Successfully processed: %d moved to trash, %d permanently deleted', $trash_count, $permanent_delete_count);
            if ($errors !== []) {
                $message .= ', '.count($errors).' failed';
            }

            $file_counts = [
                'total_files' => $this->media->withoutTrashed()->count(),
                'trashed_files' => $this->media->onlyTrashed()->count(),
                'used_storage' => $this->media->getUsedStorageSize()['used_size_readable'],
            ];

            return $this->successResponse($message, null, array_merge($file_counts, [
                'processed' => $trash_count + $permanent_delete_count,
                'total' => $total,
                'errors' => $errors,
            ]));
        } catch (Exception $exception) {
            DB::rollback();
            Log::error('Failed to bulk delete media', [
                'ids' => $ids,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to delete media: '.$exception->getMessage());
        }
    }

    /**
     * Bulk restore media from trash
     *
     * @requirements 1.5, 6.4 - Bulk operations support
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('edit_media'), 401);

        DB::beginTransaction();
        try {
            $ids = $request->ids;
            if (! is_array($ids) || $ids === []) {
                return $this->errorResponse('No media selected');
            }

            $restored_count = 0;
            $errors = [];
            $media_items = $this->media->onlyTrashed()->whereIn('id', $ids)->get();
            $total = $media_items->count();

            foreach ($media_items as $media) {
                try {
                    // Restore files from trash folder
                    $restoreResult = $this->mediaTrashService->restoreFromTrash($media);

                    if (! empty($restoreResult['errors'])) {
                        Log::warning('Some errors occurred while restoring files from trash during bulk operation', [
                            'media_id' => $media->id,
                            'errors' => $restoreResult['errors'],
                        ]);
                    }

                    // Perform database restore
                    $media->restore();
                    $restored_count++;

                    // Log the activity
                    $this->logActivity($media, ActivityAction::RESTORE, 'Media restored from trash (bulk operation)');

                    if (! empty($restoreResult['moved_files'])) {
                        Log::debug('Bulk operation: Media files restored from trash', [
                            'media_id' => $media->id,
                            'moved_files' => count($restoreResult['moved_files']),
                        ]);
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'id' => $media->id,
                        'name' => $media->name,
                        'error' => $e->getMessage(),
                    ];
                    Log::warning('Failed to restore media item in bulk operation', [
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            $message = $restored_count.' file(s) restored successfully';
            if ($errors !== []) {
                $message .= ', '.count($errors).' failed';
            }

            $file_counts = [
                'total_files' => $this->media->withoutTrashed()->count(),
                'trashed_files' => $this->media->onlyTrashed()->count(),
                'used_storage' => $this->media->getUsedStorageSize()['used_size_readable'],
            ];

            return $this->successResponse($message, null, array_merge($file_counts, [
                'processed' => $restored_count,
                'total' => $total,
                'errors' => $errors,
            ]));
        } catch (Exception $exception) {
            DB::rollback();
            Log::error('Failed to bulk restore media', [
                'ids' => $ids,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to restore media: '.$exception->getMessage());
        }
    }

    /**
     * Bulk update media metadata
     *
     * @requirements 1.5, 6.4 - Bulk metadata editing
     */
    public function bulkUpdateMetadata(Request $request): JsonResponse
    {
        abort_unless(Auth::user()->can('edit_media'), 401);

        DB::beginTransaction();
        try {
            $ids = $request->ids;
            $metadata = $request->metadata ?? [];

            if (! is_array($ids) || $ids === []) {
                return $this->errorResponse('No media selected');
            }

            if (empty($metadata)) {
                return $this->errorResponse('No metadata provided');
            }

            $updated_count = 0;
            $errors = [];
            $media_items = $this->media->whereIn('id', $ids)->withoutTrashed()->get();
            $total = $media_items->count();

            // Allowed metadata fields for bulk update
            $allowedFields = ['alt_text', 'caption', 'tags', 'title', 'description', 'copyright', 'license'];

            foreach ($media_items as $media) {
                try {
                    $hasChanges = false;

                    foreach ($allowedFields as $field) {
                        if (isset($metadata[$field]) && $metadata[$field] !== '') {
                            // For tags, append to existing tags if specified
                            if ($field === 'tags' && isset($metadata['append_tags']) && $metadata['append_tags']) {
                                $existingTags = $media->getCustomProperty('tags', '');
                                $newTags = $existingTags ? $existingTags.', '.$metadata[$field] : $metadata[$field];
                                $media->setCustomProperty($field, $newTags);
                            } else {
                                $media->setCustomProperty($field, $metadata[$field]);
                            }

                            $hasChanges = true;
                        }
                    }

                    if ($hasChanges) {
                        $media->save();
                        $updated_count++;

                        $this->logActivity($media, ActivityAction::UPDATE, 'Media metadata updated (bulk operation)');
                    }
                } catch (Exception $e) {
                    $errors[] = [
                        'id' => $media->id,
                        'name' => $media->name,
                        'error' => $e->getMessage(),
                    ];
                    Log::warning('Failed to update media metadata in bulk operation', [
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            $message = $updated_count.' file(s) updated successfully';
            if ($errors !== []) {
                $message .= ', '.count($errors).' failed';
            }

            return $this->successResponse($message, null, [
                'processed' => $updated_count,
                'total' => $total,
                'errors' => $errors,
            ]);
        } catch (Exception $exception) {
            DB::rollback();
            Log::error('Failed to bulk update media metadata', [
                'ids' => $ids,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to update media metadata: '.$exception->getMessage());
        }
    }

    // (findAndDeleteMediaFiles method removed; logic now handled by MediaService)
    /**
     * Get conversion status for a specific media item
     */
    public function getConversionStatus($id): JsonResponse
    {
        try {
            $conversionStatus = $this->mediaVariationService->getConversionStatus($id);
            $variations = $this->mediaVariationService->getMediaVariations($id);

            return response()->json([
                'status' => 1,
                'message' => 'Conversion status retrieved successfully',
                'data' => [
                    'conversion_status' => $conversionStatus,
                    'variations' => $variations,
                    'media_id' => $id,
                ],
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to get conversion status', [
                'media_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => 0,
                'error' => 'Failed to get conversion status',
            ], 500);
        }
    }

    /**
     * Get variation configuration for frontend
     */
    public function getVariationConfig(): JsonResponse
    {
        try {
            $config = $this->mediaVariationService->getVariationConfig();

            return response()->json([
                'status' => 1,
                'message' => 'Variation configuration retrieved successfully',
                'data' => $config,
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to get variation config', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => 0,
                'error' => 'Failed to get variation configuration',
            ], 500);
        }
    }

    /**
     * Get responsive image data for a specific media item
     */
    public function getResponsiveImageData($id): JsonResponse
    {
        try {
            $responsiveData = $this->mediaVariationService->getResponsiveImageData($id);

            if ($responsiveData === []) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Media not found or not an image',
                ], 404);
            }

            return response()->json([
                'status' => 1,
                'message' => 'Responsive image data retrieved successfully',
                'data' => $responsiveData,
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to get responsive image data', [
                'media_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => 0,
                'error' => 'Failed to get responsive image data',
            ], 500);
        }
    }

    /**
     * Generate responsive image HTML for editor insertion using Spatie's built-in feature
     */
    public function getResponsiveImageHtml($id): JsonResponse
    {
        try {
            $media = CustomMedia::query()->find($id);
            if (! $media) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Media not found',
                ], 404);
            }

            if (! str_starts_with((string) $media->mime_type, 'image/')) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Media is not an image',
                ], 400);
            }

            // Use Spatie's built-in responsive image generation
            $responsiveHtml = (string) $media();

            // Also get WebP version if available
            $webpHtml = '';
            try {
                $webpHtml = (string) $media('webp');
            } catch (Exception) {
                // WebP conversion might not be available
            }

            return response()->json([
                'status' => 1,
                'message' => 'Responsive image HTML generated successfully',
                'data' => [
                    'html' => $responsiveHtml,
                    'webp_html' => $webpHtml,
                    'media' => [
                        'id' => $media->id,
                        'name' => $media->name,
                        'alt_text' => $media->getCustomProperty('alt_text', ''),
                        'caption' => $media->getCustomProperty('caption', ''),
                        'url' => $media->getUrl(),
                        'mime_type' => $media->mime_type,
                    ],
                ],
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to generate responsive image HTML', [
                'media_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => 0,
                'error' => 'Failed to generate responsive image HTML',
            ], 500);
        }
    }

    /**
     * Clean up orphaned media directories
     */
    public function cleanupOrphanedDirectories()
    {
        try {
            $disk = Storage::disk(config('media-library.disk_name', 'public'));
            $cleaned = 0;
            $errors = 0;

            // Get all media records with their UUIDs
            $existingUuids = CustomMedia::query()->whereNotNull('uuid')->pluck('uuid')->toArray();

            // Get all directories from storage
            $directories = $disk->directories();

            foreach ($directories as $directory) {
                $uuid = basename($directory);

                // Check if this UUID exists in database
                if (! in_array($uuid, $existingUuids) &&
                    (strlen($uuid) === 8 || strlen($uuid) === 36)) { // Old 8-char or new UUID format
                    try {
                        $disk->deleteDirectory($directory);
                        $cleaned++;

                        Log::info('Cleaned orphaned media directory: '.$directory);
                    } catch (Exception $e) {
                        $errors++;
                        Log::warning('Failed to clean orphaned directory: '.$directory, [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json([
                'status' => 1,
                'message' => sprintf('Cleanup completed. Cleaned: %d, Errors: %d', $cleaned, $errors),
                'cleaned' => $cleaned,
                'errors' => $errors,
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status' => 0,
                'error' => 'Cleanup failed: '.$exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Get common view data for media management pages
     */
    private function getViewData(string $action, ?CustomMedia $media = null): array
    {
        $page_title = match ($action) {
            'List' => 'Media Library',
            'Create' => 'Add Media File',
            'Edit' => 'Edit Media',
            default => $media instanceof CustomMedia ? $media->name : 'Media'
        };

        $storage_data = $this->media->getUsedStorageSize();

        $mime_types = DB::table('media')
            ->select('mime_type as label', 'mime_type as value')
            ->groupBy('mime_type')
            ->get()
            ->map(fn ($item): array => [
                'label' => $item->label,
                'value' => $item->value,
            ]);

        // Upload settings for Create and List actions (unified media library)
        $upload_settings = null;
        if (in_array($action, ['Create', 'List'])) {
            $max_upload_size = config('media-library.max_file_size') / (1024 * 1024);
            $accepted_file_types = config('media.media_allowed_file_types');

            $allowed_types = explode(',', $accepted_file_types);

            // Categorize file types for simpler display
            $has_images = false;
            $has_videos = false;
            $has_documents = false;

            foreach ($allowed_types as $type) {
                $type = trim($type);
                if (str_starts_with($type, 'image/')) {
                    $has_images = true;
                } elseif (str_starts_with($type, 'video/')) {
                    $has_videos = true;
                } elseif (str_starts_with($type, 'application/') || str_starts_with($type, 'text/')) {
                    $has_documents = true;
                }
            }

            $friendly_categories = [];
            if ($has_images) {
                $friendly_categories[] = 'Images';
            }

            if ($has_videos) {
                $friendly_categories[] = 'Videos';
            }

            if ($has_documents) {
                $friendly_categories[] = 'Documents';
            }

            $upload_settings = [
                'max_size_mb' => $max_upload_size,
                'max_size_bytes' => config('media-library.max_file_size'),
                'accepted_mime_types' => $accepted_file_types,
                'friendly_file_types' => implode(', ', $friendly_categories),
                'max_filename_length' => (int) config('media.max_file_name_length', 100),
                'upload_route' => route('app.media.upload-media'),
                'settings_route' => route('app.media.upload-settings'),
            ];
        }

        return [
            'module_title' => self::MODULE_TITLE,
            'module_name' => __(self::MODULE_NAME),
            'module_path' => self::MODULE_PATH,
            'module_model' => CustomMedia::class,
            'module_name_singular' => Str::singular(self::MODULE_NAME),
            'module_action' => $action,
            'page_title' => $page_title,
            'media' => $media,
            'owner_options' => User::getAddedByOptions(),
            'storage_data' => $storage_data,
            'mime_types' => $mime_types,
            'upload_settings' => $upload_settings,
            'variation_config' => $this->mediaVariationService->getVariationConfig(),
        ];
    }

    /**
     * Permanently delete media files and database record
     */
    private function permanentDeleteMedia(CustomMedia $media, MediaService $mediaService): void
    {
        $mediaService->permanentlyDeleteMedia($media);
    }
}
