<?php

namespace Modules\Platform\Models\Presenters;

trait DomainDnsPresenters
{
    protected function getRecordTypeLabelAttribute(): string
    {
        $typeConfig = config('platform.domain.record_types.'.$this->type, []);

        return $typeConfig['label'] ?? (string) $this->type;
    }

    protected function getTtlLabelAttribute(): string
    {
        $ttl = $this->ttl;
        $ttlConfig = config('platform.domain.dns_ttls.'.$ttl, []);

        if (! empty($ttlConfig['label'])) {
            return (string) $ttlConfig['label'];
        }

        return $ttl !== null ? $ttl.'s' : '-';
    }

    protected function getCreatedAtFormattedAttribute(): string
    {
        return app_date_time_format($this->created_at, 'datetime');
    }

    protected function getFullNameAttribute(): string
    {
        $domain = $this->domain;
        if (! $domain) {
            return $this->name ?? '';
        }

        if ($this->name === '@' || empty($this->name)) {
            return $domain->domain_name;
        }

        return sprintf('%s.%s', $this->name, $domain->domain_name);
    }
}
