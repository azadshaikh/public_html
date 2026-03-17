<?php

declare(strict_types=1);

namespace Modules\Platform\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Platform\Http\Requests\ProviderRequest;
use Modules\Platform\Models\Provider;

class ProviderDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'platform.providers';

    protected string $permissionPrefix = 'providers';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Provider::class;
    }

    public function getRequestClass(): ?string
    {
        return ProviderRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('name')
                ->label('Name')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('240px'),

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('160px'),

            Column::make('vendor')
                ->label('Vendor')
                ->template('badge')
                ->sortable()
                ->width('160px'),

            Column::make('email')
                ->label('Email')
                ->sortable()
                ->width('220px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('created_at')
                ->label('Created')
                ->sortable()
                ->width('120px'),

            Column::make('_actions')->label('Actions')->template('actions')->excludeFromExport()->width('90px'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('type')
                ->label('Type')
                ->placeholder('All Types')
                ->options(Provider::getTypeOptions()),
            Filter::select('vendor')
                ->label('Vendor')
                ->placeholder('All Vendors')
                ->options(Provider::getVendorOptions()),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success')->value('active'),
            StatusTab::make('inactive')->label('Inactive')->icon('ri-close-circle-line')->color('warning')->value('inactive'),
            StatusTab::make('suspended')->label('Suspended')->icon('ri-pause-circle-line')->color('danger')->value('suspended'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}
