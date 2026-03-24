<?php

declare(strict_types=1);

namespace Modules\Agency\Definitions;

use App\Scaffold\Column;
use App\Scaffold\ScaffoldDefinition;
use Modules\Helpdesk\Models\Ticket;

class AgencyTicketDefinition extends ScaffoldDefinition
{
    // Define the route prefix for the agency tickets
    protected string $routePrefix = 'agency.tickets';

    // Disable permission checks for the agency portal as it's customer facing
    // Disable permission checks for the agency portal as it's customer facing
    protected string $permissionPrefix = '';

    protected ?string $statusField = 'status';

    public function getPermissionPrefix(): string
    {
        return '';
    }

    public function getModelClass(): string
    {
        return Ticket::class;
    }

    public function columns(): array
    {
        return [
            Column::make('subject')
                ->label('Your Tickets')
                ->link('show_url')
                ->class('fw-semibold')
                // ->width('50%') // Let it take available space
                ->sortable(false),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->width('120px')
                ->sortable(false),

            Column::make('last_updated')
                ->label('Last Updated')
                ->width('150px')
                ->sortable(false),
        ];
    }

    public function filters(): array
    {
        return [];
    }

    public function actions(): array
    {
        return [];
    }

    public function statusTabs(): array
    {
        return [];
    }
}
