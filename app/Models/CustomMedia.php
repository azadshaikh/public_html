<?php

namespace App\Models;

use App\Models\Presenters\MediaPresenter;
use Exception;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $url
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt_text
 * @property string|null $title
 */
class CustomMedia extends Media
{
    use HasFactory;
    use MediaPresenter;
    use SoftDeletes;

    protected $table = 'media';

    protected $guarded = ['id'];

    protected $casts = [
        'custom_properties' => 'array',
        'generated_conversions' => 'array',
        'responsive_images' => 'array',
        'manipulations' => 'array',
    ];

    /**
     * Get the storage root folder for this media item
     */
    public function getStorageRootFolder(): string
    {
        // Null = not set, use config default; empty string = intentionally no prefix (local disk)
        return $this->media_storage_root ?? config('media.media_storage_root', 'media');
    }

    /**
     * Get URL for media, using stored CDN base URL when available.
     *
     * Ensures old media URLs survive CDN or storage provider changes.
     * Local-disk media always uses current config (adapts to domain changes).
     */
    public function getUrl(string $conversionName = ''): string
    {
        if (! empty($this->cdn_base_url)) {
            $path = $this->getPathRelativeToRoot($conversionName);
            $url = rtrim((string) $this->cdn_base_url, '/').'/'.ltrim($path, '/');

            // Cache-busting version parameter (mirrors Spatie's versionUrl behavior)
            if ($this->updated_at && config('media-library.version_urls')) {
                $url .= '?v='.$this->updated_at->timestamp;
            }

            return $url;
        }

        return parent::getUrl($conversionName);
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->getUrl('thumbnail');
    }

    public function getMediumUrl(): ?string
    {
        return $this->getUrl('medium');
    }

    public function getLargeUrl(): ?string
    {
        return $this->getUrl('large');
    }

    /**
     * Relationship to the user who owns this media
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship to the user who last updated this media
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Update the created_by field for a media item
     */
    public static function updateCreatedUser($id, $guard = ''): bool
    {
        $userId = $guard ? Auth::guard($guard)->id() : Auth::id();

        return self::query()->where('id', $id)->update([
            'created_by' => $userId,
            'updated_by' => $userId,
        ]) > 0;
    }

    /**
     * Get used storage size with proper caching
     */
    public static function getUsedStorageSize(): array
    {
        $sizeInBytes = self::withoutTrashed()->sum('size') ?: 0;
        $maxStorageGB = config('media.max_storage_size', -1);
        if ($maxStorageGB > 0) {
            $maxStorageInBytes = $maxStorageGB * 1073741824; // GB to bytes
        } else {
            $maxStorageInBytes = -1; // Unlimited
        }

        return [
            'used_size_bytes' => $sizeInBytes,
            'used_size_readable' => self::formatFileSize($sizeInBytes),
            'max_size_bytes' => $maxStorageInBytes,
            'max_size_readable' => $maxStorageInBytes > 0 ? self::formatFileSize($maxStorageInBytes) : 'Unlimited',
            'percentage_used' => $maxStorageInBytes > 0 ? round($sizeInBytes / $maxStorageInBytes * 100, 2) : 0,
            'remaining_bytes' => $maxStorageInBytes > 0 ? max(0, $maxStorageInBytes - $sizeInBytes) : null,
            'remaining_readable' => $maxStorageInBytes > 0 ? self::formatFileSize(max(0, $maxStorageInBytes - $sizeInBytes)) : 'Unlimited',
        ];
    }

    /**
     * Get all media data with proper filtering and pagination
     */
    public static function getAllData(array $filterOptions = []): LengthAwarePaginator
    {
        $query = self::query()
            ->withoutTrashed()
            ->with(['owner:id,first_name,last_name,email'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (! empty($filterOptions['search'])) {
            $search = $filterOptions['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ILIKE', sprintf('%%%s%%', $search))
                    ->orWhere('file_name', 'ILIKE', sprintf('%%%s%%', $search))
                    ->orWhere('custom_properties->alt_text', 'ILIKE', sprintf('%%%s%%', $search))
                    ->orWhere('custom_properties->title', 'ILIKE', sprintf('%%%s%%', $search))
                    ->orWhere('custom_properties->caption', 'ILIKE', sprintf('%%%s%%', $search))
                    ->orWhere('custom_properties->tags', 'ILIKE', sprintf('%%%s%%', $search));
            });
        }

        if (! empty($filterOptions['mime_types'])) {
            $query->whereIn('mime_type', (array) $filterOptions['mime_types']);
        }

        if (! empty($filterOptions['created_by'])) {
            $query->whereIn('created_by', (array) $filterOptions['created_by']);
        }

        if (! empty($filterOptions['date_from'])) {
            $query->whereDate('created_at', '>=', $filterOptions['date_from']);
        }

        if (! empty($filterOptions['date_to'])) {
            $query->whereDate('created_at', '<=', $filterOptions['date_to']);
        }

        $perPage = $filterOptions['per_page'] ?? 20;

        return $query->paginate($perPage);
    }

