<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use App\Traits\DateTimeFormattingTrait;
use Modules\Helpdesk\Definitions\DepartmentDefinition;
use Modules\Helpdesk\Models\Department;

/**
 * @mixin Department
 */
class DepartmentResource extends ScaffoldResource
{
    use DateTimeFormattingTrait;

    protected function definition(): ScaffoldDefinition
    {
        return new DepartmentDefinition;
    }

    protected function customFields(): array
    {
        $visibility = (string) ($this->visibility ?? '');

        $data = [
            'edit_url' => route($this->scaffold()->getRoutePrefix().'.edit', $this->resource->getKey()),
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->resource->getKey()),
            'department_head_name' => $this->departmentHead->full_name ?? 'Unassigned',

            'visibility_label' => match ($visibility) {
                'public' => 'Public',
                'private' => 'Private',
                default => $visibility !== '' && $visibility !== '0' ? ucfirst($visibility) : '—',
            },
            'visibility_badge' => match ($visibility) {
                'public' => 'info',
                'private' => 'secondary',
                default => 'secondary',
            },

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        return $this->formatDateTimeFields($data, dateFields: ['created_at', 'updated_at']);
    }
}
