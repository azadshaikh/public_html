<?php

namespace Modules\CMS\Models\Presenters;

trait RedirectionPresenters
{
    protected function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => $this->isExpired() ? 'Expired' : 'Active',
            'inactive' => 'Inactive',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    protected function getStatusBadgeAttribute(): string
    {
        if ($this->status === 'active' && $this->isExpired()) {
            return 'warning';
        }

        return match ($this->status) {
            'active' => 'success',
            'inactive' => 'secondary',
            default => 'secondary',
        };
    }

    protected function getRedirectTypeLabelAttribute(): string
    {
        return $this->resolveRedirectTypeLabel();
    }

    protected function getUrlTypeLabelAttribute(): string
    {
        $urlTypes = config('seo.url_types', []);
        $type = $urlTypes[$this->url_type] ?? null;

        if (is_array($type) && isset($type['label'])) {
            return $type['label'];
        }

        return ucfirst($this->url_type ?? 'Unknown');
    }

    protected function getMatchTypeLabelAttribute(): string
    {
        $matchTypes = config('seo.match_types', []);
        $type = $matchTypes[$this->match_type] ?? null;

        if (is_array($type) && isset($type['label'])) {
            return $type['label'];
        }

        return match ($this->match_type) {
            'exact' => 'Exact Match',
            'wildcard' => 'Wildcard',
            'regex' => 'Regex',
            default => ucfirst($this->match_type ?? 'Exact'),
        };
    }

    protected function getFormattedCreatedAtAttribute(): ?string
    {
        return $this->created_at?->toIso8601String();
    }

    protected function getFormattedUpdatedAtAttribute(): ?string
    {
        return $this->updated_at?->toIso8601String();
    }

    protected function getFormattedLastHitAtAttribute(): ?string
    {
        return $this->last_hit_at?->toIso8601String();
    }

    protected function getFormattedExpiresAtAttribute(): ?string
    {
        return $this->expires_at?->toIso8601String();
    }

    protected function getLastHitHumanAttribute(): ?string
    {
        return $this->last_hit_at?->diffForHumans();
    }

    protected function getExpiresHumanAttribute(): ?string
    {
        if (! $this->expires_at) {
            return null;
        }

        return $this->expires_at->isPast()
            ? 'Expired '.$this->expires_at->diffForHumans()
            : 'Expires '.$this->expires_at->diffForHumans();
    }

    protected function getSourceUrlLinkAttribute(): string
    {
        if (empty($this->source_url)) {
            return '';
        }

        $display = e($this->source_url);

        return '<a href="'.$display.'" class="text-decoration-none" target="_blank">'.$display.'</a>';
    }
}
