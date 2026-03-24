<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Agency\Definitions\AgencyTicketDefinition;
use Modules\Helpdesk\Models\Ticket;

/** @mixin Ticket */
class AgencyTicketResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new AgencyTicketDefinition;
    }

    protected function customFields(): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'show_url' => route('agency.tickets.show', $this->id),

            'status' => $this->status,
            'status_label' => ucfirst(str_replace('_', ' ', $this->status ?? 'unknown')),
            'status_class' => match ($this->status) {
                'open' => 'bg-success-subtle text-success-emphasis',
                'pending' => 'bg-warning-subtle text-warning-emphasis',
                'resolved' => 'bg-info-subtle text-info-emphasis',
                'on_hold' => 'bg-secondary-subtle text-secondary-emphasis',
                'cancelled' => 'bg-danger-subtle text-danger-emphasis',
                'closed' => 'bg-secondary-subtle text-secondary-emphasis',
                default => 'bg-secondary-subtle text-secondary-emphasis',
            },

            'last_updated' => $this->updated_at?->diffForHumans() ?? 'N/A',
        ];
    }
}
