<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Definitions\MediaDefinition;
use App\Models\CustomMedia;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use App\Traits\DateTimeFormattingTrait;
use Exception;
use RuntimeException;

/**
 * MediaLibraryResource - Scaffold-based JSON resource for Media Library V2
 *
 * Formats CustomMedia records for DataGrid display.
 * Includes thumbnail URLs, file info, and action URLs.
 */
class MediaLibraryResource extends ScaffoldResource
{
    use DateTimeFormattingTrait;

    /**
     * Get the scaffold definition
     */
    protected function definition(): ScaffoldDefinition
    {
        return new MediaDefinition;
    }

    /**
     * Get custom/computed fields specific to Media
     */
    protected function customFields(): array
    {
        $media = $this->media();
        $ownerRelation = $media->owner()->first();
        $owner = $ownerRelation instanceof User ? $ownerRelation : null;

        $data = [
            // File info
            'file_name' => $media->getAttribute('file_name'),
            'name' => $media->getAttribute('name') ?? $media->getAttribute('file_name'),
            'mime_type' => $media->getAttribute('mime_type'),
            'size' => $media->getAttribute('size'),
            'human_readable_size' => $media->getAttribute('human_readable_size'),

            // Badge fields for mime_type column
            'mime_type_label' => $this->getMimeTypeLabel(),
            'mime_type_class' => $this->getMimeTypeClass(),

            // Thumbnail and URLs
            'thumbnail_url' => $this->getThumbnailUrl(),
            'original_url' => $this->getOriginalUrl(),
            'media_url' => $this->getMediaUrl(),

            // Owner info
            'owner' => $owner?->name,
            'owner_id' => $media->getAttribute('created_by'),

            // Metadata
            'alt_text' => $media->getCustomProperty('alt_text', ''),
            'caption' => $media->getCustomProperty('caption', ''),
            'description' => $media->getCustomProperty('description', ''),
            'tags' => $media->getCustomProperty('tags', ''),

            // Status
            'is_trashed' => $media->getAttribute('deleted_at') !== null,
            'is_processing' => $this->isProcessing(),
            'conversion_status' => $this->getConversionStatusLabel(),

            // Action URLs for DataGrid
            'show_url' => route('app.media.details', $media->getKey()),
            'edit_url' => route('app.media.details', $media->getKey()),
            'delete_url' => route('app.media.destroy', $media->getKey()),
            'restore_url' => route('app.media.restore', $media->getKey()),

            // Datetime fields
            'created_at' => $media->getAttribute('created_at'),
            'updated_at' => $media->getAttribute('updated_at'),
        ];

        return $this->formatDateTimeFields(
            $data,
            dateFields: ['created_at', 'updated_at']
        );
    }

