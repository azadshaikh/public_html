<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Definitions\MediaDefinition;
use App\Http\Resources\MediaLibraryResource;
use App\Models\CustomMedia;
use App\Models\User;
use App\Services\MediaService;
use App\Services\MediaTrashService;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MediaLibraryController - DataGrid-based Media Library V2
 *
 * Provides a new media library interface using the DataGrid component.
 * Reuses existing MediaController endpoints for mutations (upload, edit, delete).
 */
class MediaLibraryController extends Controller implements HasMiddleware
{
    use ActivityTrait;

    private const string MODULE_TITLE = 'Media Library';

    public function __construct(private readonly CustomMedia $media, private readonly MediaService $mediaService, private readonly MediaTrashService $mediaTrashService) {}

    /**
     * Middleware for permission control
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_media', only: ['index', 'refreshData']),
            new Middleware('permission:delete_media', only: ['bulkAction']),
        ];
    }

    /**
     * Display media library index page
     */
    public function index(Request $request): Response
    {
        $status = in_array($request->input('status'), ['all', 'trash'], true) ? $request->input('status') : 'all';
        $scaffold = $this->scaffold();
        $storageData = $this->media->getUsedStorageSize();

        // Get upload settings
        $maxUploadSize = config('media-library.max_file_size') / (1024 * 1024);
        $acceptedFileTypes = config('media.media_allowed_file_types');

        // Categorize allowed types for display
        $allowedTypes = explode(',', $acceptedFileTypes);
        $hasImages = false;
        $hasVideos = false;
        $hasDocuments = false;

        foreach ($allowedTypes as $type) {
            $type = trim($type);
            if (str_starts_with($type, 'image/')) {
                $hasImages = true;
            } elseif (str_starts_with($type, 'video/')) {
                $hasVideos = true;
            } elseif (str_starts_with($type, 'application/') || str_starts_with($type, 'text/')) {
                $hasDocuments = true;
            }
        }

        $friendlyCategories = [];
        if ($hasImages) {
            $friendlyCategories[] = 'Images';
        }

        if ($hasVideos) {
            $friendlyCategories[] = 'Videos';
        }

        if ($hasDocuments) {
            $friendlyCategories[] = 'Documents';
        }

        $uploadSettings = [
            'max_size_mb' => $maxUploadSize,
            'max_size_bytes' => config('media-library.max_file_size'),
            'max_files_per_upload' => (int) config('media.max_files_per_upload', 10),
            'accepted_mime_types' => $acceptedFileTypes,
            'friendly_file_types' => implode(', ', $friendlyCategories),
            'max_filename_length' => (int) config('media.max_file_name_length', 100),
            'upload_route' => route('app.media.upload-media'),
        ];

        // Build initial paginated data
        $query = CustomMedia::query();
        if ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        $this->applyFilters($query, $request);

        if ($search = $request->input('search', '')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', sprintf('%%%s%%', $search))
                    ->orWhere('file_name', 'ilike', sprintf('%%%s%%', $search));
            });
        }

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $actualSortColumn = $scaffold->getActualSortColumn($sort) ?? 'created_at';
        $query->with('owner')->orderBy($actualSortColumn, $direction);

        $perPage = (int) $request->input('per_page', $scaffold->getPerPage());
        $paginator = $query->paginate($perPage)->onEachSide(1)->withQueryString();

        // Transform paginated items
        $items = $paginator->through(fn ($media): array => (new MediaLibraryResource($media))->toArray($request));

        // Owner options for filter
        $rawOwnerOptions = User::getAddedByOptions();
        $ownerOptions = collect($rawOwnerOptions)->map(fn ($name, $id): array => [
            'value' => (string) $id,
            'label' => (string) $name,
        ])->values()->all();

        return Inertia::render('media/index', [
            'media' => $items,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $status,
                'mime_type_category' => $request->input('mime_type_category', ''),
                'created_by' => $request->input('created_by', ''),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'view' => $request->input('view', 'cards'),
            ],
            'statistics' => [
                'total' => CustomMedia::withoutTrashed()->count(),
                'trash' => CustomMedia::onlyTrashed()->count(),
            ],
            'uploadSettings' => $uploadSettings,
            'storageData' => $storageData,
            'filterOptions' => [
                'mime_type_category' => [
                    ['value' => 'image', 'label' => 'Images'],
                    ['value' => 'video', 'label' => 'Videos'],
                    ['value' => 'audio', 'label' => 'Audio'],
                    ['value' => 'document', 'label' => 'Documents'],
                ],
                'created_by' => $ownerOptions,
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    /**
     * Handle bulk actions
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => ['required', 'string', 'in:delete,restore,force_delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->input('action');
        $ids = $request->input('ids');

        DB::beginTransaction();
        try {
            $count = 0;
            $mediaItems = CustomMedia::query()->whereIn('id', $ids)->withTrashed()->get();

            foreach ($mediaItems as $media) {
                switch ($action) {
                    case 'delete':
                        if (! $media->trashed()) {
                            $this->mediaTrashService->moveToTrash($media);
                            $media->delete();
                            $count++;
                        }

                        break;

                    case 'restore':
                        if ($media->trashed()) {
                            $this->mediaTrashService->restoreFromTrash($media);
                            $media->restore();
                            $count++;
                        }

                        break;

                    case 'force_delete':
                        if ($media->trashed()) {
                            $this->mediaService->permanentlyDeleteMedia($media);
                            $count++;
                        }

                        break;
                }
            }

            DB::commit();

            $actionLabels = [
                'delete' => 'moved to trash',
                'restore' => 'restored',
                'force_delete' => 'permanently deleted',
            ];

            return back()->with('status', sprintf('%d file(s) %s', $count, $actionLabels[$action]));
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('MediaLibrary bulkAction error: '.$exception->getMessage(), [
                'action' => $action,
                'ids' => $ids,
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to perform bulk action');
        }
    }

    /**
     * AJAX endpoint to refresh media data, statistics, and storage info
     */
    public function refreshData(Request $request): JsonResponse
    {
        $status = in_array($request->input('status'), ['all', 'trash'], true) ? $request->input('status') : 'all';
        $scaffold = $this->scaffold();

        $query = CustomMedia::query();
        if ($status === 'trash') {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        $this->applyFilters($query, $request);

        if ($search = $request->input('search', '')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', sprintf('%%%s%%', $search))
                    ->orWhere('file_name', 'ilike', sprintf('%%%s%%', $search));
            });
        }

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $actualSortColumn = $scaffold->getActualSortColumn($sort) ?? 'created_at';
        $query->with('owner')->orderBy($actualSortColumn, $direction);

        $perPage = (int) $request->input('per_page', $scaffold->getPerPage());
        $paginator = $query->paginate($perPage)->onEachSide(1)->withQueryString();

        $items = $paginator->through(fn ($media): array => (new MediaLibraryResource($media))->toArray($request));

        return response()->json([
            'media' => $items,
            'statistics' => [
                'total' => CustomMedia::withoutTrashed()->count(),
                'trash' => CustomMedia::onlyTrashed()->count(),
            ],
            'storageData' => $this->media->getUsedStorageSize(),
        ]);
    }

    /**
     * Get scaffold definition
     */
    protected function scaffold(): MediaDefinition
    {
        return new MediaDefinition;
    }

    /**
     * Apply filters to query
     */
    protected function applyFilters($query, Request $request): void
    {
        // MIME type category filter
        if ($category = $request->input('mime_type_category')) {
            $query->where(function ($q) use ($category): void {
                match ($category) {
                    'image' => $q->where('mime_type', 'like', 'image/%'),
                    'video' => $q->where('mime_type', 'like', 'video/%'),
                    'audio' => $q->where('mime_type', 'like', 'audio/%'),
                    'document' => $q->where(function ($dq): void {
                        $dq->where('mime_type', 'like', 'application/%')
                            ->orWhere('mime_type', 'like', 'text/%');
                    }),
                    default => null,
                };
            });
        }

        // Owner filter
        if ($createdBy = $request->input('created_by')) {
            $query->where('created_by', $createdBy);
        }

        // Date range filter
        if ($dateFrom = $request->input('created_at_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('created_at_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
    }
}
