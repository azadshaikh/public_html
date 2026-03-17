<?php

namespace Modules\Platform\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Platform\Definitions\DomainDnsRecordDefinition;

class DomainDnsRecordRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $domainId = $this->integer('domain_id') ?: null;
        $id = $this->getRouteParameter();

        return [
            'domain_id' => ['required', 'integer', $this->existsRule('platform_domains', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('platform_dns_records', 'name')
                    ->where('domain_id', $domainId)
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'type' => ['required', 'integer', 'min:0', 'max:12'],
            'value' => ['required', 'string', 'max:65535'],
            'ttl' => ['required', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'weight' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'port' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'disabled' => ['nullable', 'boolean'],
            'record_id' => ['nullable', 'string', 'max:255'],
            'zone_id' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function fieldLabels(): array
    {
        return [
            'name' => 'Record Name',
            'type' => 'Record Type',
            'value' => 'Record Value',
            'ttl' => 'TTL',
            'priority' => 'Priority',
            'weight' => 'Weight',
            'port' => 'Port',
            'disabled' => 'Disabled',
            'record_id' => 'Record ID',
            'zone_id' => 'Zone ID',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new DomainDnsRecordDefinition;
    }
}
