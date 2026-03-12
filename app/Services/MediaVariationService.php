<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaVariationService
{
    /**
     * Supported variation sizes with their default dimensions
     * Simple naming scheme: small, medium, large, xlarge
     */
    private const array VARIATION_SIZES = [
        'thumbnail' => ['width' => 150, 'height' => 150],   // UI previews, media library grid
        'small' => ['width' => 400, 'height' => 300],       // Max width 400px
        'medium' => ['width' => 800, 'height' => 600],      // Max width 800px
        'large' => ['width' => 1200, 'height' => 900],      // Max width 1200px
        'xlarge' => ['width' => 1920, 'height' => 1440],    // Max width 1920px
    ];

    /**
     * Supported output formats
     */
    private const array SUPPORTED_FORMATS = [
        'webp' => ['quality_range' => [60, 90], 'default_quality' => 80],
        'jpeg' => ['quality_range' => [60, 95], 'default_quality' => 85],
        'png' => ['quality_range' => [60, 100], 'default_quality' => 90],
    ];

    /**
     * Get optimized media URL for a specific variation and format
     */
    public function getOptimizedMediaUrl(
        mixed $mediaId,
        string $variation = 'original',
        string $format = 'webp'
    ): string {
        // No cache - always generate fresh URLs for real-time file access
        return $this->generateOptimizedUrl($mediaId, $variation);
    }

    /**
     * Get all available variations for a media item
     */
    public function getMediaVariations(mixed $mediaId): array
    {
        // No cache - always check files in real-time
        $media = $mediaId instanceof Media ? $mediaId : Media::query()->find($mediaId);
        if (! $media) {
            return [];
        }

        $variations = ['original' => $this->getOriginalUrl($media)];

        // Check for optimized conversion (WebP)
        if ($media->hasGeneratedConversion('optimized')) {
            $variations['optimized'] = $media->getUrl('optimized');
        }

        // Check if this is a small image that doesn't need full responsive variations
        $isSmallImage = $media->getCustomProperty('is_small_image', false);
        $skipResponsive = $media->getCustomProperty('skip_responsive_variations', false);

        if ($isSmallImage || $skipResponsive) {
            // For small images, only include available variations and always include thumbnail
            if ($media->hasGeneratedConversion('thumbnail')) {
                $variations['thumbnail'] = $media->getUrl('thumbnail');
            }

            // Log for debugging
            Log::debug('Small image variations generated', [
                'media_id' => $mediaId,
                'is_small_image' => $isSmallImage,
                'available_variations' => array_keys($variations),
            ]);

            return $variations;
        }

        // For normal images, check all responsive sizes
        foreach (array_keys(self::VARIATION_SIZES) as $size) {
            if ($media->hasGeneratedConversion($size)) {
                $variations[$size] = $media->getUrl($size);
            }

            // Skip missing conversions instead of using placeholders
        }

        return $variations;
    }

    /**
     * Get responsive image data for modern web delivery
     */
    public function getResponsiveImageData(mixed $mediaId): array
    {
        $media = $mediaId instanceof Media ? $mediaId : Media::query()->find($mediaId);
        if (! $media || ! $this->isImage($media->mime_type)) {
            return [];
        }

        $variations = $this->getMediaVariations($media);

        // Check if this is a small image
        $isSmallImage = $media->getCustomProperty('is_small_image', false);
        $originalWidth = $media->getCustomProperty('width', 0);

        if ($isSmallImage || $originalWidth <= 400) {
            // For small images, use simplified responsive data
            return [
                'src' => $variations['optimized'] ?? $variations['original'],
                'srcset' => $variations['original'], // Single source for small images
                'sizes' => '(max-width: 400px) 400px, 400px', // Simple sizes attribute
                'alt' => $media->getCustomProperty('alt_text', ''),
                'loading' => 'lazy',
                'width' => $originalWidth ?: 400,
                'height' => $media->getCustomProperty('height', 0) ?: 300,
                'optimized_srcset' => '', // No optimized srcset for small images
                'has_optimized' => $this->hasOptimizedConversion($media),
                'is_small_image' => true,
            ];
        }

        // For normal images, use full responsive data
        return [
            'src' => $variations['optimized'] ?? $variations['medium'] ?? $variations['original'],
            'srcset' => $this->buildSrcSet($variations, $media),
            'sizes' => $this->getResponsiveSizes($media),
            'alt' => $media->getCustomProperty('alt_text', ''),
            'loading' => 'lazy',
            'width' => $this->getConfiguredWidth('medium'),
            'height' => $this->getConfiguredHeight('medium'),
            'optimized_srcset' => $this->buildOptimizedSrcSet($variations, $media),
            'has_optimized' => $this->hasOptimizedConversion($media),
            'is_small_image' => false,
        ];
    }

    /**
     * Generate responsive image HTML with picture element for better performance
     */
    public function generateResponsiveImageHtml($mediaId, array $options = []): string
    {
        // Handle both Media object and ID
        if ($mediaId instanceof Media) {
            $media = $mediaId;
        } else {
            $media = Media::query()->find($mediaId);
        }

        if (! $media || ! $this->isImage($media->mime_type)) {
            return '';
        }

        $responsive = $this->getResponsiveImageData($mediaId);
        $alt = $options['alt'] ?? $responsive['alt'] ?? '';
        $class = $options['class'] ?? '';
        $style = $options['style'] ?? '';

        // Check if this is a small image
        if ($responsive['is_small_image'] ?? false) {
            // For small images, use simple img tag without picture element
            return sprintf(
                '<img src="%s" alt="%s" loading="lazy" %s %s>',
                $responsive['src'],
                htmlspecialchars($alt),
                $class ? 'class="'.htmlspecialchars($class).'"' : '',
                $style ? 'style="'.htmlspecialchars((string) $style).'"' : ''
            );
        }

        // Build picture element with optimized WebP support for normal images
        $html = '<picture>';

        // Optimized WebP sources if available
        if (! empty($responsive['optimized_srcset'])) {
            $html .= sprintf(
                '<source srcset="%s" sizes="%s" type="image/webp">',
                $responsive['optimized_srcset'],
                $responsive['sizes']
            );
        }

        // Fallback image
        $html .= sprintf(
            '<img src="%s" srcset="%s" sizes="%s" alt="%s" loading="lazy" %s %s>',
            $responsive['src'],
            $responsive['srcset'],
            $responsive['sizes'],
            htmlspecialchars($alt),
            $class ? 'class="'.htmlspecialchars($class).'"' : '',
            $style ? 'style="'.htmlspecialchars((string) $style).'"' : ''
        );

        return $html.'</picture>';
    }

    /**
     * Test method to verify srcset generation for a specific media ID
     */
    public function testSrcsetGeneration(int $mediaId): array
    {
        $media = Media::query()->find($mediaId);
        if (! $media) {
            return ['error' => 'Media not found'];
        }

        $variations = $this->getMediaVariations($media);
        $responsive = $this->getResponsiveImageData($media);

        return [
            'media_id' => $mediaId,
            'original_dimensions' => [
                'width' => $media->getCustomProperty('width'),
                'height' => $media->getCustomProperty('height'),
            ],
            'variations' => $variations,
            'srcset' => $responsive['srcset'] ?? '',
            'optimized_srcset' => $responsive['optimized_srcset'] ?? '',
            'sizes' => $responsive['sizes'] ?? '',
            'conversion_dimensions' => $this->debugConversionDimensions($mediaId),
        ];
    }

    /**
     * Debug method to log actual conversion dimensions
     */
    public function debugConversionDimensions(int $mediaId): array
    {
        $media = Media::query()->find($mediaId);
        if (! $media) {
            return [];
        }

        $debug = [
            'media_id' => $mediaId,
            'original_width' => $media->getCustomProperty('width', 'unknown'),
            'original_height' => $media->getCustomProperty('height', 'unknown'),
            'conversions' => [],
        ];

        foreach (array_keys(self::VARIATION_SIZES) as $size) {
            if ($media->hasGeneratedConversion($size)) {
                // Get actual file dimensions
                $actualFileDimensions = null;
                try {
                    $imageSize = $this->getRemoteSafeConversionImageSize($media, $size);
                    if ($imageSize) {
                        $actualFileDimensions = [
                            'width' => (int) $imageSize[0],
                            'height' => (int) $imageSize[1],
                        ];
                    } else {
                        $actualFileDimensions = ['error' => 'Could not read dimensions'];
                    }
                } catch (Exception $e) {
                    $actualFileDimensions = ['error' => $e->getMessage()];
                }

                $debug['conversions'][$size] = [
                    'configured_width' => $this->getConfiguredWidth($size),
                    'actual_width_from_method' => $this->getActualConversionWidth($media, $size),
                    'actual_file_dimensions' => $actualFileDimensions,
                    'url' => $media->getUrl($size),
                    'custom_properties' => [
                        $size.'_width' => $media->getCustomProperty($size.'_width'),
                        sprintf('actual_%s_width', $size) => $media->getCustomProperty(sprintf('actual_%s_width', $size)),
                        sprintf('conversion_%s_width', $size) => $media->getCustomProperty(sprintf('conversion_%s_width', $size)),
                        $size.'_actual_width' => $media->getCustomProperty($size.'_actual_width'),
                        sprintf('generated_%s_width', $size) => $media->getCustomProperty(sprintf('generated_%s_width', $size)),
                    ],
                ];
            }
        }

        if (config('app.debug')) {
            Log::info('Conversion dimensions debug', $debug);
        }

        return $debug;
    }

    /**
     * Get variation configuration for JavaScript/frontend
     */
    public function getVariationConfig(): array
    {
        return [
            'sizes' => $this->getConfiguredSizes(),
            'formats' => array_keys(self::SUPPORTED_FORMATS),
            'quality_settings' => $this->getQualitySettings(),
            'upload_settings' => $this->getUploadSettings(),
        ];
    }

    /**
     * Clear any cached data (no-op since we removed caching)
     */
    public function invalidateMediaCache(int $mediaId): void
    {
        // No cache to invalidate - method kept for compatibility
    }

    /**
     * Get conversion status for a media item
     */
    public function getConversionStatus(mixed $mediaId): array
    {
        // No cache - always check real-time conversion status
        $media = $mediaId instanceof Media ? $mediaId : Media::query()->find($mediaId);
        if (! $media) {
            return [
                'status' => 'not_found',
                'conversions' => [],
                'pending' => [],
                'failed' => [],
            ];
        }

        // If conversions are disabled, report as completed (nothing to do)
        if (! config('media.image_conversions_enabled', false)) {
            return [
                'status' => 'completed',
                'conversions' => [],
                'pending' => [],
                'failed' => [],
            ];
        }

        $status = [
            'status' => 'completed',
            'conversions' => [],
            'pending' => [],
            'failed' => [],
        ];

        // Check if this is a small image
        $isSmallImage = $media->getCustomProperty('is_small_image', false);

        if ($isSmallImage) {
            // For small images, only check for thumbnail and optimized
            $conversionsToCheck = ['optimized', 'thumbnail'];
        } else {
            // Check each conversion including optimized
            $conversionsToCheck = array_merge(['optimized'], array_keys(self::VARIATION_SIZES));
        }

        foreach ($conversionsToCheck as $size) {
            if ($this->shouldHaveVariation($size, $media)) {
                if ($media->hasGeneratedConversion($size)) {
                    $status['conversions'][] = $size;
                } else {
                    $status['pending'][] = $size;
                    $status['status'] = 'processing';
                }
            }
        }

        // Check for processing failures
        if ($media->getCustomProperty('conversion_failed')) {
            $status['status'] = 'failed';
            $status['error'] = $media->getCustomProperty('conversion_error');
        }

        return $status;
    }

    /**
     * Get available responsive sizes for a specific media item
     * Only returns sizes that actually exist or should exist for this media
     */
    public function getAvailableResponsiveSizes(int $mediaId): array
    {
        $media = Media::query()->find($mediaId);
        if (! $media || ! $this->isImage($media->mime_type)) {
            return [];
        }

        $availableSizes = [];
        $originalWidth = $media->getCustomProperty('width', 0);
        $isSmallImage = $media->getCustomProperty('is_small_image', false);

        // Define size descriptions
        $sizeDescriptions = [
            'thumbnail' => 'Thumbnail ('.config('media.thumbnail_width', 150).'px width)',
            'small' => 'Small (400px width)',
            'medium' => 'Medium (800px width)',
            'large' => 'Large (1200px width)',
            'xlarge' => 'Extra Large (1920px width)',
            'optimized' => 'Optimized WebP',
        ];

        // Always include thumbnail if it exists
        if ($media->hasGeneratedConversion('thumbnail')) {
            $availableSizes['thumbnail'] = $sizeDescriptions['thumbnail'];
        }

        // Include optimized version if it exists
        if ($media->hasGeneratedConversion('optimized')) {
            $availableSizes['optimized'] = $sizeDescriptions['optimized'];
        }

        // For small images, don't include responsive variations
        if ($isSmallImage || $originalWidth <= 400) {
            return $availableSizes;
        }

        // For normal images, check each responsive size
        foreach (['small', 'medium', 'large', 'xlarge'] as $size) {
            $configuredWidth = $this->getConfiguredWidth($size);

            // Only include sizes that are smaller than the original image
            if ($originalWidth > $configuredWidth && $media->hasGeneratedConversion($size)) {
                $availableSizes[$size] = $sizeDescriptions[$size];
            }
        }

        return $availableSizes;
    }

    /**
     * Check if media has optimized conversion
     */
    private function hasOptimizedConversion(Media $media): bool
    {
        return $media->hasGeneratedConversion('optimized');
    }

    /**
     * Get actual width of a generated conversion
     */
    private function getActualConversionWidth(Media $media, string $size): int
    {
        if (! $media->hasGeneratedConversion($size)) {
            return $this->getConfiguredWidth($size);
        }

        // Prefer any already-stored metadata (fast, no IO)
        $actualWidth = $media->getCustomProperty(sprintf('actual_%s_width', $size));
        if ($actualWidth && (int) $actualWidth > 0) {
            return (int) $actualWidth;
        }

        $plannedWidth = $media->getCustomProperty($size.'_width');
        if ($plannedWidth && (int) $plannedWidth > 0) {
            return (int) $plannedWidth;
        }

        foreach ([sprintf('conversion_%s_width', $size), $size.'_actual_width', sprintf('generated_%s_width', $size)] as $property) {
            $width = $media->getCustomProperty($property);
            if ($width && (int) $width > 0) {
                return (int) $width;
            }
        }

        // Try to get actual file dimensions via storage-agnostic method
        try {
            $imageSize = $this->getRemoteSafeConversionImageSize($media, $size);
            if ($imageSize && isset($imageSize[0])) {
                return (int) $imageSize[0];
            }

            Log::warning('Conversion file not found or unreadable', [
                'media_id' => $media->id,
                'size' => $size,
                'disk' => $media->disk,
            ]);
        } catch (Exception $exception) {
            Log::warning('Could not read actual conversion dimensions', [
                'media_id' => $media->id,
                'size' => $size,
                'error' => $exception->getMessage(),
            ]);
        }

        // Final fallback to configured width
        return $this->getConfiguredWidth($size);
    }

    /**
     * Build optimized WebP srcset for modern browsers
     */
    private function buildOptimizedSrcSet(array $variations, ?Media $media = null): string
    {
        $srcSet = [];
        $sizeOrder = ['small', 'medium', 'large', 'xlarge'];

        // Add responsive variations that actually exist
        foreach ($sizeOrder as $size) {
            if (isset($variations[$size]) && ! empty($variations[$size])) {
                $width = $media instanceof Media ? $this->getActualConversionWidth($media, $size) : $this->getConfiguredWidth($size);
                $srcSet[] = sprintf('%s %dw', $variations[$size], $width);
            }
        }

        // Always include optimized image if it exists and is larger than our largest responsive variation
        if ($media && isset($variations['optimized'])) {
            $originalWidth = $media->getCustomProperty('width', 0);
            $largestResponsiveWidth = 0;

            // Find the largest responsive width we have
            foreach ($sizeOrder as $size) {
                if (isset($variations[$size]) && ! empty($variations[$size])) {
                    $largestResponsiveWidth = $this->getConfiguredWidth($size);
                }
            }

            // Include optimized if it's larger than our largest responsive variation
            if ($originalWidth > $largestResponsiveWidth) {
                $srcSet[] = sprintf('%s %sw', $variations['optimized'], $originalWidth);
            }
        }

        // If no responsive variations but we have optimized, include it
        if ($srcSet === [] && isset($variations['optimized']) && $media) {
            $originalWidth = $media->getCustomProperty('width', 0);
            if ($originalWidth > 0) {
                $srcSet[] = sprintf('%s %sw', $variations['optimized'], $originalWidth);
            }
        }

        return implode(', ', $srcSet);
    }

    /**
     * Get image dimensions for a conversion in a storage-agnostic way.
     * For local disks, reads from the filesystem directly.
     * For remote disks (FTP/S3), downloads to a temp file.
     *
     * @return array|false Returns [width, height, type, attr] or false
     */
    private function getRemoteSafeConversionImageSize(Media $media, string $conversionName): array|false
    {
        $diskDriver = config(sprintf('filesystems.disks.%s.driver', $media->disk), 'local');

        if ($diskDriver === 'local') {
            // Local disk — try direct path first
            $conversionPath = $media->getPath($conversionName);

            if (file_exists($conversionPath)) {
                return @getimagesize($conversionPath);
            }

            // Fallback: try constructing from URL
            $url = $media->getUrl($conversionName);
            $parsedUrl = parse_url($url);
            $relativePath = ltrim($parsedUrl['path'] ?? '', '/');
            $localPath = public_path($relativePath);

            if (file_exists($localPath)) {
                return @getimagesize($localPath);
            }

            return false;
        }

        // Remote disk — download to temp file
        $tempPath = null;

        try {
            $disk = Storage::disk($media->disk);
            $relativePath = $media->getPathRelativeToRoot($conversionName);

            if (! $disk->exists($relativePath)) {
                return false;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'media_conv_');
            file_put_contents($tempPath, $disk->get($relativePath));

            return @getimagesize($tempPath);
        } catch (Exception $exception) {
            Log::warning('Failed to read remote conversion dimensions', [
                'media_id' => $media->id,
                'conversion' => $conversionName,
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
     * Generate optimized URL for a specific variation
     */
    private function generateOptimizedUrl(mixed $mediaId, string $variation): string
    {
        $media = $mediaId instanceof Media ? $mediaId : Media::query()->find($mediaId);
        if (! $media) {
            return '';
        }

        // Return original for non-images or if variation doesn't exist
        if (! $this->isImage($media->mime_type) || $variation === 'original') {
            return $this->getOriginalUrl($media);
        }

        // Check if the requested variation exists
        if ($media->hasGeneratedConversion($variation)) {
            return $media->getUrl($variation);
        }

        // Fallback logic: try smaller sizes if requested size doesn't exist
        $fallbackOrder = $this->getFallbackOrder($variation);
        foreach ($fallbackOrder as $fallbackSize) {
            if ($media->hasGeneratedConversion($fallbackSize)) {
                return $media->getUrl($fallbackSize);
            }
        }

        // Final fallback to original
        return $this->getOriginalUrl($media);
    }

    /**
     * Get original media URL safely
     */
    private function getOriginalUrl(Media $media): string
    {
        try {
            return $media->getUrl();
        } catch (Exception $exception) {
            Log::warning('Failed to get original media URL', [
                'media_id' => $media->id,
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Check if media is an image
     */
    private function isImage(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    /**
     * Check if a variation should exist based on configuration
     */
    private function shouldHaveVariation(string $size, ?Media $media = null): bool
    {
        // For small images, only thumbnail should be generated
        if ($media && $media->getCustomProperty('is_small_image', false)) {
            return $size === 'thumbnail';
        }

        // Optimized should always be generated for non-WebP images
        if ($size === 'optimized') {
            return true;
        }

        // Thumbnail is always enabled
        if ($size === 'thumbnail') {
            return true;
        }

        // For responsive sizes, always generate them for normal images
        $responsiveSizes = ['small', 'medium', 'large', 'xlarge'];
        if (in_array($size, $responsiveSizes)) {
            return true;
        }

        // For other sizes, check if they have valid dimensions configured
        return $this->getConfiguredWidth($size) > 0 &&
               $this->getConfiguredHeight($size) > 0;
    }

    /**
     * Get configured sizes with fallbacks
     */
    private function getConfiguredSizes(): array
    {
        $configured = [];
        foreach (self::VARIATION_SIZES as $size => $defaults) {
            $configured[$size] = [
                'width' => $this->getConfiguredWidth($size, $defaults['width']),
                'height' => $this->getConfiguredHeight($size, $defaults['height']),
                'enabled' => $this->shouldHaveVariation($size),
            ];
        }

        return $configured;
    }

    /**
     * Get configured width with fallback
     */
    private function getConfiguredWidth(string $size, ?int $default = null): int
    {
        // Thumbnail uses config value
        if ($size === 'thumbnail') {
            return (int) config('media.thumbnail_width', 150);
        }

        $default ??= self::VARIATION_SIZES[$size]['width'] ?? 0;

        // Use config values or fall back to constants
        $configValue = config(sprintf('media.%s_width', $size));
        if ($configValue && $configValue > 0) {
            return (int) $configValue;
        }

        return $default;
    }

    /**
     * Get configured height with fallback
     */
    private function getConfiguredHeight(string $size, ?int $default = null): int
    {
        // Thumbnail uses config value (square)
        if ($size === 'thumbnail') {
            return (int) config('media.thumbnail_width', 150);
        }

        $default ??= self::VARIATION_SIZES[$size]['height'] ?? 0;

        // Use config values or fall back to constants
        $configValue = config(sprintf('media.%s_height', $size));
        if ($configValue && $configValue > 0) {
            return (int) $configValue;
        }

        return $default;
    }

    /**
     * Build srcset string for responsive images
     */
    private function buildSrcSet(array $variations, ?Media $media = null): string
    {
        $srcSet = [];
        $sizeOrder = ['small', 'medium', 'large', 'xlarge'];

        // Add responsive variations that actually exist
        foreach ($sizeOrder as $size) {
            if (isset($variations[$size]) && ! empty($variations[$size])) {
                $width = $this->getConfiguredWidth($size);
                $srcSet[] = sprintf('%s %dw', $variations[$size], $width);
            }
        }

        // Always include optimized image if it exists and is larger than our largest responsive variation
        if ($media && isset($variations['optimized'])) {
            $originalWidth = $media->getCustomProperty('width', 0);
            $largestResponsiveWidth = 0;

            // Find the largest responsive width we have
            foreach ($sizeOrder as $size) {
                if (isset($variations[$size]) && ! empty($variations[$size])) {
                    $largestResponsiveWidth = $this->getConfiguredWidth($size);
                }
            }

            // Include optimized if it's larger than our largest responsive variation
            if ($originalWidth > $largestResponsiveWidth) {
                $srcSet[] = sprintf('%s %sw', $variations['optimized'], $originalWidth);
            }
        }

        // If no responsive variations but we have optimized, include it
        if ($srcSet === [] && isset($variations['optimized']) && $media) {
            $originalWidth = $media->getCustomProperty('width', 0);
            if ($originalWidth > 0) {
                $srcSet[] = sprintf('%s %sw', $variations['optimized'], $originalWidth);
            }
        }

        return implode(', ', $srcSet);
    }

    /**
     * Get responsive sizes attribute based on available conversions
     */
    private function getResponsiveSizes(?Media $media = null): string
    {
        if (! $media instanceof Media) {
            // Fallback to default if no media provided
            return '(max-width: 400px) 400px, (max-width: 800px) 800px, (max-width: 1200px) 1200px, 1920px';
        }

        $variations = $this->getMediaVariations($media->id);
        $originalWidth = $media->getCustomProperty('width', 0);
        $sizesArray = [];

        // Define the responsive breakpoints we want to check for
        $responsiveBreakpoints = [
            'small' => 400,
            'medium' => 800,
            'large' => 1200,
            'xlarge' => 1920,
        ];

        // Find what responsive sizes actually exist
        $availableWidths = [];
        foreach ($responsiveBreakpoints as $size => $width) {
            if (isset($variations[$size]) && ! empty($variations[$size])) {
                $availableWidths[] = $width;
            }
        }

        // Determine the maximum available width
        $maxWidth = 0;
        if ($availableWidths !== []) {
            $maxWidth = max($availableWidths);
        }

        // If we have optimized and it's larger than responsive sizes, use original width
        if (isset($variations['optimized']) && $originalWidth > $maxWidth) {
            $maxWidth = $originalWidth;
        }

        // If no variations at all, fallback
        if ($maxWidth === 0) {
            return '100vw';
        }

        // Build the sizes attribute
        // For each available responsive size, add a breakpoint
        foreach ($availableWidths as $width) {
            $sizesArray[] = sprintf('(max-width: %spx) %spx', $width, $width);
        }

        // If optimized is larger than responsive sizes, add its breakpoint
        if (isset($variations['optimized']) && $originalWidth > max($availableWidths !== [] ? $availableWidths : [0])) {
            $sizesArray[] = sprintf('(max-width: %spx) %spx', $originalWidth, $originalWidth);
        }

        // Final fallback - the maximum width available
        $sizesArray[] = $maxWidth.'px';

        return implode(', ', $sizesArray);
    }

    /**
     * Get fallback order for missing variations
     */
    private function getFallbackOrder(string $requestedSize): array
    {
        $order = ['xlarge', 'large', 'medium', 'small', 'thumbnail'];

        // Remove the requested size and reorder based on proximity
        return array_filter($order, fn (string $size): bool => $size !== $requestedSize);
    }

    /**
     * Get quality settings for different formats
     */
    private function getQualitySettings(): array
    {
        $settings = [];
        foreach (self::SUPPORTED_FORMATS as $format => $config) {
            $configValue = config(sprintf('media.%s_quality', $format));
            $quality = $configValue && $configValue > 0 ? (int) $configValue : $config['default_quality'];

            $settings[$format] = [
                'quality' => $quality,
                'range' => $config['quality_range'],
            ];
        }

        return $settings;
    }

    /**
     * Get upload settings for frontend
     */
    private function getUploadSettings(): array
    {
        return [
            'max_file_size' => config('media-library.max_file_size'),
            'max_file_size_mb' => config('media-library.max_file_size') / (1024 * 1024),
            'allowed_types' => config('media.media_allowed_file_types', ''),
            'max_filename_length' => config('media.max_file_name_length', 100),
        ];
    }
}
