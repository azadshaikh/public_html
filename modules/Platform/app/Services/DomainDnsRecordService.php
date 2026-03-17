<?php

namespace Modules\Platform\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Platform\Definitions\DomainDnsRecordDefinition;
use Modules\Platform\Http\Resources\DomainDnsRecordResource;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\DomainDnsRecord;

class DomainDnsRecordService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new DomainDnsRecordDefinition;
    }

    public function getDataGridConfig(): array
    {
        $config = $this->scaffold()->toDataGridConfig();

        foreach (($config['filters'] ?? []) as $i => $filter) {
            if (($filter['key'] ?? null) === 'domain_id') {
                $config['filters'][$i]['options'] = Domain::query()
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray();
            }

            if (($filter['key'] ?? null) === 'type') {
                $config['filters'][$i]['options'] = collect(config('platform.domain.record_types', []))
                    ->mapWithKeys(fn ($item): array => [(string) ($item['value'] ?? '') => ($item['label'] ?? '')])
                    ->toArray();
            }

            if (($filter['key'] ?? null) === 'ttl') {
                $config['filters'][$i]['options'] = collect(config('platform.domain.dns_ttls', []))
                    ->mapWithKeys(fn ($item): array => [(string) ($item['value'] ?? '') => ($item['label'] ?? '')])
                    ->toArray();
            }
        }

        return $config;
    }

    public function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('domain_id')) {
            $query->where('domain_id', $request->integer('domain_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->integer('type'));
        }

        if ($request->filled('ttl')) {
            $query->where('ttl', $request->integer('ttl'));
        }

        if ($request->filled('disabled')) {
            $query->where('disabled', $request->boolean('disabled'));
        }
    }

    public function find(int $id): DomainDnsRecord
    {
        /** @var DomainDnsRecord $record */
        $record = DomainDnsRecord::withTrashed()->findOrFail($id);

        return $record;
    }

    public function getDnsTypeOptions(): array
    {
        return collect(config('platform.domain.record_types', []))
            ->values()
            ->map(fn (array $item): array => [
                'value' => $item['value'] ?? null,
                'label' => $item['label'] ?? null,
            ])
            ->filter(fn (array $item): bool => $item['value'] !== null && $item['label'] !== null)
            ->values()
            ->all();
    }

    public function getTtlOptions(): array
    {
        return collect(config('platform.domain.dns_ttls', []))
            ->values()
            ->map(fn (array $item): array => [
                'value' => $item['value'] ?? null,
                'label' => $item['label'] ?? null,
            ])
            ->filter(fn (array $item): bool => $item['value'] !== null && $item['label'] !== null)
            ->values()
            ->all();
    }

    protected function getResourceClass(): ?string
    {
        return DomainDnsRecordResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'domain:id,name',
        ];
    }

    protected function prepareCreateData(array $data): array
    {
        return $this->prepareData($data);
    }

    protected function prepareUpdateData(array $data): array
    {
        return $this->prepareData($data);
    }

    private function prepareData(array $data): array
    {
        return [
            'domain_id' => $data['domain_id'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'record_id' => $data['record_id'] ?? null,
            'type' => $data['type'] ?? 0,
            'name' => $data['name'] ?? null,
            'value' => $data['value'] ?? null,
            'ttl' => $data['ttl'] ?? 3600,
            'priority' => $data['priority'] ?? null,
            'weight' => $data['weight'] ?? null,
            'port' => $data['port'] ?? null,
            'disabled' => $data['disabled'] ?? false,
            'metadata' => $data['metadata'] ?? null,
        ];
    }
}
