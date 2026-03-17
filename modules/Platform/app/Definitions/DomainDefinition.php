<?php

declare(strict_types=1);

namespace Modules\Platform\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Platform\Http\Requests\DomainRequest;
use Modules\Platform\Models\Domain;

class DomainDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'platform.domains';

    protected string $permissionPrefix = 'domains';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Domain::class;
    }

    public function getRequestClass(): ?string
    {
        return DomainRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('name')
                ->label('Domain')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('260px'),

            Column::make('agency_name')
                ->label('Agency')
                ->sortable('agency_id')
                ->width('190px'),

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('140px'),

            Column::make('registrar_name')
                ->label('Registrar')
                ->sortable()
                ->width('180px'),

            Column::make('expiry_date')
                ->label('Expires')
                ->template('platform_expiry')
                ->sortable()
                ->width('140px'),

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
            Filter::select('agency_id')->label('Agency')->placeholder('All Agencies'),
            Filter::select('type')->label('Type')->placeholder('All Types'),
            Filter::select('registrar_id')->label('Registrar')->placeholder('All Registrars'),
            Filter::dateRange('registered_date')->label('Registered Date'),
            Filter::dateRange('expiry_date')->label('Expiry Date'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success')->value('active'),
            StatusTab::make('inactive')->label('Inactive')->icon('ri-close-circle-line')->color('secondary')->value('inactive'),
            StatusTab::make('expired')->label('Expired')->icon('ri-time-line')->color('danger')->value('expired'),
            StatusTab::make('pending')->label('Pending')->icon('ri-loader-line')->color('warning')->value('pending'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}
