<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Http\Middleware\CheckQueueMonitorUiConfig;
use App\Http\Middleware\EnsureSuperUserAccess;
use App\Models\Monitor;
use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Illuminate\Routing\Controllers\Middleware;

class QueueMonitorDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'app.masters.queue-monitor';

    protected string $entityName = 'Monitor Entry';

    protected string $entityPlural = 'Monitor Entries';

    protected bool $requiresSuperUserAccess = true;

    /**
     * No permission-based status filter — integer status handled in service.
     */
    protected ?string $statusField = null;

    protected ?string $defaultSort = 'started_at_exact';

    protected string $defaultSortDirection = 'desc';

    public function getModelClass(): string
    {
        return Monitor::class;
    }

    // ================================================================
    // MIDDLEWARE (role-based, not permission-based)
    // ================================================================

    public function getMiddleware(): array
    {
        return [
            new Middleware(EnsureSuperUserAccess::class),
            new Middleware(CheckQueueMonitorUiConfig::class),
        ];
    }

    // ================================================================
    // DATAGRID COLUMNS
    // ================================================================

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width(40),

            Column::make('status')
                ->label('Status')
                ->badge()
                ->sortable()
                ->width(100),

            Column::make('name')
                ->label('Job')
                ->sortable()
                ->searchable(),

            Column::make('queue')
                ->label('Queue')
                ->sortable()
                ->searchable(),

            Column::make('attempt')
                ->label('Attempt')
                ->sortable()
                ->center(),

            Column::make('duration')
                ->label('Duration')
                ->sortable(),

            Column::make('wait')
                ->label('Wait')
                ->sortable(),

            Column::make('started_at')
                ->label('Started')
                ->sortable('started_at_exact'),

            Column::make('exception_message')
                ->label('Error')
                ->template('error_modal'),

            Column::make('_actions')
                ->label('')
                ->actions()
                ->excludeFromExport(),
        ];
    }

    // ================================================================
    // FILTERS — metadata key+value pair
    // ================================================================

    public function filters(): array
    {
        return [
            Filter::search('metadata_key')
                ->label('Metadata Key')
                ->placeholder('e.g. website_id'),

            Filter::search('metadata_value')
                ->label('Metadata Value')
                ->placeholder('e.g. 42'),
        ];
    }

    // ================================================================
    // STATUS TABS
    // ================================================================

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')
                ->label('All')
                ->icon('ri-list-check')
                ->color('primary')
                ->default(),

            StatusTab::make('succeeded')
                ->label('Succeeded')
                ->icon('ri-checkbox-circle-line')
                ->color('success'),

            StatusTab::make('failed')
                ->label('Failed')
                ->icon('ri-close-circle-line')
                ->color('danger'),

            StatusTab::make('running')
                ->label('Running')
                ->icon('ri-loader-4-line')
                ->color('primary'),

            StatusTab::make('queued')
                ->label('Queued')
                ->icon('ri-time-line')
                ->color('secondary'),

            StatusTab::make('stale')
                ->label('Stale')
                ->icon('ri-alarm-warning-line')
                ->color('secondary'),
        ];
    }

    // ================================================================
    // ACTIONS (config-gated)
    // ================================================================

    public function actions(): array
    {
        $actions = [];

        if (config('queue-monitor.ui.allow_deletion')) {
            $actions[] = Action::make('delete')
                ->label('Delete')
                ->icon('ri-delete-bin-line')
                ->route('app.masters.queue-monitor.destroy')
                ->delete()
                ->danger()
                ->confirm('Delete this monitor entry?')
                ->forRow();

            $actions[] = Action::make('delete')
                ->label('Delete Selected')
                ->icon('ri-delete-bin-line')
                ->danger()
                ->forBulk();
        }

        if (config('queue-monitor.ui.allow_purge')) {
            $actions[] = Action::make('purge')
                ->label('Purge All')
                ->icon('ri-delete-bin-2-line')
                ->danger()
                ->forBulk();
        }

        return $actions;
    }
}
