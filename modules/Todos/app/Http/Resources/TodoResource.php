<?php

declare(strict_types=1);

namespace Modules\Todos\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Illuminate\Support\Str;
use Modules\Todos\Definitions\TodoDefinition;
use Modules\Todos\Models\Todo;

/** @mixin Todo */
class TodoResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new TodoDefinition;
    }

    protected function customFields(): array
    {
        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),
            'edit_url' => route($this->scaffold()->getRoutePrefix().'.edit', $this->id),

            // Description preview
            'description_preview' => Str::limit(strip_tags((string) $this->description), 120),

            // Priority badge
            'priority_label' => ucfirst(str_replace('_', ' ', $this->priority)),
            'priority_class' => $this->getPriorityClass(),

            // Status badge
            'status_label' => ucfirst(str_replace('_', ' ', $this->status)),
            'status_class' => $this->getStatusClass(),

            // Dates formatted
            'start_date_formatted' => $this->start_date ? app_date_time_format($this->start_date, 'date') : null,
            'due_date_formatted' => $this->due_date ? app_date_time_format($this->due_date, 'date') : null,
            'completed_at_formatted' => $this->completed_at ? app_date_time_format($this->completed_at, 'datetime') : null,
            'created_at_formatted' => $this->created_at ? app_date_time_format($this->created_at, 'date') : null,
            'updated_at_formatted' => $this->updated_at ? app_date_time_format($this->updated_at, 'date') : null,

            // Relationships
            'assigned_to_name' => $this->assignedTo ? trim(sprintf('%s %s', $this->assignedTo->first_name, $this->assignedTo->last_name)) : 'Unassigned',
            'owner_name' => $this->owner ? trim(sprintf('%s %s', $this->owner->first_name, $this->owner->last_name)) : null,

            // Labels as array
            'labels_list' => $this->labels ? explode(',', $this->labels) : [],

            // Flags
            'is_overdue' => ! $this->completed_at && $this->due_date && $this->due_date->isPast(),
        ];
    }

    protected function getPriorityClass(): string
    {
        return match ($this->priority) {
            'low' => 'bg-success-subtle text-success',
            'medium' => 'bg-info-subtle text-info',
            'high' => 'bg-warning-subtle text-warning',
            'critical' => 'bg-danger-subtle text-danger',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    protected function getStatusClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-warning-subtle text-warning',
            'in_progress' => 'bg-info-subtle text-info',
            'completed' => 'bg-success-subtle text-success',
            'on_hold' => 'bg-secondary-subtle text-secondary',
            'cancelled' => 'bg-dark-subtle text-dark',
            default => 'bg-secondary-subtle text-secondary',
        };
    }
}
