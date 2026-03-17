<?php

namespace Modules\Platform\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Platform\Definitions\DomainDefinition;
use Modules\Platform\Models\Domain;

/** @mixin Domain */
class DomainResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new DomainDefinition;
    }

    protected function customFields(): array
    {
        $typeConfig = config('platform.domain.types.'.$this->type, []);
        $typeColor = $typeConfig['color'] ?? 'secondary';

        $statusConfig = config('platform.domain.statuses.'.$this->status, []);
        $statusColor = $statusConfig['color'] ?? 'secondary';

        $expiryDate = $this->expiry_date;
        $daysUntilExpiry = $expiryDate ? (int) round(now()->diffInDays($expiryDate, false)) : null;
        $isExpired = $daysUntilExpiry !== null && $daysUntilExpiry < 0;
        $expiryClass = 'text-muted';

        if ($isExpired) {
            $expiryClass = 'text-danger fw-medium';
        } elseif ($daysUntilExpiry !== null && $daysUntilExpiry <= 30) {
            $expiryClass = 'text-warning';
        }

        $registrarName = data_get($this->resource, 'registrar_name');
        if ($this->resource->relationLoaded('domainRegistrars')) {
            $registrar = $this->domainRegistrars->first();
            if ($registrar !== null && isset($registrar->name)) {
                $registrarName = $registrar->name;
            }
        }

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'agency_name' => $this->agency->name ?? '-',

            'type_label' => $typeConfig['label'] ?? ($this->type ? ucfirst((string) $this->type) : '-'),
            'type_class' => sprintf('bg-%s-subtle text-%s', $typeColor, $typeColor),

            'registrar_name' => $registrarName ?? '-',

            'expiry_date' => app_date_time_format($expiryDate, 'date'),
            'days_until_expiry' => $daysUntilExpiry,
            'expiry_date_class' => $expiryClass,

            'status_label' => $statusConfig['label'] ?? ucfirst((string) $this->status),
            'status_class' => sprintf('bg-%s-subtle text-%s', $statusColor, $statusColor),

            'created_at' => app_date_time_format($this->created_at, 'date'),
            'updated_at' => app_date_time_format($this->updated_at, 'date'),
        ];
    }
}
