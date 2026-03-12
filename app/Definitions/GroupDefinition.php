<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Http\Requests\GroupRequest;
use App\Models\Group;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

class GroupDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.masters.groups';

    protected string $permissionPrefix = 'groups';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Group::class;
    }

    public function getRequestClass(): ?string
    {
        return GroupRequest::class;
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
                ->searchable()
                ->link('show_url'),

            Column::make('slug')
                ->label('Slug')
                ->sortable()
                ->searchable(),

            Column::make('items_count')
                ->label('Items')
                ->sortable()
                ->width('100px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('created_at')
                ->label('Created')
                ->datetime()
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
                ->value('active'),

            StatusTab::make('inactive')
                ->label('Inactive')
                ->value('inactive'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }
}