    /**
     * Get available MIME types for filtering
     */
    public static function getAvailableMimeTypes(): array
    {
        return self::withoutTrashed()
            ->select('mime_type')
            ->distinct()
            ->orderBy('mime_type')
            ->pluck('mime_type')
            ->map(fn (string $mimeType): array => [
                'value' => $mimeType,
                'label' => self::getMimeTypeLabel($mimeType),
                'category' => self::getMimeTypeCategory($mimeType),
            ])
            ->all();
    }

    /**
     * Get friendly label for MIME type
     */
    public static function getMimeTypeLabel(string $mimeType): string
    {
        $labels = [
            'image/jpeg' => 'JPEG Image',
            'image/jpg' => 'JPG Image',
            'image/png' => 'PNG Image',
            'image/gif' => 'GIF Image',
            'image/webp' => 'WebP Image',
            'image/svg+xml' => 'SVG Vector',
            'video/mp4' => 'MP4 Video',
            'video/webm' => 'WebM Video',
            'application/pdf' => 'PDF Document',
            'text/plain' => 'Text File',
        ];

        return $labels[$mimeType] ?? strtoupper(str_replace(['/', 'application', 'image', 'video'], ['', 'Document', 'Image', 'Video'], $mimeType));
    }

    /**
     * Get category for MIME type
     */
    public static function getMimeTypeCategory(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'Images';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'Videos';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'Audio';
        }

        if (str_starts_with($mimeType, 'application/')) {
            return 'Documents';
        }

        if (str_starts_with($mimeType, 'text/')) {
            return 'Text Files';
        }

