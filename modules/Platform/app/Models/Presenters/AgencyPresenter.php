<?php

namespace Modules\Platform\Models\Presenters;

trait AgencyPresenter
{
    protected function getStatusBadgeAttribute(): string
    {
        $status = config('platform.agency_statuses.'.$this->status);

        if ($status) {
            return '<span class="badge bg-'.$status['color'].'-subtle text-'.$status['color'].'">'.$status['label'].'</span>';
        }

        return '<span class="badge bg-primary-subtle text-primary">Status:'.ucwords($this->status).'</span>';
    }

    protected function getAgencyInfoAttribute(): string
    {
        $info = [];

        if ($this->name) {
            $info[] = 'Name: '.$this->name;
        }

        if ($this->email) {
            $info[] = 'Email: '.$this->email;
        }

        if ($this->website) {
            $info[] = 'Website: '.$this->website;
        }

        return implode(' | ', $info);
    }

    protected function getContactInfoAttribute(): string
    {
        $info = [];

        if ($this->email) {
            $info[] = 'Email: '.$this->email;
        }

        if ($this->mobile) {
            $info[] = sprintf('Mobile: %s %s', $this->country_code, $this->mobile);
        }

        if ($this->address) {
            $info[] = 'Address: '.$this->address;
        }

        return implode(' | ', $info);
    }

    protected function getLocationInfoAttribute(): string
    {
        $info = [];

        if ($this->city_name) {
            $info[] = 'City: '.$this->city_name;
        }

        if ($this->zip_code) {
            $info[] = 'ZIP: '.$this->zip_code;
        }

        if ($this->address) {
            $info[] = 'Address: '.$this->address;
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

    protected function getLogoUrlAttribute(): ?string
    {
        return $this->logo_id ? get_media_url($this->logo_id) : get_media_url(setting('media_default_placeholder_image'));
    }

    protected function getIconUrlAttribute(): ?string
    {
        return $this->icon_id ? get_media_url($this->icon_id) : get_media_url(setting('media_default_placeholder_image'));
    }

    protected function getLightIconUrlAttribute(): ?string
    {
        return $this->light_icon_id ? get_media_url($this->light_icon_id) : get_media_url(setting('media_default_placeholder_image'));
    }

    protected function getFaviconIconUrlAttribute(): ?string
    {
        return $this->favicon_icon ? get_media_url($this->favicon_icon) : get_media_url(setting('media_default_placeholder_image'));
    }

    protected function getAppleTouchIconUrlAttribute(): ?string
    {
        return $this->apple_touch_icon ? get_media_url($this->apple_touch_icon) : get_media_url(setting('media_default_placeholder_image'));
    }

    protected function getAndroidDevicesIconUrlAttribute(): ?string
    {
        return $this->android_devices_icon ? get_media_url($this->android_devices_icon) : get_media_url(setting('media_default_placeholder_image'));
    }
}
