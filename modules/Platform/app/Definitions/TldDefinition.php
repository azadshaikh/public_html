<?php

declare(strict_types=1);

namespace Modules\Platform\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Platform\Http\Requests\TldRequest;
use Modules\Platform\Models\Tld;

class TldDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'platform.tlds';

    protected string $permissionPrefix = 'tlds';

    protected ?string $statusField = null;

    public function getModelClass(): string
    {
        return Tld::class;
    }

    public function getRequestClass(): ?string
    {
        return TldRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('tld')
                ->label('TLD')
                ->sortable('tld_order')
                ->searchable()
                ->width('120px'),

            Column::make('whois_server')
                ->label('Whois Server')
                ->sortable()
                ->width('220px'),

            Column::make('price')
                ->label('Price')
                ->sortable()
                ->width('110px'),

            Column::make('sale_price')
                ->label('Sale Price')
                ->sortable()
                ->width('110px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('110px'),

            Column::make('is_suggested')
                ->label('Suggested')
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
            Filter::search('tld')->label('TLD')->placeholder('Search TLD...'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success'),
            StatusTab::make('inactive')->label('Inactive')->icon('ri-close-circle-line')->color('danger'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('secondary'),
        ];
    }
}
