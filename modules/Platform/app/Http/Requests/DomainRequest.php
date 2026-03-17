<?php

namespace Modules\Platform\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Platform\Definitions\DomainDefinition;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;

class DomainRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $typeKeys = array_keys(config('platform.domain.types', []));
        $statusKeys = array_keys(config('platform.domain.statuses', []));

        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueRule('name')],
            'type' => ['nullable', 'string', Rule::in($typeKeys)],
            'agency_id' => ['nullable', 'integer', $this->existsRule('platform_agencies', 'id')],
            'status' => ['required', 'string', Rule::in($statusKeys)],
            'registrar_id' => [
                'nullable',
                'integer',
                Rule::exists('platform_providers', 'id')->where('type', Provider::TYPE_DOMAIN_REGISTRAR),
            ],
            'registrar_name' => ['nullable', 'string', 'max:255'],
            'registered_date' => ['nullable', 'date'],
            'expires_date' => ['nullable', 'date', 'after:registered_date'],
            'updated_date' => ['nullable', 'date'],
            'domain_name_server_1' => ['nullable', 'string', 'max:255'],
            'domain_name_server_2' => ['nullable', 'string', 'max:255'],
            'domain_name_server_3' => ['nullable', 'string', 'max:255'],
            'domain_name_server_4' => ['nullable', 'string', 'max:255'],
            'dns_provider' => ['nullable', 'string', 'max:255'],
            'dns_zone_id' => ['nullable', 'string', 'max:255'],

            // Legacy fields (kept for compatibility)
            'account_group_id' => ['nullable', 'array'],
            'account_group_id.*' => ['nullable', 'integer'],
            'domain_account_username' => ['nullable', 'array'],
            'domain_account_username.*' => ['nullable', 'string', 'max:255'],
            'domain_account_password' => ['nullable', 'array'],
            'domain_account_password.*' => ['nullable', 'string'],
            'domain_account_api_key' => ['nullable', 'array'],
            'domain_account_api_key.*' => ['nullable', 'string'],
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new DomainDefinition;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('name') && $this->filled('domain_name')) {
            $this->merge([
                'name' => Domain::getDomain((string) $this->input('domain_name')),
            ]);
        }
    }
}
