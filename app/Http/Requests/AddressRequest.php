<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Definitions\AddressDefinition;
use App\Models\Address;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;

/**
 * AddressRequest - Scaffold-based form request for Address CRUD
 *
 * Uses ScaffoldRequest helpers for consistent validation patterns.
 */
class AddressRequest extends ScaffoldRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'address1' => [$this->requiredOnCreate(), 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'address3' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'country_code' => [$this->requiredOnCreate(), 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:100'],
            'state_code' => ['nullable', 'string', 'max:10'],
            'city' => [$this->requiredOnCreate(), 'string', 'max:100'],
            'city_code' => ['nullable', 'string', 'max:10'],
            'zip' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_primary' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
            'addressable_type' => ['nullable', 'string', 'max:255'],
            'addressable_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     */
    public function attributes(): array
    {
        return [
            'address1' => 'street address',
            'address2' => 'address line 2',
            'address3' => 'address line 3',
            'country_code' => 'country',
            'state_code' => 'state code',
            'city_code' => 'city code',
            'zip' => 'postal code',
            'phone_code' => 'phone code',
            'is_primary' => 'primary address',
            'is_verified' => 'verified status',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'country_code.size' => 'The country must be a valid 2-letter ISO country code.',
            'latitude.between' => 'The latitude must be between -90 and 90.',
            'longitude.between' => 'The longitude must be between -180 and 180.',
        ];
    }

    /**
     * Get the scaffold definition.
     */
    protected function definition(): ScaffoldDefinition
    {
        return new AddressDefinition;
    }

    /**
     * Get the model class for unique/exists rule generation.
     */
    protected function getModelClass(): string
    {
        return Address::class;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->prepareBooleanField('is_primary');
        $this->prepareBooleanField('is_verified');

        if ($this->has('country_code') && $this->country_code) {
            $this->merge(['country_code' => strtoupper((string) $this->country_code)]);
        }

        if ($this->has('state_code') && $this->state_code) {
            $this->merge(['state_code' => strtoupper((string) $this->state_code)]);
        }

        $this->trimField('first_name');
        $this->trimField('last_name');
        $this->trimField('company');
        $this->trimField('address1');
        $this->trimField('address2');
        $this->trimField('address3');
        $this->trimField('city');
        $this->trimField('state');
        $this->trimField('zip');
    }
}
