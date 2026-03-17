<?php

namespace Modules\Platform\Models\Presenters;

use Modules\Platform\Enums\WebsiteStatus;

trait WebsitePresenter
{
    protected function getStatusBadgeAttribute(): string
    {
        if ($this->status instanceof WebsiteStatus) {
            return '<span class="badge '.$this->status->badgeClass().'">'.$this->status->label().'</span>';
        }

        // Fallback for non-enum statuses (legacy)
        $status = config('platform.website.status.'.$this->status);
        if ($status) {
            return '<span class="badge bg-'.$status['color'].'-subtle text-'.$status['color'].'">'.$status['label'].'</span>';
        }

        return '<span class="badge bg-primary-subtle text-primary">Status: '.ucwords($this->status).'</span>';
    }

    protected function getDomainUrlAttribute(): string
    {
        if ($this->is_www) {
            return '<a href="https://www.'.$this->domain.'" class="text-primary fw-bold text-decoration-none" target="_blank">www.'.$this->domain.'</a>';
        }

        return '<a href="https://'.$this->domain.'" class="text-primary fw-bold text-decoration-none" target="_blank">'.$this->domain.'</a>';
    }

    protected function getTypeBadgeAttribute(): string
    {
        $type = config('platform.website.types.'.$this->type);

        if ($type) {
            return '<span class="badge bg-'.$type['color'].'-subtle text-'.$type['color'].'">'.$type['label'].'</span>';
        }

        return '<span class="badge text-bg-secondary">'.ucwords($this->type).'</span>';
    }

    protected function getCategoryLabelAttribute()
    {
        // primaryCategory relation returns a collection
        $category = $this->primaryCategory->first();

        return $category ? $category->name : '-';
    }

    protected function getSubCategoryLabelAttribute()
    {
        // Find the first non-primary category to mimic sub-category behavior
        $subCategory = $this->categories->first(fn ($cat): bool => ! $cat->pivot->is_primary);

        return $subCategory ? $subCategory->name : '-';
    }

    protected function getWebsiteInfoAttribute(): string
    {
        $info = [];

        if ($this->name) {
            $info[] = 'Name: '.$this->name;
        }

        if ($this->domain) {
            $info[] = 'Domain: '.$this->domain;
        }

        return implode(' | ', $info);
    }

    protected function getDomainInfoAttribute(): string
    {
        $info = [];

        if ($this->domain) {
            $info[] = 'Domain: '.$this->domain;
        }

        if ($this->is_www) {
            $info[] = 'WWW: Yes';
        }

        return implode(' | ', $info);
    }

    protected function getServerInfoAttribute(): string
    {
        $info = [];

        if ($this->server_id) {
            $info[] = 'Server ID: '.$this->server_id;
        }

        if ($this->provider) {
            $info[] = 'Provider: '.$this->provider;
        }

        if ($this->astero_version) {
            $info[] = 'Version: '.$this->astero_version;
        }

        return implode(' | ', $info);
    }

    protected function getDnsInfoAttribute(): string
    {
        $info = [];

        if ($this->use_dns_management) {
            $info[] = 'DNS Management: Enabled';
        }

        if ($this->dns_provider) {
            $info[] = 'Provider: '.$this->dns_provider;
        }

        if ($this->dns_zone_id) {
            $info[] = 'Zone ID: '.$this->dns_zone_id;
        }

        return implode(' | ', $info);
    }

    protected function getSetupInfoAttribute(): string
    {
        $info = [];

        if ($this->storage_zone_setup) {
            $info[] = 'Storage Zone: ✓';
        }

        if ($this->backup_setup) {
            $info[] = 'Backup: ✓';
        }

        if ($this->setup_complete_flag) {
            $info[] = 'Complete: ✓';
        }

        return implode(' | ', $info);
    }

    protected function getCreatedAtFormattedAttribute()
    {
        return $this->created_at ? app_date_time_format($this->created_at, 'datetime') : '-';
    }

    protected function getUpdatedAtFormattedAttribute()
    {
        return $this->updated_at ? app_date_time_format($this->updated_at, 'datetime') : '-';
    }

    protected function getExpiredOnFormattedAttribute()
    {
        return $this->expired_on ? app_date_time_format($this->expired_on, 'datetime') : '-';
    }

    protected function getIsExpiredAttribute()
    {
        if (! $this->expired_on) {
            return false;
        }

        return $this->expired_on->isPast();
    }

    protected function getExpiredBadgeAttribute(): string
    {
        if ($this->is_expired) {
            return '<span class="badge bg-danger-subtle text-danger">Expired</span>';
        }

        if ($this->expired_on) {
            return '<span class="badge bg-success-subtle text-success">Active</span>';
        }

        return '<span class="badge text-bg-secondary">No Expiry</span>';
    }
}
