<?php

namespace Modules\Platform\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Carbon\Carbon;
use Modules\Platform\Definitions\SecretDefinition;

/**
 * @property int $id
 * @property string|null $type
 * @property bool|int|string|null $is_active
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SecretResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new SecretDefinition;
    }

    protected function customFields(): array
    {
        $typeConfig = config('platform.secret_types.'.$this->type, []);
        $typeColor = $typeConfig['color'] ?? 'secondary';

        $active = (bool) $this->is_active;

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'type_label' => $typeConfig['label'] ?? ucfirst((string) $this->type),
            'type_class' => sprintf('bg-%s-subtle text-%s', $typeColor, $typeColor),

            'is_active' => $active,
            'is_active_label' => $active ? 'Active' : 'Inactive',
            'is_active_class' => $active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary',

            'expires_at' => $this->expires_at ? app_date_time_format($this->expires_at, 'date') : '-',

            'created_at' => app_date_time_format($this->created_at, 'date'),
            'updated_at' => app_date_time_format($this->updated_at, 'date'),
        ];
    }
}
