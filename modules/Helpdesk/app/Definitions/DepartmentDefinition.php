<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Definitions;

use App\Models\User;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Helpdesk\Http\Requests\DepartmentRequest;
use Modules\Helpdesk\Models\Department;

class DepartmentDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'helpdesk.departments';

    protected string $permissionPrefix = 'helpdesk_departments';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Department::class;
    }

    public function getRequestClass(): ?string
    {
        return DepartmentRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            Column::make('name')
                ->label('Name')
                ->sortable()
                ->searchable(['name', 'description'])
                ->link('show_url')
                ->width('240px'),

            Column::make('department_head_name')
                ->label('Department Head')
                ->sortable('department_head')
                ->width('220px'),

            Column::make('visibility')
                ->label('Visibility')
                ->template('badge')
                ->sortable(),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable(),

            Column::make('created_at')
                ->label('Created')
                ->sortable(),

            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('department_head')
                ->label('Department Head')
                ->options(User::getActiveUsersForSelect())
                ->placeholder('All Department Heads'),

            Filter::select('visibility')
                ->label('Visibility')
                ->options(collect(config('helpdesk.visibility_options', []))
                    ->mapWithKeys(fn (array $opt): array => [$opt['value'] => $opt['label']])
                    ->toArray())
                ->placeholder('All Visibility'),

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

            StatusTab::make('active')
                ->label('Active')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value('active'),

            StatusTab::make('inactive')
                ->label('Inactive')
                ->icon('ri-pause-circle-line')
                ->color('warning')
                ->value('inactive'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }
}
