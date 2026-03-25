<?php

namespace App\Traits;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait InteractWithCustomMedia
{
    use InteractsWithMedia {
        InteractsWithMedia::addMedia as parentAddMedia;
    }

    /**
     * Override addMedia to use consistent file naming
     */
    public function addMedia($file): FileAdder
    {
        return $this->parentAddMedia($file);
    }

    /**
     * Register media conversions for uploaded media
     * Uses Spatie's built-in responsive images feature
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        if (! $media instanceof Media) {
            Log::warning('Media conversion called without media object');

            return;
        }

        // Check system requirements
        if (! $this->canProcessConversions()) {
            Log::warning('Media conversions skipped: system requirements not met', [
                'media_id' => $media->id,
            ]);

            return;
        }

        // Skip non-image files
        if (! $this->isImageFile($media->mime_type)) {
            return;
        }

        // Skip SVG files - they don't need conversions
        if ($media->mime_type === 'image/svg+xml') {
            return;
        }

        // Check if image conversions are enabled in settings
        if (! config('media.image_conversions_enabled', false)) {
            return;
        }

        $quality = $this->getImageQuality();

        // 1. Create thumbnail in WebP format
        $thumbnailWidth = (int) config('media.thumbnail_width', 150);
        $media->setCustomProperty('thumbnail_width', $thumbnailWidth);
        $media->setCustomProperty('thumbnail_height', $thumbnailWidth);

        $conversion = $this->addMediaConversion('thumbnail');
        $conversion->queued();

        $conversion->width($thumbnailWidth);
        $conversion->quality($quality);
        $conversion->format('webp'); // Always WebP for thumbnails

        // 2. Create optimized WebP version (for JPG/PNG originals)
        if (in_array($media->mime_type, ['image/jpeg', 'image/jpg', 'image/png'])) {
            $conversion = $this->addMediaConversion('optimized');
            $conversion->queued();

            $conversion->quality($quality);
            $conversion->format('webp');
        }

        // 3. Create responsive images for the optimized version or original WebP
        $this->createResponsiveImages($media, $quality);

        // Handle icon sizes if specified
        $iconSizes = config('media.icon_conversion_size', '');
        if (! empty($iconSizes) && $media->mime_type === 'image/x-icon') {
            $sizes = explode(',', $iconSizes);
            foreach ($sizes as $size) {
                $dimensions = explode('x', trim($size));
                if (count($dimensions) === 2) {
                    $width = (int) $dimensions[0];
                    $height = (int) $dimensions[1];

                    if ($width > 0 && $height > 0) {
                        $conversion = $this->addMediaConversion(sprintf('icon_%dx%d', $width, $height));
                        $conversion->queued();
                        $conversion->width($width);
                        $conversion->quality($quality);
                    }
                }
            }
        }

        // Mark conversions as processed immediately
        $media->setCustomProperty('conversions_processed', true);
        $media->save();
    }

    /**
     * Legacy method for backward compatibility
     *
     * @deprecated Use shouldGenerateConversion instead
     */
    protected function shouldAddConversion(string $size): bool
    {
        return $this->shouldGenerateConversion($size);
    }

    /**
     * Check if conversion should be generated
     */
    protected function shouldGenerateConversion(string $size): bool
    {
        // Thumbnail is always generated
        if ($size === 'thumbnail') {
            return true;
        }

        // Optimized WebP conversion
        if ($size === 'optimized') {
            return true;
        }

        // Responsive sizes
        if (str_starts_with($size, 'responsive-')) {
            return true;
        }

        // Legacy responsive sizes (for backward compatibility)
        $responsiveSizes = ['small', 'medium', 'large', 'xlarge'];
        if (in_array($size, $responsiveSizes)) {
            return true;
        }

        // Check if it's an icon conversion
        if (str_starts_with($size, 'icon_')) {
            return ! empty(config('media.icon_conversion_size', ''));
        }

        return false;
    }

    /**
     * Legacy method for backward compatibility
     *
     * @deprecated Conversion handling moved to ProcessMediaConversions job
     */
    protected function handleIconConversions(Media $media): void
    {
        // Icon conversions now handled in background job
        Log::info('Icon conversions queued for background processing', [
            'media_id' => $media->id,
        ]);
    }

