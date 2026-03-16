<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\CMS\Definitions\RedirectionDefinition;
use Modules\CMS\Models\Redirection;

/** @mixin Redirection */
class RedirectionResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new RedirectionDefinition;
    }

    protected function customFields(): array
    {
        $isTrashed = $this->resource->deleted_at !== null;

        $statusLabel = $isTrashed
            ? 'Trash'
            : $this->status_label;

        $statusClass = $isTrashed
            ? 'bg-danger-subtle text-danger'
            : ($this->status === 'active' && $this->isExpired()
                ? 'bg-warning-subtle text-warning'
                : match ($this->status) {
                    'active' => 'bg-success-subtle text-success',
                    'inactive' => 'bg-secondary-subtle text-secondary',
                    default => 'bg-secondary-subtle text-secondary',
                });

        return [
            // Redirections do not have a show page; link the main column to edit when available.
            'show_url' => $isTrashed
                ? null
                : route($this->scaffold()->getRoutePrefix().'.edit', $this->resource->getKey()),

            'created_at' => $this->resource->created_at
                ? app_date_time_format($this->resource->created_at, 'date')
                : '—',

            'status_label' => $statusLabel,
            'status_class' => $statusClass,

            'redirect_type_label' => $this->redirect_type_label,
            'redirect_type_class' => $this->mapRedirectTypeClass((string) $this->redirect_type),

            'url_type_label' => $this->url_type_label,
            'url_type_class' => $this->mapUrlTypeClass((string) $this->url_type),

            'match_type_label' => $this->match_type_label,
            'match_type_class' => $this->mapMatchTypeClass((string) $this->match_type),
        ];
    }

    private function mapRedirectTypeClass(string $type): string
    {
        return match ($type) {
            '301', '308' => 'bg-success-subtle text-success',
            '302', '307' => 'bg-warning-subtle text-warning',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    private function mapUrlTypeClass(string $type): string
    {
        return $type === 'external'
            ? 'bg-warning-subtle text-warning'
            : 'bg-primary-subtle text-primary';
    }

    private function mapMatchTypeClass(string $type): string
    {
        return match ($type) {
            'exact' => 'bg-primary-subtle text-primary',
            'wildcard' => 'bg-info-subtle text-info',
            'regex' => 'bg-warning-subtle text-warning',
            default => 'bg-secondary-subtle text-secondary',
        };
    }
}