        return 'Other';
    }

    /**
     * Format file size in human readable format
     */
    public static function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = (int) floor(log($bytes, 1024));

        return round($bytes / 1024 ** $factor, 2).' '.$units[$factor];
    }

    /**
     * Clear storage usage cache
     */
    public static function clearStorageCache(): void
    {
        Cache::forget('media_used_storage_size');
    }

    /**
     * Check if media has completed processing
     */
    public function isProcessingComplete(): bool
    {
        return $this->getCustomProperty('conversions_processed', false);
    }

    /**
     * Check if media processing failed
     */
    public function hasProcessingFailed(): bool
    {
        return $this->getCustomProperty('conversion_failed', false);
    }

    /**
     * Get processing status
     */
    public function getProcessingStatus(): string
    {
        if ($this->hasProcessingFailed()) {
            return 'failed';
        }

        if ($this->isProcessingComplete()) {
            return 'completed';
        }

        return 'processing';
    }

    /**
     * Get processing error if any
     */
    public function getProcessingError(): ?string
    {
        return $this->getCustomProperty('conversion_error');
    }

    /**
     * Mark processing as complete
     */
    public function markProcessingComplete(): bool
    {
        return $this->forceFill([
            'custom_properties' => array_merge($this->custom_properties ?? [], [
                'conversions_processed' => true,
                'processing_completed_at' => now()->toISOString(),
            ]),
        ])->save();
    }

    /**
     * Mark processing as failed
     */
    public function markProcessingFailed(string $error): bool
    {
        return $this->forceFill([
            'custom_properties' => array_merge($this->custom_properties ?? [], [
                'conversion_failed' => true,
                'conversion_error' => $error,
                'processing_failed_at' => now()->toISOString(),
            ]),
        ])->save();
    }

    /**
     * Get media URL with intelligent fallback mechanism
     *
     * @param  string|null  $conversion  The desired conversion name
     * @param  bool  $usePlaceholder  Whether to return placeholder for missing media
     */
    public function getMediaUrl(?string $conversion = null, bool $usePlaceholder = false): ?string
    {
        try {
            // Fast-path for SVG files - they never have conversions
            if ($this->mime_type === 'image/svg+xml') {
                return $this->getUrl();
            }

            // Build the fallback chain for the requested conversion
            $fallbackChain = $this->getConversionFallbackChain($conversion);

            // Try each conversion in the fallback chain
            foreach ($fallbackChain as $conversionName) {
                if ($conversionName === 'original') {
                    // Always return original as final fallback
                    return $this->getUrl();
                }

                // Check if this conversion exists
                if ($this->hasGeneratedConversion($conversionName)) {
                    return $this->getUrl($conversionName);
                }
            }

            // If no conversions worked, return original
            return $this->getUrl();
        } catch (Exception $exception) {
            Log::warning('Failed to get media URL, falling back to original', [
                'media_id' => $this->id ?? null,
                'conversion' => $conversion,
                'error' => $exception->getMessage(),
            ]);

            // Try to return original URL as last resort
            try {
                return $this->getUrl();
            } catch (Exception $fallbackException) {
                Log::error('Failed to get original media URL', [
                    'media_id' => $this->id ?? null,
                    'error' => $fallbackException->getMessage(),
                ]);

                return $usePlaceholder ? get_placeholder_image_url() : null;
            }
        }
    }

    /**
     * Static method to resolve media URL (for helper function)
     */
    public static function resolveMediaUrl(mixed $media, ?string $conversion = null, bool $usePlaceholder = false): ?string
    {
        if (! $media instanceof Media) {
            return $usePlaceholder ? get_placeholder_image_url() : null;
        }

        // Use the trait method if available
        if (method_exists($media, 'getMediaUrl')) {
            return $media->getMediaUrl($conversion, $usePlaceholder);
        }

        // Fallback to standard getUrl if trait not available
        try {
            return $conversion ? $media->getUrl($conversion) : $media->getUrl();
        } catch (Exception) {
            return $usePlaceholder ? get_placeholder_image_url() : null;
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model): void {
            // Set audit fields from authenticated user
            if (auth()->check()) {
                if ($model->getAttribute('created_by') === null) {
                    $model->setAttribute('created_by', auth()->id());
                }
                if ($model->getAttribute('updated_by') === null) {
                    $model->setAttribute('updated_by', auth()->id());
                }
            }

            // Generate sortable, lowercase ULID (26 chars vs 36 for UUID)
            // Time-prefixed for natural creation-order sorting in folder listings
            $model->uuid = strtolower((string) Str::ulid());

            // Determine disk type once
            $diskDriver = config(sprintf('filesystems.disks.%s.driver', $model->disk), 'local');
            $isLocalDisk = $diskDriver === 'local';

            // Set media_storage_root based on disk type
            if (empty($model->media_storage_root)) {
                if ($isLocalDisk) {
                    // Local disk: no prefix needed (disk URL already includes /storage)
                    $model->media_storage_root = '';
                } else {
                    // Remote disks: use configured root folder for namespacing
                    $model->media_storage_root = config('media.media_storage_root', 'media');
                }
            }

            // Ensure media_storage_root is always relative (no leading slash, no absolute path)
            if (! empty($model->media_storage_root)) {
                $model->media_storage_root = ltrim(str_replace(public_path(), '', $model->media_storage_root), '/');
            }

            // Snapshot CDN base URL for remote disks only
            // Preserves correct URLs even if CDN provider or URL changes later
            // Local-disk media skips this (URLs adapt to domain changes via config)
            if (empty($model->cdn_base_url) && ! $isLocalDisk) {
                $diskUrl = config(sprintf('filesystems.disks.%s.url', $model->disk));
                if (! empty($diskUrl)) {
                    $model->cdn_base_url = rtrim($diskUrl, '/');
                }
            }
        });
        // static::saved(function ($model) {
        //     self::clearStorageCache();
        // });
        // static::deleted(function ($model) {
        //     self::clearStorageCache();
        // });
    }

    /**
     * Scope for active (non-trashed) media
     */
    #[Scope]
    protected function active($query)
    {
        return $query->withoutTrashed();
    }

    /**
     * Scope for images only
     */
    #[Scope]
    protected function images($query)
    {
        return $query->where('mime_type', 'LIKE', 'image/%');
    }

    /**
     * Scope for videos only
     */
    #[Scope]
    protected function videos($query)
    {
        return $query->where('mime_type', 'LIKE', 'video/%');
    }

    /**
     * Scope for documents only
     */
    #[Scope]
    protected function documents($query)
    {
        return $query->where(function ($q): void {
            $q->where('mime_type', 'LIKE', 'application/%')
                ->orWhere('mime_type', 'LIKE', 'text/%');
        });
    }

    /**
     * Build the fallback chain for a given conversion
     */
    protected function getConversionFallbackChain(?string $conversion): array
    {
        // If no conversion requested, just return original
        if ($conversion === null) {
            return ['original'];
        }

        // Define the size hierarchy
        $sizeHierarchy = ['xlarge', 'large', 'medium', 'small'];

        // Special handling for thumbnail
        if ($conversion === 'thumbnail') {
            return ['thumbnail', 'optimized', 'original'];
        }

        // Handle known size variants
        if (in_array($conversion, $sizeHierarchy)) {
            $chain = [$conversion];
            $smallerSizes = $this->getSmallerSizeConversions($conversion);
            $chain = array_merge($chain, $smallerSizes);
            $chain[] = 'optimized';
            $chain[] = 'original';

            return $chain;
        }

        // For custom/unknown conversions
        return [$conversion, 'optimized', 'original'];
    }

    /**
     * Get smaller size conversions for a given size
     */
    protected function getSmallerSizeConversions(string $requestedSize): array
    {
        $sizeHierarchy = ['xlarge', 'large', 'medium', 'small'];

        // Find the position of the requested size
        $position = array_search($requestedSize, $sizeHierarchy, true);

        // If not found or it's the smallest, return empty
        if ($position === false || $position === count($sizeHierarchy) - 1) {
            return [];
        }

        // Return all smaller sizes
        return array_slice($sizeHierarchy, $position + 1);
    }
}
