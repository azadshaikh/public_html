<?php

declare(strict_types=1);

namespace Modules\Todos\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Todos\Http\Requests\TodoRequest;
use Modules\Todos\Models\Todo;

class TodoDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.todos';

    protected string $permissionPrefix = 'todos';

    protected ?string $statusField = 'status';

    protected bool $includeActionConfigInInertia = false;

    protected bool $includeEmptyStateConfigInInertia = false;

    protected bool $includeRowActionsInInertiaRows = false;

    public function getModelClass(): string
    {
        return Todo::class;
    }

    public function getRequestClass(): ?string
    {
        return TodoRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('title')
                ->label('Title')
                ->sortable()
                ->searchable()
                ->link('show_url'),

            Column::make('priority')
                ->label('Priority')
                ->template('badge')
                ->sortable(),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable(),

            Column::make('assigned_to_name')
                ->label('Assigned To'),

            Column::make('due_date_formatted')
                ->label('Due Date')
                ->sortable('due_date'),

            Column::make('created_at_formatted')
                ->label('Created')
                ->sortable('created_at'),

            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('priority')
                ->label('Priority')
                ->options([
                    ['value' => 'low', 'label' => 'Low'],
                    ['value' => 'medium', 'label' => 'Medium'],
                    ['value' => 'high', 'label' => 'High'],
                    ['value' => 'critical', 'label' => 'Critical'],
                ])
                ->placeholder('All Priorities'),

            Filter::select('visibility')
                ->label('Visibility')
                ->options([
                    ['value' => 'private', 'label' => 'Private'],
                    ['value' => 'public', 'label' => 'Public'],
                ])
                ->placeholder('All'),

            Filter::dateRange('due_date')
                ->label('Due Date'),
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

            StatusTab::make('pending')
                ->label('Pending')
                ->value('pending')
                ->icon('ri-time-line')
                ->color('warning'),

            StatusTab::make('in_progress')
                ->label('In Progress')
                ->value('in_progress')
                ->icon('ri-loader-line')
                ->color('info'),

            StatusTab::make('completed')
                ->label('Completed')
                ->value('completed')
                ->icon('ri-checkbox-circle-line')
                ->color('success'),

            StatusTab::make('on_hold')
                ->label('On Hold')
                ->value('on_hold')
                ->icon('ri-pause-circle-line')
                ->color('secondary'),

            StatusTab::make('cancelled')
                ->label('Cancelled')
                ->value('cancelled')
                ->icon('ri-close-circle-line')
                ->color('dark'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }
}
