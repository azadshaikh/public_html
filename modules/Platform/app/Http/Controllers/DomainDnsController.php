<?php

namespace Modules\Platform\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Platform\Definitions\DomainDnsRecordDefinition;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\DomainDnsRecord;
use Modules\Platform\Services\DomainDnsRecordService;

class DomainDnsController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly DomainDnsRecordService $dnsRecordService
    ) {}

    public static function middleware(): array
    {
        return (new DomainDnsRecordDefinition)->getMiddleware();
    }

    protected function service(): DomainDnsRecordService
    {
        return $this->dnsRecordService;
    }

    protected function inertiaPage(): string
    {
        return 'platform/dns';
    }

    protected function getIndexViewData(Request $request): array
    {
        $domainId = $request->integer('domain_id');
        $domain = $domainId ? Domain::query()->find($domainId) : null;

        return [
            'domain' => $domain ? [
                'id' => $domain->getKey(),
                'name' => $domain->name,
            ] : null,
        ];
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var DomainDnsRecord $dnsRecord */
        $dnsRecord = $model;
        $domain = $dnsRecord->exists
            ? Domain::query()->findOrFail((int) $dnsRecord->domain_id)
            : $this->resolveDomainForForm();

        return [
            'domain' => [
                'id' => $domain->getKey(),
                'name' => $domain->name,
            ],
            'initialValues' => [
                'domain_id' => (string) $domain->getKey(),
                'name' => (string) ($dnsRecord->name ?? ''),
                'type' => isset($dnsRecord->type) ? (string) $dnsRecord->type : '',
                'value' => (string) ($dnsRecord->value ?? ''),
                'ttl' => isset($dnsRecord->ttl) ? (string) $dnsRecord->ttl : '3600',
                'priority' => isset($dnsRecord->priority) ? (string) $dnsRecord->priority : '',
                'weight' => isset($dnsRecord->weight) ? (string) $dnsRecord->weight : '',
                'port' => isset($dnsRecord->port) ? (string) $dnsRecord->port : '',
                'disabled' => (bool) ($dnsRecord->disabled ?? false),
                'record_id' => (string) ($dnsRecord->record_id ?? ''),
                'zone_id' => (string) ($dnsRecord->zone_id ?? ''),
            ],
            'typeOptions' => $this->dnsRecordService->getDnsTypeOptions(),
            'ttlOptions' => $this->dnsRecordService->getTtlOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var DomainDnsRecord $dnsRecord */
        $dnsRecord = $model;

        return [
            'id' => $dnsRecord->getKey(),
            'name' => $dnsRecord->name,
            'type_label' => config('platform.domain.record_types.'.$dnsRecord->type.'.label'),
        ];
    }

    protected function transformModelForShow(Model $model): array
    {
        /** @var DomainDnsRecord $dnsRecord */
        $dnsRecord = $model;
        $dnsRecord->loadMissing('domain:id,name');

        return [
            'id' => $dnsRecord->getKey(),
            'domain_id' => $dnsRecord->domain_id,
            'domain_name' => $dnsRecord->domain?->name,
            'name' => $dnsRecord->name,
            'type' => $dnsRecord->type,
            'type_label' => config('platform.domain.record_types.'.$dnsRecord->type.'.label', (string) $dnsRecord->type),
            'value' => $dnsRecord->value,
            'ttl' => $dnsRecord->ttl,
            'priority' => $dnsRecord->priority,
            'weight' => $dnsRecord->weight,
            'port' => $dnsRecord->port,
            'disabled' => (bool) $dnsRecord->disabled,
            'record_id' => $dnsRecord->record_id,
            'zone_id' => $dnsRecord->zone_id,
            'created_at' => app_date_time_format($dnsRecord->created_at, 'datetime'),
            'updated_at' => app_date_time_format($dnsRecord->updated_at, 'datetime'),
            'deleted_at' => app_date_time_format($dnsRecord->deleted_at, 'datetime'),
        ];
    }

    protected function getAfterStoreRedirectUrl(Model $model): string
    {
        return route('platform.dns.show', $model);
    }

    protected function capturePreviousValues(Model $model): array
    {
        if (! $model instanceof DomainDnsRecord) {
            return [];
        }

        return [
            'name' => $model->name,
            'type' => $model->type,
            'value' => $model->value,
            'ttl' => $model->ttl,
            'disabled' => $model->disabled,
        ];
    }

    private function resolveDomainForForm(): Domain
    {
        $domainId = request()->integer('domain_id');
        if (! $domainId && app()->runningInConsole()) {
            return new Domain([
                'id' => 0,
                'name' => '',
            ]);
        }

        abort_unless((bool) $domainId, 404, 'domain_id is required');

        /** @var Domain $domain */
        $domain = Domain::query()->findOrFail($domainId);

        return $domain;
    }
}
