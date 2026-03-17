<?php

namespace Modules\Platform\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Platform\Definitions\DomainDnsRecordDefinition;
use Modules\Platform\Models\DomainDnsRecord;

/** @mixin DomainDnsRecord */
class DomainDnsRecordResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new DomainDnsRecordDefinition;
    }

    protected function customFields(): array
    {
        $typeConfig = config('platform.domain.record_types.'.$this->type, []);

        return [
            'type_label' => $typeConfig['label'] ?? (string) $this->type,
            'created_at' => app_date_time_format($this->created_at, 'date'),
            'updated_at' => app_date_time_format($this->updated_at, 'date'),
        ];
    }
}
