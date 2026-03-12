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
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            new Middleware('permission:view_media', only: ['index', 'data']),
            new Middleware('permission:delete_media', only: ['bulkAction']),
        ];
    }

    /**
     * Display media library index page (HTML view)
     */
    public function index(Request $request, string $status = 'all'): View|JsonResponse
    {
        // DataGrid requests use the status path (/media-library/{status}) and expect JSON.
        // Mirror ScaffoldController behavior to support StatusTabsPlugin (path-based navigation).
        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            $request->merge(['status' => $status]);

            return $this->data($request);
        }

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
            'accepted_mime_types' => $acceptedFileTypes,
            'friendly_file_types' => implode(', ', $friendlyCategories),
            'max_filename_length' => (int) config('media.max_file_name_length', 100),
            'upload_route' => route('app.media.upload-media'),
            'settings_route' => route('app.media.upload-settings'),
        ];

        // Get user options for filter
        $ownerOptions = User::getAddedByOptions();

        return view('app.media-v2.index', [
            'page_title' => self::MODULE_TITLE,
            'breadcrumbs' => [
                ['title' => 'Dashboard', 'url' => route('dashboard')],
                ['title' => 'Media Library', 'url' => null],
            ],
            'status_slug' => $status,
            'storage_data' => $storageData,
            'upload_settings' => $uploadSettings,
            'owner_options' => $ownerOptions,
            'total_files' => $this->media->withoutTrashed()->count(),
            'trashed_files' => $this->media->onlyTrashed()->count(),
            'tableConfig' => $scaffold->toDataGridConfig(),
            'statusTabs' => collect($scaffold->statusTabs())->map(function ($tab): array {
                $tab->url = route('app.media-library.index', $tab->key ?: 'all');

                return $tab->toArray();
            })->all(),
        ]);
    }

    /**
     * Get data for DataGrid (JSON API endpoint)
     */
    public function data(Request $request): JsonResponse
    {
        try {
            $scaffold = $this->scaffold();
            $status = $request->input('status', 'all');
            $search = $request->input('search', '');
            $sort = $request->input('sort_column', 'created_at');
            $direction = $request->input('sort_direction', 'desc');
            $perPage = (int) $request->input('per_page', $scaffold->getPerPage());

            // Build query
            $query = CustomMedia::query();

            // Apply status filter
            if ($status === 'trash') {
                $query->onlyTrashed();
            } else {
                $query->withoutTrashed();
            }

            // Apply search
            if ($search) {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'ilike', sprintf('%%%s%%', $search))
                        ->orWhere('file_name', 'ilike', sprintf('%%%s%%', $search));
                });
            }

            // Apply filters
            $this->applyFilters($query, $request);

            // Eager load relationships to prevent N+1 queries
            $query->with('owner');

            // Apply sorting
            $actualSortColumn = $scaffold->getActualSortColumn($sort) ?? 'created_at';
            $query->orderBy($actualSortColumn, $direction);

            // Paginate
            $paginator = $query->paginate($perPage);

            // Transform items (avoid JsonResource collection wrapping)
            $items = $paginator
                ->getCollection()
                ->map(fn ($media): array => (new MediaLibraryResource($media))->toArray($request))
                ->values()
                ->all();

            // Status tab counts (StatusTabsPlugin reads status_counts/statistics)
            $statusCounts = [
                'total' => CustomMedia::withoutTrashed()->count(),
                'trash' => CustomMedia::onlyTrashed()->count(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'items' => $items,
                    'columns' => $scaffold->toDataGridConfig()['columns'],
                    'filters' => $this->getFiltersWithOptions($scaffold),
                    // DataGrid ActionsPlugin reads `data.actions` for both row + bulk actions.
                    'actions' => $scaffold->toDataGridConfig()['actions'],
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ],
                    'status_counts' => $statusCounts,
                    'current_status' => $status,
                ],
            ]);
        } catch (Exception $exception) {
            Log::error('MediaLibrary data() error: '.$exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load media',
                'error' => config('app.debug') ? $exception->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    /**
     * Handle bulk actions
     */
    public function bulkAction(Request $request): JsonResponse
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

            return response()->json([
                'status' => 'success',
                'message' => sprintf('%d file(s) %s', $count, $actionLabels[$action]),
                'affected' => $count,
                'data' => [
                    'status_counts' => [
                        'total' => CustomMedia::withoutTrashed()->count(),
                        'trash' => CustomMedia::onlyTrashed()->count(),
                    ],
                ],
            ]);
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('MediaLibrary bulkAction error: '.$exception->getMessage(), [
                'action' => $action,
                'ids' => $ids,
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to perform bulk action',
                'error' => config('app.debug') ? $exception->getMessage() : 'An error occurred',
            ], 500);
        }
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

    /**
     * Get filters with dynamic options
     */
    protected function getFiltersWithOptions(MediaDefinition $scaffold): array
    {
        $filters = $scaffold->toDataGridConfig()['filters'];

        // Add user options to created_by filter
        foreach ($filters as &$filter) {
            if ($filter['key'] === 'created_by') {
                // DataGrid supports both {value: label} and [{value, label}] formats.
                // Prefer array format to match platform conventions.
                $rawOptions = User::getAddedByOptions();
                $options = collect($rawOptions);

                // If it's already in [{value,label}] format, keep as-is.
                $first = $options->first();
                if (is_array($first) && array_key_exists('value', $first) && array_key_exists('label', $first)) {
                    $filter['options'] = $options->values()->toArray();

                    continue;
                }

                // Otherwise assume {id: name} (array or collection) and convert.
                $filter['options'] = $options
                    ->map(fn ($name, $id): array => ['value' => (string) $id, 'label' => (string) $name])
                    ->values()
                    ->all();
            }
        }

        return $filters;
    }
}
