<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\AIRegistry\Definitions\AiModelDefinition;

class AiModelRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'integer', $this->existsRule('airegistry_providers', 'id')],
            'slug' => [
                'required',
                'string',
                'max:255',
                $this->uniqueRule('slug', 'airegistry_models')->where(fn ($query) => $query->where('provider_id', $this->integer('provider_id'))),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'context_window' => ['nullable', 'integer', 'min:0'],
            'max_output_tokens' => ['nullable', 'integer', 'min:0'],
            'input_cost_per_1m' => ['nullable', 'numeric', 'min:0'],
            'output_cost_per_1m' => ['nullable', 'numeric', 'min:0'],
            'input_modalities' => ['nullable', 'array'],
            'input_modalities.*' => ['string', Rule::in(array_keys(config('airegistry::options.input_modalities')))],
            'output_modalities' => ['nullable', 'array'],
            'output_modalities.*' => ['string', Rule::in(array_keys(config('airegistry::options.output_modalities')))],

            'tokenizer' => ['nullable', 'string', 'max:100'],
            'is_moderated' => ['nullable', 'boolean'],
            'supported_parameters' => ['nullable', 'string', 'max:1000'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', Rule::in(array_keys(config('airegistry::options.capabilities')))],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', Rule::in(array_keys(config('airegistry::options.categories')))],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'provider_id' => 'Provider',
            'slug' => 'Model Slug',
            'name' => 'Model Name',
            'description' => 'Description',
            'context_window' => 'Context Window',
            'max_output_tokens' => 'Max Output Tokens',
            'input_cost_per_1m' => 'Input Cost per 1M',
            'output_cost_per_1m' => 'Output Cost per 1M',
            'input_modalities' => 'Input Modalities',
            'output_modalities' => 'Output Modalities',
            'tokenizer' => 'Tokenizer',
            'is_moderated' => 'Moderated',
            'supported_parameters' => 'Supported Parameters',
            'capabilities' => 'Capabilities',
            'categories' => 'Categories',
            'is_active' => 'Active',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new AiModelDefinition;
    }
}
