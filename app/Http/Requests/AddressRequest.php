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
            // Full name (first_name + last_name combined display)
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],

            // Address type
            'type' => ['nullable', 'string', 'max:50'],

            // Address lines
            'address1' => [$this->requiredOnCreate(), 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],

            // Location details
            'city' => [$this->requiredOnCreate(), 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country_code' => [$this->requiredOnCreate(), 'string', 'size:2'],

            // Contact info
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],

            // Flags
            'is_primary' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
            'is_billing' => ['nullable', 'boolean'],
            'is_shipping' => ['nullable', 'boolean'],

            // Polymorphic relation (optional - can be attached later)
            'addressable_type' => ['nullable', 'string', 'max:255'],
            'addressable_id' => ['nullable', 'integer'],

            // Coordinates (optional)
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // Metadata
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom attribute names for validation messages
     */
    public function attributes(): array
    {
        return [
            'address1' => 'street address',
            'address2' => 'address line 2',
            'country_code' => 'country',
            'postcode' => 'postal code',
            'is_primary' => 'primary address',
            'is_verified' => 'verified status',
            'is_billing' => 'billing address',
            'is_shipping' => 'shipping address',
        ];
    }

    /**
     * Get custom validation messages
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
     * Get the scaffold definition
     */
    protected function definition(): ScaffoldDefinition
    {
        return new AddressDefinition;
    }

    /**
     * Get the model class for unique/exists rule generation
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
        // Convert string booleans to actual booleans
        $this->prepareBooleanField('is_primary');
        $this->prepareBooleanField('is_verified');
        $this->prepareBooleanField('is_billing');
        $this->prepareBooleanField('is_shipping');

        // Uppercase country code
        if ($this->has('country_code') && $this->country_code) {
            $this->merge([
                'country_code' => strtoupper($this->country_code),
            ]);
        }

        // Trim whitespace from address fields
        $this->trimField('address1');
        $this->trimField('address2');
        $this->trimField('city');
        $this->trimField('state');
        $this->trimField('postcode');
        $this->trimField('first_name');
        $this->trimField('last_name');
    }
}
