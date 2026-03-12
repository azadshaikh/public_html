<?php

namespace App\Traits;

use App\Models\Address;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @method MorphMany addresses()
 */
trait AddressableTrait
{
    /**
     * Save or update address for the model.
     * Supports saving primary address and multiple address types.
     *
     * @param  array  $data  Address data with optional 'type' field
     * @param  string  $type  Address type (primary, contact, support, technical, billing, shipping, etc.)
     * @param  bool  $isPrimary  Whether this is the primary address
     */
    public function saveAddress(array $data, string $type = 'primary', bool $isPrimary = true): void
    {
        $addressData = $this->extractAddressData($data);

        if (empty($addressData) && empty($data['phone']) && empty($data['phone_code'])) {
            return;
        }

        $addressAttributes = [
            'first_name' => $data['first_name'] ?? $this->getAddressFirstName(),
            'last_name' => $data['last_name'] ?? $this->getAddressLastName(),
            'company' => $data['company'] ?? $this->getAddressCompany(),
            'address1' => $addressData['address1'] ?? null,
            'address2' => $addressData['address2'] ?? null,
            'address3' => $addressData['address3'] ?? null,
            'country' => $addressData['country'] ?? null,
            'country_code' => $addressData['country_code'] ?? null,
            'state' => $addressData['state'] ?? null,
            'state_code' => $addressData['state_code'] ?? null,
            'city' => $addressData['city'] ?? null,
            'city_code' => $addressData['city_code'] ?? null,
            'zip' => $addressData['zip'] ?? null,
            'phone' => $data['phone'] ?? null,
            'phone_code' => $data['phone_code'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'type' => $type,
            'is_primary' => $isPrimary,
            'is_verified' => $data['is_verified'] ?? false,
        ];

        // If saving as primary, unset other primary addresses
        if ($isPrimary) {
            $this->addresses()->where('is_primary', true)->update(['is_primary' => false]);
        }

        // Update or create address by type
        $this->addresses()->updateOrCreate(
            ['type' => $type],
            $addressAttributes
        );
    }

    /**
     * Save multiple addresses at once.
     *
     * @param  array  $addresses  Array of addresses, each with 'type' key
     *
     * Example:
     * $model->saveAddresses([
     *     ['type' => 'primary', 'address1' => '...', 'phone' => '...'],
     *     ['type' => 'contact', 'phone' => '...', 'phone_code' => '...'],
     *     ['type' => 'support', 'phone' => '...'],
     * ]);
     */
    public function saveAddresses(array $addresses): void
    {
        foreach ($addresses as $addressData) {
            $type = $addressData['type'] ?? 'primary';
            $isPrimary = $addressData['is_primary'] ?? ($type === 'primary');

            $this->saveAddress($addressData, $type, $isPrimary);
        }
    }

    /**
     * Get address by type.
     */
    public function getAddressByType(string $type): ?Address
    {
        $address = $this->addresses()->where('type', $type)->first();

        return $address instanceof Address ? $address : null;
    }

    /**
     * Extract address data from input array.
     */
    protected function extractAddressData(array $data): array
    {
        return array_filter(
            array_intersect_key($data, array_flip([
                'address1', 'address2', 'address3', 'country', 'country_code',
                'state', 'state_code', 'city', 'city_code', 'zip',
            ]))
        );
    }

    /**
     * Get company name for address (override if needed).
     */
    protected function getAddressCompany(): ?string
    {
        return $this->company ?? $this->name ?? null;
    }

    /**
     * Get first name for address (override if needed).
     */
    protected function getAddressFirstName(): ?string
    {
        return $this->first_name ?? $this->name ?? null;
    }

    /**
     * Get last name for address (override if needed).
     */
    protected function getAddressLastName(): ?string
    {
        return $this->last_name ?? null;
    }
}
