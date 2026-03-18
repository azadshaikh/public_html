<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Http\Requests\EmailTemplateRequest;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

class EmailTemplateDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'app.masters.email.templates';

    protected string $permissionPrefix = 'email_templates';

    protected bool $requiresSuperUserAccess = true;

    protected ?string $statusField = 'status';

    protected bool $includeActionConfigInInertia = false;

    protected bool $includeEmptyStateConfigInInertia = false;

    protected bool $includeRowActionsInInertiaRows = false;

    public function getModelClass(): string
    {
        return EmailTemplate::class;
    }

    public function getRequestClass(): ?string
    {
        return EmailTemplateRequest::class;
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
                ->width('40px')
                ->excludeFromExport(),

            Column::make('name')
                ->label('Template')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('220px'),

            Column::make('subject')
                ->label('Subject')
                ->sortable()
                ->searchable(),

            Column::make('provider_name')
                ->label('Provider')
                ->sortable(false),

            Column::make('status')
                ->label('Status')
                ->badge()
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

    public function actions(): array
    {
        $routePrefix = $this->getRoutePrefix();

        return [
            Action::make('show')
                ->label('View')
                ->icon('ri-eye-line')
                ->route($routePrefix.'.show')
                ->forRow(),

            Action::make('edit')
                ->label('Edit')
                ->icon('ri-pencil-line')
                ->route($routePrefix.'.edit')
                ->hideOnStatus('trash')
                ->forRow(),

            Action::make('delete')
                ->label('Move to Trash')
                ->icon('ri-delete-bin-line')
                ->danger()
                ->route($routePrefix.'.destroy')
                ->method('DELETE')
                ->confirm('Are you sure you want to move this item to trash?')
                ->confirmBulk('Move {count} items to trash?')
                ->hideOnStatus('trash')
                ->forBoth(),

            Action::make('restore')
                ->label('Restore')
                ->icon('ri-refresh-line')
                ->success()
                ->route($routePrefix.'.restore')
                ->method('PATCH')
                ->confirm('Are you sure you want to restore this item?')
                ->confirmBulk('Restore {count} items?')
                ->showOnStatus('trash')
                ->forBoth(),

            Action::make('force_delete')
                ->label('Delete Permanently')
                ->icon('ri-delete-bin-fill')
                ->danger()
                ->route($routePrefix.'.force-delete')
                ->method('DELETE')
                ->confirm('⚠️ This cannot be undone!')
                ->confirmBulk('⚠️ Permanently delete {count} items? This cannot be undone!')
                ->showOnStatus('trash')
                ->forBoth(),
        ];
    }

    // ================================================================
    // DATAGRID FILTERS
    // ================================================================

    public function filters(): array
    {
        return [
            Filter::select('provider_id')
                ->label('Provider')
                ->options($this->getProviderOptions())
                ->placeholder('All Providers'),

            Filter::dateRange('created_at')
                ->label('Created Date'),
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

    // ================================================================
    // HELPERS
    // ================================================================

    private function getProviderOptions(): array
    {
        return EmailProvider::getActiveProvidersForSelect();
    }
}
