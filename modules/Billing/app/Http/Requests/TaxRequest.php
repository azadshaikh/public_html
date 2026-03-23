<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Definitions\TaxDefinition;
use Modules\Billing\Models\Tax;

class TaxRequest extends ScaffoldRequest
{
    // ================================================================
    // VALIDATION RULES
    // ================================================================

    public function rules(): array
    {
        return [
            // Required fields
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', $this->uniqueRule('code')],
            'type' => ['required', Rule::in([Tax::TYPE_PERCENTAGE, Tax::TYPE_FIXED])],
            'rate' => [
                'required',
                'numeric',
                'min:0',
                Rule::when($this->input('type') === Tax::TYPE_PERCENTAGE, ['max:100']),
            ],

            // Location fields
            'country' => ['nullable', 'string', 'max:2'],
            'state' => ['nullable', 'string', 'max:50'],
            'postal_code' => ['nullable', 'string', 'max:20'],

            // Optional fields
            'description' => ['nullable', 'string', 'max:1000'],
            'applies_to' => ['nullable', 'array'],
            'applies_to.*' => ['string'],
            'excludes' => ['nullable', 'array'],
            'excludes.*' => ['string'],
            'is_compound' => ['boolean'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    // ================================================================
    // FRIENDLY FIELD NAMES
    // ================================================================

    public function attributes(): array
    {
        return [
            'name' => 'Tax Name',
            'code' => 'Tax Code',
            'type' => 'Tax Type',
            'rate' => 'Tax Rate',
            'country' => 'Country',
            'state' => 'State/Province',
            'postal_code' => 'Postal Code',
            'description' => 'Description',
            'applies_to' => 'Applies To',
            'excludes' => 'Excludes',
            'is_compound' => 'Compound Tax',
            'priority' => 'Priority',
            'is_active' => 'Active Status',
            'effective_from' => 'Effective From',
            'effective_to' => 'Effective To',
        ];
    }

    // ================================================================
    // CUSTOM ERROR MESSAGES
    // ================================================================

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter the tax name.',
            'code.required' => 'Please enter a unique tax code.',
            'code.unique' => 'This tax code is already in use.',
            'type.required' => 'Please select the tax type.',
            'rate.required' => 'Please enter the tax rate.',
            'rate.max' => 'Tax rate cannot exceed 100%.',
            'effective_to.after_or_equal' => 'End date must be after or equal to start date.',
        ];
    }

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    protected function definition(): ScaffoldDefinition
    {
        return new TaxDefinition;
    }

    protected function getModelClass(): string
    {
        return Tax::class;
    }
}
