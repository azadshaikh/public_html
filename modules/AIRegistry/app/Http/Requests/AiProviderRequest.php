<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\AIRegistry\Definitions\AiProviderDefinition;

class AiProviderRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:64', $this->uniqueRule('slug', 'airegistry_providers')],
            'name' => ['required', 'string', 'max:255'],
            'docs_url' => ['nullable', 'url', 'max:500'],
            'api_key_url' => ['nullable', 'url', 'max:500'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', Rule::in(array_keys(config('airegistry::options.capabilities')))],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'slug' => 'Provider Slug',
            'name' => 'Provider Name',
            'docs_url' => 'Docs URL',
            'api_key_url' => 'API Key URL',
            'capabilities' => 'Capabilities',
            'is_active' => 'Active',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new AiProviderDefinition;
    }
}
