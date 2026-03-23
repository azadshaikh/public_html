<?php

declare(strict_types=1);

namespace Modules\Billing\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Billing\Http\Requests\TaxRequest;
use Modules\Billing\Models\Tax;

class TaxDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'app.billing.taxes';

    protected string $permissionPrefix = 'taxes';

    /**
     * Status field is null because is_active is a boolean handled in TaxResource.
     */
    protected ?string $statusField = null;

    public function getModelClass(): string
    {
        return Tax::class;
    }

    public function getRequestClass(): ?string
    {
        return TaxRequest::class;
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
                ->label('Name')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('180px'),

            Column::make('code')
                ->label('Code')
                ->sortable()
                ->searchable()
                ->width('100px'),

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('100px'),

            Column::make('formatted_rate')
                ->label('Rate')
                ->sortable('rate')
                ->width('100px'),

            Column::make('country')
                ->label('Country')
                ->sortable()
                ->searchable()
                ->width('100px'),

            Column::make('state')
                ->label('State')
                ->sortable()
                ->searchable()
                ->width('100px'),

            Column::make('priority')
                ->label('Priority')
                ->sortable()
                ->width('80px'),

            Column::make('is_active')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('100px'),

            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    // ================================================================
    // DATAGRID FILTERS
    // ================================================================

    public function filters(): array
    {
        return [
            Filter::select('type')
                ->label('Type')
                ->options([
                    ['value' => 'percentage', 'label' => 'Percentage'],
                    ['value' => 'fixed', 'label' => 'Fixed Amount'],
                ])
                ->placeholder('All Types'),

            Filter::select('country')
                ->label('Country')
                ->options($this->getCountryOptions())
                ->placeholder('All Countries'),

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
    // VIEW CONFIGURATION
    // ================================================================

    public function getViewPath(): string
    {
        return 'billing::taxes';
    }

    /**
     * Get country options for filter dropdown.
     *
     * @return array<int, array{value: string, label: string}>
     */
    protected function getCountryOptions(): array
    {
        return [
            ['value' => 'US', 'label' => 'United States'],
            ['value' => 'CA', 'label' => 'Canada'],
            ['value' => 'GB', 'label' => 'United Kingdom'],
            ['value' => 'AU', 'label' => 'Australia'],
            ['value' => 'IN', 'label' => 'India'],
            ['value' => 'DE', 'label' => 'Germany'],
            ['value' => 'FR', 'label' => 'France'],
        ];
    }
}
