<?php

/**
 * Media Helper Functions
 *
 * This file contains all media-related helper functions for the application.
 * Provides unified access to media URLs, objects, and responsive image generation.
 */
use App\Models\CustomMedia;
use App\Services\MediaVariationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

if (! function_exists('get_placeholder_image_url')) {
    /**
     * Get the configured placeholder image URL.
     */
    function get_placeholder_image_url(): string
    {
        $configured = setting('media_default_placeholder_image', '');

        if (empty($configured) && function_exists('get_env_value')) {
            $configured = get_env_value('MEDIA_DEFAULT_PLACEHOLDER_IMAGE', '');
        }

        if (! empty($configured)) {
            $resolved = get_media_url($configured, null, false);
            if (! in_array($resolved, [null, '', '0'], true)) {
                return $resolved;
            }
        }

        return asset('assets/images/placeholder-image.png');
    }
}

if (! function_exists('get_media_url')) {
    /**
     * Get media URL with intelligent fallback mechanism
     *
     * This polymorphic helper accepts multiple input types and provides
     * safe access to Spatie Media Library with automatic conversion fallbacks.
     *
     * @param  mixed  $input  String path, int ID, Media object, or null
     * @param  string|null  $conversionOrDisk  Conversion name (for IDs/objects) OR disk name (for paths)
     * @param  bool  $usePlaceholder  Whether to return placeholder for missing media
     */
    function get_media_url(mixed $input, ?string $conversionOrDisk = null, bool $usePlaceholder = true): ?string
    {
        // Null check
        if ($input === null) {
            return $usePlaceholder ? get_placeholder_image_url() : null;
        }

        // String path (original behavior - for settings that store file paths)
        if (is_string($input) && ! is_numeric($input)) {
            $disk = $conversionOrDisk ?? get_storage_disk();
            if ($input !== '' && $input !== '0') {
                return Storage::disk($disk)->url($input);
            }

            return $usePlaceholder ? get_placeholder_image_url() : null;
        }

        // Numeric ID - fetch from database (ALWAYS filter by active status)
        if (is_numeric($input)) {
            $media = CustomMedia::query()->where('id', $input)
                ->where('status', 'active')
                ->first();

            if (! $media) {
                return $usePlaceholder ? get_placeholder_image_url() : null;
            }

            // Use smart fallback from model
            return $media->getMediaUrl($conversionOrDisk, $usePlaceholder);
        }

        // Media object - check if it has our smart fallback method
        if ($input instanceof CustomMedia) {
            return $input->getMediaUrl($conversionOrDisk, $usePlaceholder);
        }

        // Fallback for unexpected input
        return $usePlaceholder ? get_placeholder_image_url() : null;
    }
}

if (! function_exists('get_media')) {
    /**
     * Get full media object/data
     *
     * Returns the CustomMedia object for accessing additional media properties.
     * Always filters by active status when fetching by ID.
     *
     * @param  mixed  $input  Int ID, Media object, or null
     */
    function get_media(mixed $input): ?CustomMedia
    {
        // Already a Media object - return as-is
        if ($input instanceof CustomMedia) {
            return $input;
        }

        // Numeric ID - ALWAYS filter by active status
        if (is_numeric($input)) {
            return CustomMedia::query()->where('id', $input)
                ->where('status', 'active')
                ->first();
        }

        // Null or invalid input
        return null;
    }
}

if (! function_exists('get_storage_disk')) {
    /**
     * Get the configured storage disk for media
     */
    function get_storage_disk(): string
    {
        return config('media-library.disk_name', 'public');
    }
}

if (! function_exists('get_storage_root_folder')) {
    /**
     * Get the storage root folder path
     */
    function get_storage_root_folder(): string
    {
        // Use config value from environment (STORAGE_ROOT_FOLDER in .env)
        $configValue = config('media.media_storage_root');
        if (! empty($configValue)) {
            return $configValue;
        }

        return setting('storage.root_folder', '');
    }
}

if (! function_exists('apply_storage_root_folder')) {
    /**
     * Prefix a relative path with the configured storage root folder.
     */
    function apply_storage_root_folder(string $path = ''): string
    {
        $rootFolder = trim(get_storage_root_folder(), '/');
        $normalizedPath = trim($path, '/');

        if ($rootFolder === '') {
            return $normalizedPath;
        }

        if ($normalizedPath === '' || $normalizedPath === $rootFolder || str_starts_with($normalizedPath, $rootFolder.'/')) {
            return $normalizedPath;
        }

        return $rootFolder.'/'.$normalizedPath;
    }
}

if (! function_exists('store_uploaded_file_on_media_disk')) {
    /**
     * Store an uploaded file on the configured storage disk using the shared root folder.
     */
    function store_uploaded_file_on_media_disk(UploadedFile $file, string $directory = '', ?string $disk = null): string|false
    {
        return $file->store(apply_storage_root_folder($directory), $disk ?? get_storage_disk());
    }
}

