<?php

namespace Modules\CMS\Services\Components;

use App\Models\CustomMedia;

/**
 * Responsive Image Component
 * Usage: {responsive_image media_id='123' class='img-fluid' alt='Image description'}
 */
class ResponsiveImageComponent extends ThemeComponent
{
    public function render(array $params, $template = null): string
    {
        $mediaId = $this->param($params, 'media_id');
        $src = $this->param($params, 'src');
        $class = $this->param($params, 'class', '');
        $alt = $this->param($params, 'alt', '');
        $style = $this->param($params, 'style', '');
        $loading = $this->param($params, 'loading', 'lazy');
        $width = $this->param($params, 'width');
        $height = $this->param($params, 'height');

        // If direct src is provided, render simple img tag
        if ($src && ! $mediaId) {
            $attributes = [
                'src' => $src,
                'alt' => $alt,
                'loading' => $loading,
            ];

            if ($class) {
                $attributes['class'] = $class;
            }

            if ($style) {
                $attributes['style'] = $style;
            }

            if ($width) {
                $attributes['width'] = $width;
            }

            if ($height) {
                $attributes['height'] = $height;
            }

            return '<img '.$this->buildAttributes($attributes).'>';
        }

        // If media_id is not provided, return empty
        if (! $mediaId) {
            return '';
        }

        // Fetch media from database
        $media = CustomMedia::query()->find($mediaId);

        if (! $media) {
            return '';
        }

        // Build attributes
        $attributes = [
            'src' => get_media_url($media),
            'alt' => $alt ?: $media->alt_text ?? $media->name,
            'loading' => $loading,
        ];

        if ($class) {
            $attributes['class'] = $class;
        }

        if ($style) {
            $attributes['style'] = $style;
        }

        // Add width and height if available
        if ($media->width) {
            $attributes['width'] = $media->width;
        }

        if ($media->height) {
            $attributes['height'] = $media->height;
        }

        // Generate srcset for responsive images if conversions exist
        $srcset = $this->generateSrcset($media);
        if ($srcset) {
            $attributes['srcset'] = $srcset;
            $attributes['sizes'] = $this->param($params, 'sizes', '(max-width: 768px) 100vw, 50vw');
        }

        return '<img '.$this->buildAttributes($attributes).'>';
    }

    /**
     * Generate srcset for responsive images
     */
    protected function generateSrcset(CustomMedia $media): ?string
    {
        $conversions = $media->getGeneratedConversions();

        // @phpstan-ignore-next-line empty.variable
        if (empty($conversions)) {
            return null;
        }

        $srcset = [];

        foreach ($conversions as $conversion => $exists) {
            if ($exists) {
                $url = get_media_url($media, $conversion);
                // Try to get width from conversion name or metadata
                $width = $this->getConversionWidth($conversion);
                if ($width) {
                    $srcset[] = sprintf('%s %sw', $url, $width);
                }
            }
        }

        return $srcset === [] ? null : implode(', ', $srcset);
    }

    /**
     * Extract width from conversion name
     */
    protected function getConversionWidth(string $conversion): ?int
    {
        // Try to extract width from conversion name (e.g., "thumb_300", "large_1200")
        if (preg_match('/(\d+)/', $conversion, $matches)) {
            return (int) $matches[1];
        }

        // Default widths for common conversion names
        $defaultWidths = [
            'thumb' => 150,
            'small' => 300,
            'medium' => 600,
            'large' => 1200,
            'xlarge' => 1920,
        ];

        return $defaultWidths[$conversion] ?? null;
    }
}
