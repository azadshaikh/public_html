<?php

declare(strict_types=1);

namespace Modules\Platform\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Platform\Http\Requests\ServerRequest;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;

class ServerDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'platform.servers';

    protected string $permissionPrefix = 'servers';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Server::class;
    }

    public function getRequestClass(): ?string
    {
        return ServerRequest::class;
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
                ->width('120px'),

            Column::make('name')
                ->label('Server')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('240px'),

            Column::make('ip')
                ->label('IP')
                ->sortable()
                ->width('160px'),

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('130px'),

            Column::make('provider_name')
                ->label('Provider')
                ->sortable()
                ->width('180px'),

            Column::make('domain_usage')
                ->label('Domains')
                ->template('platform_domain_usage')
                ->sortable('current_domains')
                ->width('150px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('130px'),

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
                ->options(collect(config('platform.server_types', []))
                    ->map(fn (array $item): array => [
                        'value' => $item['value'],
                        'label' => $item['label'],
                    ])
                    ->values()
                    ->all()),
            Filter::select('provider_id')
                ->label('Provider')
                ->placeholder('All Providers')
                ->options(Provider::query()
                    ->ofType(Provider::TYPE_SERVER)
                    ->active()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray()),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success')->value('active'),
            StatusTab::make('failed')->label('Failed')->icon('ri-error-warning-line')->color('danger')->value('failed'),
            StatusTab::make('inactive')->label('Inactive')->icon('ri-close-circle-line')->color('warning')->value('inactive'),
            StatusTab::make('maintenance')->label('Maintenance')->icon('ri-tools-line')->color('warning')->value('maintenance'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}
