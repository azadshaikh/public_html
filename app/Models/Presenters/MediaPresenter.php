<?php

namespace App\Models\Presenters;

use App\Support\Media\MediaPathGenerator;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\Support\File;

trait MediaPresenter
{
    protected function getStatusFormattedAttribute(): ?string
    {
        return match ($this->status) {
            'inactive' => '<span class="badge bg-danger-subtle text-danger">Inactive</span>',
            'active' => '<span class="badge bg-success-subtle text-success">Published</span>',
            // @phpstan-ignore match.alwaysTrue
            'trash' => '<span class="badge bg-danger-subtle text-danger">Trash</span>',
            default => null,
        };
    }

    protected function getMimeTypeFormattedAttribute(): string
    {
        return ucfirst(str_replace('/', ' ', $this->mime_type));
    }

    protected function getSizeFormattedAttribute(): string
    {
        return File::getHumanReadableSize($this->size);
    }

    protected function getHumanReadableSizeAttribute(): string
    {
        return File::getHumanReadableSize($this->size);
    }

    protected function getCreatedAtFormattedAttribute()
    {
        return app_date_time_format($this->created_at, 'datetime');
    }

    protected function getUpdatedAtFormattedAttribute()
    {
        return app_date_time_format($this->updated_at, 'datetime');
    }

    protected function getCreatedByNameAttribute()
    {
        $owner = $this->owner;

        return $owner?->getAttribute('name') ?? '';
    }

    protected function getMediaUrlAttribute()
    {
        try {
            // First, try to get the direct URL from Spatie Media Library
            $url = $this->getUrl();
            if (! empty($url)) {
                return $url;
            }

            // Fallback to manual path construction using our custom path generator
            $pathGenerator = new MediaPathGenerator;
            $base_path = $pathGenerator->getBasePath($this);
            $file_path = $base_path.'/'.$this->file_name;

            // Generate URL using the configured disk
            $disk = $this->disk ?? config('filesystems.default');
            $storage = Storage::disk($disk);

            // Check if file exists and generate URL if storage supports it
            if ($storage->exists($file_path)) {
                try {
                    return $storage->url($file_path);
                } catch (Exception) {
                    // If URL generation fails, try alternative approach
                    return asset('storage/'.$file_path);
                }
            }

            // If file doesn't exist, return placeholder
            return $this->getPlaceholderUrl();
        } catch (Exception $exception) {
            // Log error without using Log facade to avoid dependency issues
            error_log('Media URL generation failed for ID '.($this->id ?? 'unknown').': '.$exception->getMessage());

            return $this->getPlaceholderUrl();
        }
    }

    protected function getMediaPathAttribute(): string
    {
        try {
            // Use our custom path generator for consistent path handling
            $pathGenerator = new MediaPathGenerator;

            return $pathGenerator->getBasePath($this).'/'.$this->file_name;
        } catch (Exception) {
            return '';
        }
    }

    protected function getCollectionFolderAttribute()
    {
        return $this->getCustomProperty('collection_folder', '');
    }

    protected function getModuleAttribute()
    {
        return $this->getCustomProperty('module', '');
    }

    protected function getMasterNameAttribute()
    {
        return $this->getCustomProperty('master_name', '');
    }

    private function getPlaceholderUrl()
    {
        // Return a placeholder based on mime type
        $mimeType = $this->mime_type ?? '';
        if (str_starts_with($mimeType, 'image/')) {
            return get_placeholder_image_url();
        }

        if (str_starts_with($mimeType, 'video/')) {
            return URL::asset('assets/images/placeholder-video.png');
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return URL::asset('assets/images/placeholder-audio.png');
        }

        return URL::asset('assets/images/placeholder-file.png');
    }
}
