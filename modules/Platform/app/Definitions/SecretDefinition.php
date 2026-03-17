<?php

declare(strict_types=1);

namespace Modules\Platform\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Platform\Http\Requests\SecretRequest;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Website;

class SecretDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'platform.secrets';

    protected string $permissionPrefix = 'secrets';

    protected ?string $statusField = null;

    public function getModelClass(): string
    {
        return Secret::class;
    }

    public function getRequestClass(): ?string
    {
        return SecretRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('key')
                ->label('Key')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('220px'),

            Column::make('username')
                ->label('Username')
                ->sortable()
                ->width('190px'),

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('150px'),

            Column::make('is_active')
                ->label('Active')
                ->template('badge')
                ->sortable()
                ->width('110px'),

            Column::make('expires_at')
                ->label('Expires')
                ->sortable()
                ->width('140px'),

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
                ->options(collect(config('platform.secret_types', []))
                    ->map(fn (array $item, string $key): array => [
                        'value' => $key,
                        'label' => $item['label'] ?? $key,
                    ])
                    ->values()
                    ->all()),
            Filter::select('secretable_type')
                ->label('Entity Type')
                ->placeholder('All Entities')
                ->options([
                    Domain::class => 'Domain',
                    Website::class => 'Website',
                    Agency::class => 'Agency',
                    Server::class => 'Server',
                    Provider::class => 'Provider',
                ]),
            Filter::search('key')->label('Key')->placeholder('Search key...'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success'),
            StatusTab::make('inactive')->label('Inactive')->icon('ri-close-circle-line')->color('warning'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}
