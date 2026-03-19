<?php

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Platform\Models\Provider;

class AgencyAttachCdnProvidersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_ids' => ['present', 'array'],
            'provider_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('platform_providers', 'id')->where('type', Provider::TYPE_CDN),
            ],
            'primary_provider_id' => [
                'nullable',
                'integer',
                Rule::exists('platform_providers', 'id')->where('type', Provider::TYPE_CDN),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $primaryProviderId = $this->input('primary_provider_id');
            $providerIds = collect($this->input('provider_ids', []))
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($primaryProviderId !== null && ! in_array((int) $primaryProviderId, $providerIds, true)) {
                $validator->errors()->add('primary_provider_id', 'The selected primary provider must be included in provider_ids.');
            }
        });
    }
}
