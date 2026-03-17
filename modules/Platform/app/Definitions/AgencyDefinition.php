<?php

declare(strict_types=1);

namespace Modules\Platform\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Platform\Http\Requests\AgencyRequest;
use Modules\Platform\Models\Agency;

class AgencyDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'platform.agencies';

    protected string $permissionPrefix = 'agencies';

    protected ?string $statusField = 'status';

    protected bool $goldenPathExample = true;

    public function getModelClass(): string
    {
        return Agency::class;
    }

    public function getRequestClass(): ?string
    {
        return AgencyRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('uid')
                ->label('UID')
                ->sortable()
                ->searchable()
                ->template('platform_uid')
                ->width('110px'),

            Column::make('name')
                ->label('Agency Name')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('240px'),

            Column::make('email')
                ->label('Email')
                ->sortable()
                ->width('220px'),

            Column::make('websites_count')
                ->label('Websites')
                ->template('badge')
                ->center()
                ->width('90px'),

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('130px'),

            Column::make('plan')
                ->label('Plan')
                ->template('badge')
                ->sortable()
                ->width('130px'),

            Column::make('owner_name')
                ->label('Owner')
                ->sortable('owner_id')
                ->width('180px'),

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
            Filter::select('type')->label('Type')->placeholder('All Types'),
            Filter::select('owner_id')->label('Owner')->placeholder('All Owners'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success')->value('active'),
            StatusTab::make('inactive')->label('Inactive')->icon('ri-close-circle-line')->color('warning')->value('inactive'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}
