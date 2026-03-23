<?php

declare(strict_types=1);

namespace Modules\Customers\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Customers\Enums\CustomerSource;
use Modules\Customers\Enums\CustomerTier;
use Modules\Customers\Http\Requests\CustomerRequest;
use Modules\Customers\Models\Customer;

class CustomerDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.customers';

    protected string $permissionPrefix = 'customers';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Customer::class;
    }

    public function getRequestClass(): ?string
    {
        return CustomerRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('company_name_display')
                ->label('Customer')
                ->sortable('company_name')
                ->searchable(['company_name'])
                ->template('customer-identity')
                ->link('show_url'),

            Column::make('email')
                ->label('Email')
                ->searchable(),

            Column::make('phone')
                ->label('Phone'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable(),

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
            Filter::search('company_name')
                ->label('Company')
                ->placeholder('Search company...'),

            Filter::search('email')
                ->label('Email')
                ->placeholder('Search email...'),

            Filter::select('tier')
                ->label('Tier')
                ->options(array_map(fn (CustomerTier $case): array => ['value' => $case->value, 'label' => $case->label()], CustomerTier::cases())),

            Filter::select('source')
                ->label('Source')
                ->options(array_map(fn (CustomerSource $case): array => ['value' => $case->value, 'label' => $case->label()], CustomerSource::cases())),

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
                ->value('active')
                ->icon('ri-check-line')
                ->color('success'),

            StatusTab::make('inactive')
                ->label('Inactive')
                ->value('inactive')
                ->icon('ri-pause-circle-line')
                ->color('secondary'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }
}
