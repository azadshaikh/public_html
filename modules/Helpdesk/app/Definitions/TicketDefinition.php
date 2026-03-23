<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Helpdesk\Http\Requests\TicketRequest;
use Modules\Helpdesk\Models\Ticket;

class TicketDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'helpdesk.tickets';

    protected string $permissionPrefix = 'helpdesk_tickets';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Ticket::class;
    }

    public function getRequestClass(): ?string
    {
        return TicketRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            Column::make('ticket_number')
                ->label('Ticket #')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('150px'),

            Column::make('subject')
                ->label('Subject')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('320px'),

            Column::make('department_name')
                ->label('Department')
                ->width('180px'),

            Column::make('raised_by_name')
                ->label('Raised By')
                ->width('180px'),

            Column::make('assigned_to_name')
                ->label('Assignee')
                ->width('180px'),

            Column::make('priority')
                ->label('Priority')
                ->template('badge')
                ->sortable()
                ->width('130px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('140px'),

            Column::make('created_at')
                ->label('Created')
                ->sortable()
                ->width('140px'),

            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('department_id')
                ->label('Department')
                ->options([])
                ->placeholder('All Departments'),

            Filter::select('priority')
                ->label('Priority')
                ->options(config('helpdesk.priority_options', []))
                ->placeholder('All Priorities'),

            Filter::select('assigned_to')
                ->label('Assigned To')
                ->options([])
                ->placeholder('All Assignees'),

            Filter::select('user_id')
                ->label('Raised By')
                ->options([])
                ->placeholder('All Requesters'),

            Filter::dateRange('created_at')
                ->label('Created Date'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')
                ->label('All')
                ->icon('ri-list-check')
                ->color('primary')
                ->default(),

            StatusTab::make('open')
                ->label('Open')
                ->icon('ri-mail-open-line')
                ->color('success')
                ->value('open'),

            StatusTab::make('pending')
                ->label('Pending')
                ->icon('ri-time-line')
                ->color('warning')
                ->value('pending'),

            StatusTab::make('resolved')
                ->label('Resolved')
                ->icon('ri-check-double-line')
                ->color('info')
                ->value('resolved'),

            StatusTab::make('on_hold')
                ->label('On Hold')
                ->icon('ri-pause-circle-line')
                ->color('secondary')
                ->value('on_hold'),

            StatusTab::make('closed')
                ->label('Closed')
                ->icon('ri-lock-line')
                ->color('dark')
                ->value('closed'),

            StatusTab::make('cancelled')
                ->label('Cancelled')
                ->icon('ri-close-circle-line')
                ->color('danger')
                ->value('cancelled'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }
}
