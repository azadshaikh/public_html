<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Http\Requests\GroupItemRequest;
use App\Models\GroupItem;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

class GroupItemDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.masters.groups.items';

    protected string $permissionPrefix = 'group_items';

    protected ?string $statusField = 'status';

    public function __construct(
        /**
         * Group ID for nested route generation.
         * Can be passed via constructor or retrieved from route.
         */
        protected ?int $groupId = null
    ) {}

    public function getModelClass(): string
    {
        return GroupItem::class;
    }

    public function getRequestClass(): ?string
    {
        return GroupItemRequest::class;
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

            Column::make('value')
                ->label('Value')
                ->sortable()
                ->searchable(),

            Column::make('is_default')
                ->label('Default')
                ->template('badge')
                ->sortable(),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable(),

            Column::make('created_at')
                ->label('Created')
                ->datetime()
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
            Filter::select('is_default')
                ->label('Default')
                ->options([
                    ['value' => '1', 'label' => 'Yes'],
                    ['value' => '0', 'label' => 'No'],
                ])
                ->placeholder('All'),

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

    /**
     * Override toDataGridConfig to handle nested routes with group parameter
     */
    public function toDataGridConfig(): array
    {
        $groupId = $this->getGroupId();

        return [
            'entity' => [
                'name' => $this->getEntityName(),
                'plural' => $this->getEntityPlural(),
            ],
            'columns' => collect($this->columns())
                ->filter(fn ($col): bool => $col->visible)
                ->map(fn ($col): array => $col->toArray())
                ->values()
                ->all(),
            'filters' => collect($this->filters())
                ->map(fn ($filter): array => $filter->toArray())
                ->all(),
            'actions' => collect($this->actions())
                ->filter(fn ($action): bool => $action->authorized())
                ->map(fn ($action): array => $action->toArray())
                ->all(),
            'statusTabs' => collect($this->statusTabs())
                ->map(function ($tab) use ($groupId): array {
                    // Auto-generate URL if not set
                    if (! $tab->url) {
                        $tab->url = route($this->getIndexRoute(), [
                            'group' => $groupId,
                            'status' => $tab->key ?: null,
                        ]);
                    }

                    return $tab->toArray();
                })
                ->all(),
            'settings' => [
                'perPage' => $this->getPerPage(),
                'defaultSort' => $this->getDefaultSort(),
                'defaultSortDirection' => $this->getDefaultSortDirection(),
                'statusField' => $this->getStatusField(),
                'enableBulkActions' => $this->hasBulkActions(),
                'enableExport' => $this->hasExport(),
            ],
            'routes' => [
                'index' => route($this->getIndexRoute(), ['group' => $groupId]),
                'create' => route($this->getCreateRoute(), ['group' => $groupId]),
                'bulkAction' => route($this->getBulkActionRoute(), ['group' => $groupId]),
            ],
        ];
    }

    /**
     * Get the group ID from constructor or route
     */
    protected function getGroupId(): ?int
    {
        if ($this->groupId !== null) {
            return $this->groupId;
        }

        // Try to get from route
        $routeGroup = request()->route('group');
        if ($routeGroup) {
            return (int) $routeGroup;
        }

        return null;
    }
}
