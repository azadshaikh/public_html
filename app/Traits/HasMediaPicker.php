<?php

declare(strict_types=1);

namespace App\Traits;

use App\Http\Resources\MediaLibraryResource;
use App\Models\CustomMedia;

/**
 * Provides media picker props for Inertia form pages.
 *
 * Controllers using this trait will return paginated media data
 * when the `picker` query parameter is present. The frontend
 * Datagrid component inside the media-picker dialog consumes
 * this data via Inertia page props.
 */
trait HasMediaPicker
{
    /**
     * Build picker-related Inertia props.
     *
     * Returns pickerMedia, pickerFilters, and uploadSettings when
     * the request contains `?picker=1`. Returns all nulls otherwise.
     *
     * @return array{pickerMedia: mixed, pickerFilters: array<string, mixed>|null, uploadSettings: array<string, mixed>|null}
     */
    protected function getMediaPickerProps(): array
    {
        if (! request()->has('picker')) {
            return [
                'pickerMedia' => null,
                'pickerFilters' => null,
                'uploadSettings' => null,
            ];
        }

        $request = request();
        $query = CustomMedia::query()->withoutTrashed();

        // Search
        if ($search = $request->input('search', '')) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', sprintf('%%%s%%', $search))
                    ->orWhere('file_name', 'ilike', sprintf('%%%s%%', $search));
            });
        }

        // MIME type category filter
        if ($category = $request->input('mime_type_category', '')) {
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

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $query->orderBy($sort, $direction);

        $perPage = (int) $request->input('per_page', 24);
        $paginator = $query->paginate($perPage)->onEachSide(1)->withQueryString();
        $items = $paginator->through(fn ($media): array => (new MediaLibraryResource($media))->toArray($request));

        // Upload settings (config values only — no DB queries)
        $maxUploadSize = config('media-library.max_file_size') / (1024 * 1024);
        $acceptedFileTypes = (string) config('media.media_allowed_file_types');

        $allowedTypes = explode(',', $acceptedFileTypes);
        $seen = [];
        $friendlyCategories = [];

        foreach ($allowedTypes as $type) {
            $type = trim($type);

            if (str_starts_with($type, 'image/') && ! isset($seen['image'])) {
                $friendlyCategories[] = 'Images';
                $seen['image'] = true;
            } elseif (str_starts_with($type, 'video/') && ! isset($seen['video'])) {
                $friendlyCategories[] = 'Videos';
                $seen['video'] = true;
            } elseif ((str_starts_with($type, 'application/') || str_starts_with($type, 'text/')) && ! isset($seen['document'])) {
                $friendlyCategories[] = 'Documents';
                $seen['document'] = true;
            }
        }

        return [
            'pickerMedia' => $items,
            'pickerFilters' => [
                'search' => $request->input('search', ''),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
                'mime_type_category' => $request->input('mime_type_category', ''),
                'picker' => '1',
                'view' => $request->input('view', 'cards'),
            ],
            'uploadSettings' => [
                'max_size_mb' => $maxUploadSize,
                'max_size_bytes' => (int) config('media-library.max_file_size'),
                'accepted_mime_types' => $acceptedFileTypes,
                'friendly_file_types' => implode(', ', $friendlyCategories),
                'max_filename_length' => (int) config('media.max_file_name_length', 100),
                'upload_route' => route('app.media.upload-media'),
            ],
        ];
    }
}
