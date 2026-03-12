<?php

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

class MediaPathGenerator extends DefaultPathGenerator
{
    public function getBasePath(Media $media): string
    {
        $baseFolder = $this->resolveBaseFolder($media);

        return $baseFolder === '' ? $media->uuid : $baseFolder.'/'.$media->uuid;
    }

    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    /**
     * Get the non-trash base path for media
     */
    public function getNonTrashBasePath(Media $media): string
    {
        return $this->getBasePath($media);
    }

    /**
     * Get the trash base path for media (used for backward-compatible restore)
     */
    public function getTrashBasePath(Media $media): string
    {
        $baseFolder = $this->resolveBaseFolder($media);

        if ($baseFolder === '') {
            return 'trash/'.$media->uuid;
        }

        return $baseFolder.'/trash/'.$media->uuid;
    }

    /**
     * Resolve the base folder prefix for a media item.
     *
     * - null: not explicitly set → use config default (backward compat)
     * - '': intentionally empty → no prefix (local disk)
     * - non-empty: use stored value (remote disk root folder)
     */
    private function resolveBaseFolder(Media $media): string
    {
        $baseFolder = $media->media_storage_root ?? config('media.media_storage_root', 'media');

        return trim((string) $baseFolder, '/');
    }
}
