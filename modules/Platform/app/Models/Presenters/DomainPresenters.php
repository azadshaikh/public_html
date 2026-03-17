<?php

namespace Modules\Platform\Models\Presenters;

trait DomainPresenters
{
    protected function getStatusBadgeAttribute(): string
    {
        $statusKey = $this->status ?? 'unknown';
        $statusOptions = config('platform.domain.statuses', []);
        $statusConfig = $statusOptions[$statusKey] ?? [];
        $statusBadge = $statusConfig['color'] ?? 'secondary';
        $statusLabel = $statusConfig['label'] ?? ucfirst($statusKey);

        return '<span class="badge bg-'.$statusBadge.'-subtle text-'.$statusBadge.'">'.$statusLabel.'</span>';
    }

    protected function getRegisteredDateFormattedAttribute(): string
    {
        return app_date_time_format($this->registered_date, 'date');
    }

    protected function getExpiryDateFormattedAttribute(): string
    {
        return app_date_time_format($this->expiry_date, 'date');
    }

    protected function getUpdatedDateFormattedAttribute(): string
    {
        return app_date_time_format($this->updated_date, 'date');
    }

    protected function getCreatedAtFormattedAttribute(): string
    {
        return app_date_time_format($this->created_at, 'date');
    }
}