    /**
     * Get thumbnail URL with fallbacks
     */
    protected function getThumbnailUrl(): ?string
    {
        $media = $this->media();

        try {
            // Try thumbnail conversion first
            if ($media->hasGeneratedConversion('thumbnail')) {
                return $media->getUrl('thumbnail');
            }

            // Try optimized/webp
            if ($media->hasGeneratedConversion('optimized')) {
                return $media->getUrl('optimized');
            }

            // Fallback to original
            return $media->getUrl();
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get original URL
     */
    protected function getOriginalUrl(): ?string
    {
        $media = $this->media();

        try {
            return $media->getUrl();
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get best media URL (webp/optimized preferred)
     */
    protected function getMediaUrl(): ?string
    {
        $media = $this->media();

        try {
            if ($media->hasGeneratedConversion('optimized')) {
                return $media->getUrl('optimized');
            }

            return $media->getUrl();
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get human-readable MIME type label
     */
    protected function getMimeTypeLabel(): string
    {
        $mimeType = (string) ($this->media()->getAttribute('mime_type') ?? '');

        // Map common mime types to friendly labels
        $mimeLabels = [
            'image/jpeg' => 'JPEG',
            'image/jpg' => 'JPG',
            'image/png' => 'PNG',
            'image/gif' => 'GIF',
            'image/webp' => 'WebP',
            'image/svg+xml' => 'SVG',
            'video/mp4' => 'MP4',
            'video/webm' => 'WebM',
            'audio/mpeg' => 'MP3',
            'audio/wav' => 'WAV',
            'application/pdf' => 'PDF',
            'application/msword' => 'DOC',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'DOCX',
            'application/vnd.ms-excel' => 'XLS',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
            'text/plain' => 'TXT',
            'text/csv' => 'CSV',
        ];

        if (isset($mimeLabels[$mimeType])) {
            return $mimeLabels[$mimeType];
        }

        // Fallback: extract subtype
        if (str_contains($mimeType, '/')) {
            $parts = explode('/', $mimeType);

            return strtoupper($parts[1] ?? $mimeType);
        }

        return strtoupper($mimeType !== '' && $mimeType !== '0' ? $mimeType : 'Unknown');
    }

    /**
     * Get CSS class for MIME type badge
     */
    protected function getMimeTypeClass(): string
    {
        $mimeType = (string) ($this->media()->getAttribute('mime_type') ?? '');

        if (str_starts_with($mimeType, 'image/')) {
            return 'bg-success-subtle text-success';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'bg-info-subtle text-info';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'bg-warning-subtle text-warning';
        }

        if (str_contains($mimeType, 'pdf')) {
            return 'bg-danger-subtle text-danger';
        }

        if (str_contains($mimeType, 'word') || str_contains($mimeType, 'document')) {
            return 'bg-primary-subtle text-primary';
        }

        if (str_contains($mimeType, 'excel') || str_contains($mimeType, 'spreadsheet')) {
            return 'bg-success-subtle text-success';
        }

        return 'bg-secondary-subtle text-secondary';
    }

    /**
     * Override getActions to use 'id' as the route parameter
     *
     * The existing media routes use {id} not {media}, so we need
     * to override the default route parameter generation.
     */
    protected function getActions(): array
    {
        $media = $this->media();
        $isTrashed = $media->getAttribute('deleted_at') !== null;
        $status = $isTrashed ? 'trash' : 'all';
        $id = $media->getKey();

        // Get actions from definition (filtered by permissions, scope, status)
        $definedActions = collect($this->scaffold()->actions())
            ->filter(fn ($action): bool => $action->authorized())
            ->filter(fn ($action): bool => $action->isForRow())
            ->filter(fn ($action): bool => $action->shouldShow($status));

        $actions = [];

        foreach ($definedActions as $action) {
            $actionData = $action->toArray();
            $key = $actionData['key'];

            // Build URL using 'id' as the parameter (media routes use {id})
            $url = null;
            if (! empty($actionData['route'])) {
                try {
                    $url = route($actionData['route'], ['id' => $id]);
                } catch (Exception) {
                    // Route doesn't exist, skip this action
                    continue;
                }
            }

            $actions[$key] = [
                'url' => $url,
                'label' => $actionData['label'],
                'icon' => $actionData['icon'] ?? null,
                'method' => $actionData['method'] ?? 'GET',
                'confirm' => $actionData['confirm'] ?? null,
                'variant' => $actionData['variant'] ?? 'default',
                'fullReload' => $actionData['fullReload'] ?? false,
                'attributes' => $actionData['attributes'] ?? null,
            ];

            // Add danger flag for variant
            if (($actionData['variant'] ?? null) === 'danger') {
                $actions[$key]['danger'] = true;
            }
        }

        return $actions;
    }

    /**
     * Check if media conversions are still being processed
     */
    protected function isProcessing(): bool
    {
        $media = $this->media();
        $mimeType = (string) ($media->getAttribute('mime_type') ?? '');

        // Only images have conversions
        if (! str_starts_with($mimeType, 'image/')) {
            return false;
        }

        // SVGs don't get converted
        if ($mimeType === 'image/svg+xml') {
            return false;
        }

        // If conversions are disabled, nothing to process
        if (! config('media.image_conversions_enabled', false)) {
            return false;
        }

        // Check if the thumbnail conversion exists — it's always generated
        return ! $media->hasGeneratedConversion('thumbnail');
    }

    /**
     * Get a human-readable conversion status label
     *
     * @return string completed|processing|not_applicable
     */
    protected function getConversionStatusLabel(): string
    {
        $media = $this->media();
        $mimeType = (string) ($media->getAttribute('mime_type') ?? '');

        if (! str_starts_with($mimeType, 'image/') || $mimeType === 'image/svg+xml') {
            return 'not_applicable';
        }

        // If conversions are disabled, no conversion status to report
        if (! config('media.image_conversions_enabled', false)) {
            return 'not_applicable';
        }

        if ($media->hasGeneratedConversion('thumbnail')) {
            return 'completed';
        }

        return 'processing';
    }

    private function media(): CustomMedia
    {
        throw_unless($this->resource instanceof CustomMedia, RuntimeException::class, 'MediaLibraryResource expects a CustomMedia model instance.');

        return $this->resource;
    }
}
