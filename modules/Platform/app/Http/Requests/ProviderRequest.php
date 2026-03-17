<?php

namespace Modules\Platform\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Platform\Definitions\ProviderDefinition;

class ProviderRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $types = array_keys(config('platform.provider.types', []));
        $vendors = array_keys(config('platform.provider.vendors', []));
        $statuses = array_keys(config('platform.provider.statuses', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in($types)],
            'vendor' => ['required', 'string', Rule::in($vendors)],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'credentials' => ['nullable', 'array'],
            'credentials.api_key' => ['nullable', 'string', 'max:500'],
            'credentials.api_token' => ['nullable', 'string', 'max:500'],
            'credentials.api_secret' => ['nullable', 'string', 'max:500'],
            'credentials.api_user' => ['nullable', 'string', 'max:255'],
            'credentials.username' => ['nullable', 'string', 'max:255'],
            'credentials.account_id' => ['nullable', 'string', 'max:255'],
            'credentials.zone_id' => ['nullable', 'string', 'max:255'],
            'credentials.client_ip' => ['nullable', 'ip'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = (string) $this->input('type');
            $vendor = (string) $this->input('vendor');

            if ($type === '' || $vendor === '') {
                return;
            }

            $vendorConfig = config('platform.provider.vendors.'.$vendor);
            $allowedTypes = (array) ($vendorConfig['types'] ?? []);

            if ($vendorConfig && ! in_array($type, $allowedTypes, true)) {
                $validator->errors()->add('vendor', 'The selected vendor is not compatible with the selected provider type.');
            }
        });
    }

    protected function definition(): ScaffoldDefinition
    {
        return new ProviderDefinition;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('status') || empty($this->status)) {
            $this->merge(['status' => 'active']);
        }
    }
}
