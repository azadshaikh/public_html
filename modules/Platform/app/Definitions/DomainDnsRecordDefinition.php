<?php

declare(strict_types=1);

namespace Modules\Platform\Definitions;

use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Illuminate\Support\Facades\App;
use Modules\Platform\Http\Requests\DomainDnsRecordRequest;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\DomainDnsRecord;
use Throwable;

class DomainDnsRecordDefinition extends ScaffoldDefinition
{
    protected string $entityName = 'DomainDnsRecord';

    protected string $entityPlural = 'DNS Records';

    protected string $routePrefix = 'platform.dns';

    protected string $permissionPrefix = 'domain_dns_records';

    protected ?string $statusField = null;

    public function getModelClass(): string
    {
        return DomainDnsRecord::class;
    }

    public function getRequestClass(): ?string
    {
        return DomainDnsRecordRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('name')
                ->label('Hostname')
                ->sortable()
                ->searchable()
                ->width('160px'),

            Column::make('type_label')
                ->label('Type')
                ->sortable('type')
                ->width('110px'),

            Column::make('value')
                ->label('Value')
                ->searchable()
                ->width('260px'),

            Column::make('ttl')
                ->label('TTL')
                ->sortable()
                ->width('110px'),

            Column::make('disabled')
                ->label('Disabled')
                ->boolean()
                ->sortable()
                ->width('110px'),

            Column::make('created_at')
                ->label('Created')
                ->sortable()
                ->width('120px'),

            Column::make('_actions')->label('Actions')->template('actions')->excludeFromExport()->width('90px'),
        ];
    }

    public function filters(): array
    {
        $domainOptions = [];

        try {
            $domainOptions = Domain::query()->orderBy('name')->pluck('name', 'id')->toArray();
        } catch (Throwable $throwable) {
            if (! App::runningInConsole()) {
                throw $throwable;
            }
        }

        return [
            Filter::select('domain_id')
                ->label('Domain')
                ->placeholder('All Domains')
                ->options($domainOptions),
            Filter::select('type')
                ->label('Type')
                ->placeholder('All Types')
                ->options(collect(config('platform.domain.record_types', []))
                    ->map(fn (array $item): array => [
                        'value' => $item['value'] ?? '',
                        'label' => $item['label'] ?? '',
                    ])
                    ->filter(fn (array $item): bool => $item['value'] !== '' && $item['label'] !== '')
                    ->values()
                    ->all()),
            Filter::select('ttl')
                ->label('TTL')
                ->placeholder('All TTLs')
                ->options(collect(config('platform.domain.dns_ttls', []))
                    ->map(fn (array $item): array => [
                        'value' => $item['value'] ?? '',
                        'label' => $item['label'] ?? '',
                    ])
                    ->filter(fn (array $item): bool => $item['value'] !== '' && $item['label'] !== '')
                    ->values()
                    ->all()),
            Filter::boolean('disabled')->label('Disabled'),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit')
                ->icon('ri-pencil-line')
                ->route($this->routePrefix.'.edit')
                ->permission('edit_'.$this->permissionPrefix)
                ->meta([
                    'class' => 'drawer-btn',
                    'attributes' => [
                        'data-bs-toggle' => 'offcanvas',
                        'data-bs-target' => '#domain-drawer',
                        'up-follow' => 'false',
                    ],
                ])
                ->forRow(),

            Action::make('delete')
                ->label('Move to Trash')
                ->icon('ri-delete-bin-line')
                ->route($this->routePrefix.'.destroy')
                ->method('DELETE')
                ->danger()
                ->confirm('Move to trash?')
                ->confirmBulk('Move {count} DNS records to trash?')
                ->permission('delete_'.$this->permissionPrefix)
                ->hideOnStatus('trash')
                ->forBoth(),

            Action::make('restore')
                ->label('Restore')
                ->icon('ri-refresh-line')
                ->route($this->routePrefix.'.restore')
                ->method('PATCH')
                ->success()
                ->confirm('Restore this DNS record?')
                ->confirmBulk('Restore {count} DNS records?')
                ->permission('restore_'.$this->permissionPrefix)
                ->showOnStatus('trash')
                ->forBoth(),

            Action::make('force_delete')
                ->label('Delete Permanently')
                ->icon('ri-delete-bin-fill')
                ->route($this->routePrefix.'.force-delete')
                ->method('DELETE')
                ->danger()
                ->confirm('⚠️ Permanently delete? This cannot be undone!')
                ->confirmBulk('⚠️ Permanently delete {count} DNS records?')
                ->permission('delete_'.$this->permissionPrefix)
                ->showOnStatus('trash')
                ->forBoth(),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}
