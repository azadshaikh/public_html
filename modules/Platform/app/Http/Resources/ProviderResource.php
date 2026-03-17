<?php

namespace Modules\Platform\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Carbon\Carbon;
use Modules\Platform\Definitions\ProviderDefinition;

/**
 * @property int $id
 * @property string|null $type
 * @property string|null $vendor
 * @property string|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProviderResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new ProviderDefinition;
    }

    protected function customFields(): array
    {
        $typeConfig = config('platform.provider.types.'.$this->type, []);
        $typeColor = $typeConfig['color'] ?? 'secondary';

        $vendorConfig = config('platform.provider.vendors.'.$this->vendor, []);
        $vendorColor = $vendorConfig['color'] ?? 'secondary';

        $statusConfig = config('platform.provider.statuses.'.$this->status, []);
        $statusColor = $statusConfig['color'] ?? 'secondary';

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'type_label' => $typeConfig['label'] ?? ucfirst((string) $this->type),
            'type_class' => sprintf('bg-%s-subtle text-%s', $typeColor, $typeColor),

            'vendor_label' => $vendorConfig['label'] ?? ucfirst((string) $this->vendor),
            'vendor_class' => sprintf('bg-%s-subtle text-%s', $vendorColor, $vendorColor),

            'status_label' => $statusConfig['label'] ?? ucfirst((string) $this->status),
            'status_class' => sprintf('bg-%s-subtle text-%s', $statusColor, $statusColor),

            'created_at' => app_date_time_format($this->created_at, 'date'),
            'updated_at' => app_date_time_format($this->updated_at, 'date'),
        ];
    }
}
