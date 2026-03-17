<?php

namespace Modules\Platform\Http\Resources;

use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Carbon\Carbon;
use Modules\Platform\Definitions\AgencyDefinition;

/**
 * @property int $id
 * @property string|null $type
 * @property string|null $plan
 * @property string|null $status
 * @property int|null $websites_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $owner
 */
class AgencyResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new AgencyDefinition;
    }

    protected function customFields(): array
    {
        $typeConfig = config('platform.agency_types.'.$this->type, []);
        $typeColor = $typeConfig['color'] ?? 'secondary';
        $planLabel = $this->getPlanLabel();

        $planColor = match ($this->plan) {
            'starter' => 'info',
            'growth' => 'success',
            'reseller' => 'primary',
            'custom' => 'warning',
            default => 'secondary',
        };

        $statusConfig = config('platform.agency_statuses.'.$this->status, []);
        $statusColor = $statusConfig['color'] ?? 'secondary';

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            'owner_name' => $this->owner
                ? trim(($this->owner->first_name ?? '').' '.($this->owner->last_name ?? ''))
                : '-',

            'websites_count' => (int) ($this->websites_count ?? 0),
            'websites_count_class' => 'bg-primary-subtle text-primary',

            'type_label' => $typeConfig['label'] ?? ($this->type ? (string) $this->type : '-'),
            'type_class' => sprintf('bg-%s-subtle text-%s', $typeColor, $typeColor),

            'plan_label' => $planLabel,
            'plan_class' => sprintf('bg-%s-subtle text-%s', $planColor, $planColor),

            'status_label' => $statusConfig['label'] ?? ucfirst((string) $this->status),
            'status_class' => sprintf('bg-%s-subtle text-%s', $statusColor, $statusColor),

            'created_at' => app_date_time_format($this->created_at, 'date'),
            'updated_at' => app_date_time_format($this->updated_at, 'date'),
        ];
    }

    private function getPlanLabel(): string
    {
        $plans = config('astero.agency_plans', []);

        $plan = (string) ($this->plan ?? '');

        return $plans[$plan]['label'] ?? ucfirst($plan);
    }
}
