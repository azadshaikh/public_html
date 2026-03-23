<?php

declare(strict_types=1);

namespace Modules\Customers\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Customers\Http\Requests\CustomerContactRequest;
use Modules\Customers\Models\CustomerContact;

class CustomerContactDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.customers.contacts';

    protected string $permissionPrefix = 'customer_contacts';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return CustomerContact::class;
    }

    public function getRequestClass(): ?string
    {
        return CustomerContactRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('customer_name')
                ->label('Customer')
                ->searchable([
                    'customer.company_name',
                    'customer.contact_first_name',
                    'customer.contact_last_name',
                    'customer.email',
                ])
                ->link('customer_show_url'),

            Column::make('full_name')
                ->label('Contact')
                ->sortable('first_name')
                ->searchable(['first_name', 'last_name'])
                ->link('show_url'),

            Column::make('email')
                ->label('Email')
                ->searchable(),

            Column::make('phone')
                ->label('Phone'),

            Column::make('is_primary')
                ->label('Primary')
                ->template('boolean'),

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
            Filter::search('email')
                ->label('Email')
                ->placeholder('Search email...'),

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
