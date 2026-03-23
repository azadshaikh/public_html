<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use App\Traits\DateTimeFormattingTrait;
use Modules\Helpdesk\Definitions\TicketDefinition;
use Modules\Helpdesk\Models\Ticket;

/**
 * @mixin Ticket
 */
class TicketResource extends ScaffoldResource
{
    use DateTimeFormattingTrait;

    protected function definition(): ScaffoldDefinition
    {
        return new TicketDefinition;
    }

    protected function customFields(): array
    {
        $status = (string) ($this->status ?? '');
        $priority = (string) ($this->priority ?? '');

        $statusLabel = match ($status) {
            'open' => 'Open',
            'pending' => 'Pending',
            'resolved' => 'Resolved',
            'on_hold' => 'On Hold',
            'closed' => 'Closed',
            'cancelled' => 'Cancelled',
            default => $status !== '' && $status !== '0' ? ucfirst(str_replace('_', ' ', $status)) : '—',
        };

        $statusBadge = match ($status) {
            'open' => 'success',
            'pending' => 'warning',
            'resolved' => 'info',
            'on_hold' => 'secondary',
            'closed' => 'outline',
            'cancelled' => 'danger',
            default => 'secondary',
        };

        if (! empty($this->deleted_at)) {
            $statusLabel = 'Trashed';
            $statusBadge = 'destructive';
        }

        $priorityLabel = match ($priority) {
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
            default => $priority !== '' && $priority !== '0' ? ucfirst($priority) : '—',
        };

        $priorityBadge = match ($priority) {
            'low' => 'info',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'destructive',
            default => 'secondary',
        };

        $data = [
            'edit_url' => route($this->scaffold()->getRoutePrefix().'.edit', $this->resource->getKey()),
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->resource->getKey()),
            'department_name' => $this->department->name ?? '—',
            'raised_by_name' => $this->user->full_name ?? $this->user->name ?? '—',
            'assigned_to_name' => $this->assignedTo->full_name ?? $this->assignedTo->name ?? '—',

            'priority_label' => $priorityLabel,
            'priority_badge' => $priorityBadge,
            'status_label' => $statusLabel,
            'status_badge' => $statusBadge,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        return $this->formatDateTimeFields($data, dateFields: ['created_at', 'updated_at']);
    }
}