    /**
     * Create responsive images based on original image dimensions
     */
    private function createResponsiveImages(Media $media, int $quality): void
    {
        // Get image dimensions — prefer pre-stored custom properties (set during upload
        // from the local temp file) to avoid downloading back from FTP/S3.
        try {
            $originalWidth = (int) $media->getCustomProperty('width', 0);
            $originalHeight = (int) $media->getCustomProperty('height', 0);

            // If dimensions aren't pre-stored, fall back to reading from disk
            if ($originalWidth === 0 || $originalHeight === 0) {
                $imageSize = $this->getRemoteSafeImageSize($media);

                if (! $imageSize) {
                    Log::warning('Could not get image dimensions for responsive images', [
                        'media_id' => $media->id,
                        'disk' => $media->disk,
                    ]);

                    return;
                }

                $originalWidth = $imageSize[0];
                $originalHeight = $imageSize[1];

                // Store original dimensions in custom properties
                $media->setCustomProperty('width', $originalWidth);
                $media->setCustomProperty('height', $originalHeight);
                $media->save();
            }

            // Define responsive breakpoints from config
            $responsiveSizes = [
                'small' => (int) config('media.small_width', 400),
                'medium' => (int) config('media.medium_width', 800),
                'large' => (int) config('media.large_width', 1200),
                'xlarge' => (int) config('media.xlarge_width', 1920),
            ];

            // Handle small images (smaller than 400px) differently
            if ($originalWidth <= 400) {
                // For very small images, skip responsive variations
                // Thumbnail is already created in registerMediaConversions()

                // Mark as small image for special handling
                $media->setCustomProperty('is_small_image', true);
                $media->setCustomProperty('skip_responsive_variations', true);
                $media->save();

                Log::info('Small image detected, limited conversions created', [
                    'media_id' => $media->id,
                    'original_width' => $originalWidth,
                    'original_height' => $originalHeight,
                ]);

                return;
            }

            // For normal-sized images, create responsive sizes that are smaller than the original
            foreach ($responsiveSizes as $sizeName => $targetWidth) {
                if ($targetWidth < $originalWidth) {
                    // Calculate proportional height for reference
                    $targetHeight = round($targetWidth / $originalWidth * $originalHeight);

                    // Store the planned dimensions
                    $media->setCustomProperty($sizeName.'_width', $targetWidth);
                    $media->setCustomProperty($sizeName.'_height', $targetHeight);

                    $conversion = $this->addMediaConversion($sizeName);
                    $conversion->queued();

                    $conversion->width($targetWidth);
                    $conversion->quality($quality);
                    $conversion->format('webp'); // Always WebP for responsive images
                }
            }
        } catch (Exception $exception) {
            Log::error('Failed to create responsive images', [
                'media_id' => $media->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Check if system can process media conversions
     */
    private function canProcessConversions(): bool
    {
        // Check if ImageMagick or GD is available
        if (! extension_loaded('imagick') && ! extension_loaded('gd')) {
            return false;
        }

        // Check memory limit for larger files
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit !== '-1') {
            $memoryBytes = $this->convertToBytes($memoryLimit);
            if ($memoryBytes < 128 * 1024 * 1024) { // Less than 128MB
                return false;
            }
        }

        return true;
    }

    /**
     * Get configured image quality
     */
    private function getImageQuality(): int
    {
        return (int) config('media.image_quality', 80);
    }

    /**
     * Get image dimensions in a storage-agnostic way.
     * For local disks, uses getPath() directly.
     * For remote disks (FTP/S3), downloads to a temp file.
     *
     * @return array|false Returns [width, height, type, attr] or false on failure
     */
    private function getRemoteSafeImageSize(Media $media): array|false
    {
        $diskDriver = config(sprintf('filesystems.disks.%s.driver', $media->disk), 'local');

        if ($diskDriver === 'local') {
            // Local disk — use direct path
            $path = $media->getPath();

            return @getimagesize($path);
        }

        // Remote disk — download to temp file
        $tempPath = null;

        try {
            $disk = Storage::disk($media->disk);
            $relativePath = $media->getPathRelativeToRoot();

            if (! $disk->exists($relativePath)) {
                Log::warning('Remote media file not found for dimension reading', [
                    'media_id' => $media->id,
                    'disk' => $media->disk,
                    'path' => $relativePath,
                ]);

                return false;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'media_dim_');
            file_put_contents($tempPath, $disk->get($relativePath));

            return @getimagesize($tempPath);
        } catch (Exception $exception) {
            Log::warning('Failed to read remote image dimensions', [
                'media_id' => $media->id,
                'disk' => $media->disk,
                'error' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Check if the media file is a convertible image
     */
    private function isImageFile(string $mimeType): bool
    {
        $convertibleImageTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/bmp',
            'image/tiff',
            'image/tif',
            'image/webp',
        ];

        return in_array($mimeType, $convertibleImageTypes);
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $number = (int) $memoryLimit;

        switch ($last) {
            case 'g':
                $number *= 1024;
                // fall through
                // no break
            case 'm':
                $number *= 1024;
                // fall through
                // no break
            case 'k':
                $number *= 1024;
        }

        return $number;
    }
}
