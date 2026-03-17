<?php

namespace Modules\Platform\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Platform\Definitions\ServerDefinition;
use Modules\Platform\Models\Provider;

/**
 * @property int $id
 * @property string|null $type
 * @property string|null $status
 * @property int|null $max_domains
 * @property int|null $current_domains
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Provider|null $provider
 * @property-read Collection<int, Provider> $serverProviders
 */
class ServerResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new ServerDefinition;
    }

    protected function customFields(): array
    {
        $provider = $this->getPrimaryProviderName();

        $typeConfig = config('platform.server_types.'.$this->type, []);
        $typeColor = $typeConfig['color'] ?? 'secondary';

        $statusConfig = config('platform.server_statuses.'.$this->status, []);
        $statusColor = $statusConfig['color'] ?? 'secondary';

        $current = (int) ($this->current_domains ?? 0);
        $max = $this->max_domains !== null ? (int) $this->max_domains : null;
        $percent = $max && $max > 0 ? (int) round($current / $max * 100) : null;

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'provider_name' => $provider ?? '-',

            'type_label' => $typeConfig['label'] ?? ($this->type ? (string) $this->type : '-'),
            'type_class' => sprintf('bg-%s-subtle text-%s', $typeColor, $typeColor),

            'status_label' => $statusConfig['label'] ?? ucfirst((string) $this->status),
            'status_class' => sprintf('bg-%s-subtle text-%s', $statusColor, $statusColor),

            'domain_usage_current' => $current,
            'domain_usage_max' => $max,
            'domain_usage_percent' => $percent,

            'created_at' => app_date_time_format($this->created_at, 'date'),
            'updated_at' => app_date_time_format($this->updated_at, 'date'),
        ];
    }

    protected function getPrimaryProviderName(): ?string
    {
        if (! $this->resource->relationLoaded('serverProviders')) {
            return $this->provider?->name;
        }

        $providers = $this->serverProviders;
        $primary = $providers->firstWhere('pivot.is_primary', true) ?? $providers->first();

        return $primary?->name;
    }
}
