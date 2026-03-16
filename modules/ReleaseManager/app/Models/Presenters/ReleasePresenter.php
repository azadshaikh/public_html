<?php

namespace Modules\ReleaseManager\Models\Presenters;

trait ReleasePresenter
{
    protected function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => '<span class="badge text-bg-secondary">Draft</span>',
            'published' => '<span class="badge bg-success-subtle text-success">Published</span>',
            'trash' => '<span class="badge bg-danger-subtle text-danger">Trash</span>',
            default => '<span class="badge bg-light-subtle text-dark">Unknown</span>',
        };
    }

    protected function getReleaseTypeBadgeAttribute(): string
    {
        return match ($this->release_type) {
            'application' => '<span class="badge bg-primary-subtle text-primary">Application</span>',
            'module' => '<span class="badge bg-info-subtle text-info">Module</span>',
            'theme' => '<span class="badge bg-warning-subtle text-warning">Theme</span>',
            default => '<span class="badge text-bg-secondary">Unknown</span>',
        };
    }

    protected function getReleaseLinkFormattedAttribute(): string
    {
        return $this->release_link ? '<a href="'.get_media_url($this->release_link).'" target="_blank" class="btn btn-sm btn-primary">Download</a>' : '';
    }

    protected function getReleaseAtFormattedAttribute(): string
    {
        return $this->release_at ? app_date_time_format($this->release_at, 'date') : '--';
    }

    protected function getCreatedAtFormattedAttribute(): string
    {
        return app_date_time_format($this->created_at, 'datetime');
    }

    protected function getUpdatedAtFormattedAttribute(): string
    {
        return app_date_time_format($this->updated_at, 'datetime');
    }

    protected function getDeletedAtFormattedAttribute(): string
    {
        return $this->deleted_at ? app_date_time_format($this->deleted_at, 'datetime') : '--';
    }
}