if (! function_exists('get_responsive_image')) {
    /**
     * Generate responsive image HTML using our custom responsive images
     */
    function get_responsive_image($mediaId, array $options = []): string
    {
        if (empty($mediaId)) {
            return '';
        }

        // Extract ID if Media object is passed
        $media = $mediaId instanceof CustomMedia ? $mediaId : CustomMedia::query()->find($mediaId);

        if (! $media) {
            return '';
        }

        try {
            // Build responsive image HTML
            return buildResponsiveImageHtml($media, $options);
        } catch (Exception $exception) {
            Log::error('Failed to generate responsive image', [
                'media_id' => $media->id,
                'error' => $exception->getMessage(),
            ]);

            return '';
        }
    }
}

if (! function_exists('buildResponsiveImageHtml')) {
    /**
     * Build responsive image HTML with srcset and picture element
     */
    function buildResponsiveImageHtml(CustomMedia $media, array $options = []): string
    {
        try {
            // Use MediaVariationService for proper responsive image generation
            $mediaVariationService = resolve(MediaVariationService::class);

            // Get responsive image data
            $responsiveData = $mediaVariationService->getResponsiveImageData($media->id);

            if (empty($responsiveData)) {
                // Fallback to simple img tag
                $src = $media->hasGeneratedConversion('optimized')
                    ? $media->getUrl('optimized')
                    : $media->getUrl();

                $class = $options['class'] ?? '';
                $alt = $options['alt'] ?? $media->getCustomProperty('alt_text', $media->name ?? '');
                $style = $options['style'] ?? '';
                $lazy = $options['lazy'] ?? true;
                $loading = $lazy ? 'lazy' : 'eager';

                $html = '<img';
                $html .= ' src="'.htmlspecialchars($src).'"';
                $html .= ' alt="'.htmlspecialchars((string) $alt).'"';
                if ($class) {
                    $html .= ' class="'.htmlspecialchars($class).'"';
                }

                if ($style) {
                    $html .= ' style="'.htmlspecialchars((string) $style).'"';
                }

                $html .= ' loading="'.htmlspecialchars($loading).'"';

                return $html.'>';
            }

            // Extract options
            $class = $options['class'] ?? '';
            $alt = $options['alt'] ?? $responsiveData['alt'] ?? '';
            $style = $options['style'] ?? '';
            $lazy = $options['lazy'] ?? true;
            $loading = $lazy ? 'lazy' : 'eager';

            // For small images, use simple img tag
            if ($responsiveData['is_small_image'] ?? false) {
                $html = '<img';
                $html .= ' src="'.htmlspecialchars((string) $responsiveData['src']).'"';
                $html .= ' alt="'.htmlspecialchars($alt).'"';
                if ($class) {
                    $html .= ' class="'.htmlspecialchars($class).'"';
                }

                if ($style) {
                    $html .= ' style="'.htmlspecialchars((string) $style).'"';
                }

                $html .= ' loading="'.htmlspecialchars($loading).'"';

                return $html.'>';
            }

            // For normal images, build proper responsive image
            $html = '<img';

            // Add srcset if available
            if (! empty($responsiveData['srcset'])) {
                $html .= ' srcset="'.htmlspecialchars((string) $responsiveData['srcset']).'"';
            }

            // Add sizes if available
            if (! empty($responsiveData['sizes'])) {
                $html .= ' sizes="'.htmlspecialchars((string) $responsiveData['sizes']).'"';
            }

            // Add src
            $html .= ' src="'.htmlspecialchars((string) $responsiveData['src']).'"';

            // Add alt
            $html .= ' alt="'.htmlspecialchars($alt).'"';

            // Add optional attributes
            if ($class) {
                $html .= ' class="'.htmlspecialchars($class).'"';
            }

            if ($style) {
                $html .= ' style="'.htmlspecialchars((string) $style).'"';
            }

            // Add loading
            $html .= ' loading="'.htmlspecialchars($loading).'"';

            return $html.'>';
        } catch (Exception $exception) {
            Log::error('Failed to build responsive image HTML', [
                'media_id' => $media->id,
                'error' => $exception->getMessage(),
            ]);

            // Fallback to simple img tag
            $src = $media->hasGeneratedConversion('optimized')
                ? $media->getUrl('optimized')
                : $media->getUrl();

            $class = $options['class'] ?? '';
            $alt = $options['alt'] ?? $media->getCustomProperty('alt_text', $media->name ?? '');
            $style = $options['style'] ?? '';
            $lazy = $options['lazy'] ?? true;
            $loading = $lazy ? 'lazy' : 'eager';

            $html = '<img';
            $html .= ' src="'.htmlspecialchars($src).'"';
            $html .= ' alt="'.htmlspecialchars((string) $alt).'"';
            if ($class) {
                $html .= ' class="'.htmlspecialchars($class).'"';
            }

            if ($style) {
                $html .= ' style="'.htmlspecialchars((string) $style).'"';
            }

            $html .= ' loading="'.htmlspecialchars($loading).'"';

            return $html.'>';
        }
    }
}

if (! function_exists('responsive_image')) {
    /**
     * Generate responsive image with fallback for missing media
     */
    function responsive_image($mediaId, array $options = []): string
    {
        // Return empty string if no valid responsive image can be generated
        // No placeholder fallback for responsive images
        return get_responsive_image($mediaId, $options);
    }
}
