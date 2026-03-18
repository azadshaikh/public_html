<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Definitions\AddressDefinition;
use App\Models\Address;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use App\Services\GeoDataService;
use App\Traits\DateTimeFormattingTrait;
use RuntimeException;

class AddressResource extends ScaffoldResource
{
    use DateTimeFormattingTrait;

    /**
     * Get the scaffold definition
     */
    protected function definition(): ScaffoldDefinition
    {
        return new AddressDefinition;
    }

    protected function baseAttributeKeys(): ?array
    {
        return [
            'first_name',
            'last_name',
            'type',
            'address1',
            'city',
            'state',
            'country_code',
            'created_at',
        ];
    }

    /**
     * Get custom/computed fields specific to Address model.
     */
    protected function customFields(): array
    {
        $address = $this->address();

        $data = [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $address->getKey()),
            'edit_url' => route($this->scaffold()->getRoutePrefix().'.edit', $address->getKey()),

            // Computed display values
            'full_name' => $this->getFullName(),
            'full_address' => $this->getFullAddress(),
            'formatted_address' => $this->getFormattedAddress(),
            'country_name' => $this->getCountryName(),
            'addressable_label' => $this->getAddressableLabel(),
            'has_coordinates' => $address->getAttribute('latitude') && $address->getAttribute('longitude'),

            // ⚠️ DataGrid badge template expects {column}_label and {column}_class
            'type_label' => $this->getTypeLabel(),
            'type_class' => $this->getTypeClass(),
            'primary_label' => (bool) $address->getAttribute('is_primary') ? 'Primary' : 'Secondary',
            'primary_class' => (bool) $address->getAttribute('is_primary') ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary',
            'verified_label' => (bool) $address->getAttribute('is_verified') ? 'Verified' : 'Unverified',
            'verified_class' => (bool) $address->getAttribute('is_verified') ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning',

            // Datetime fields
            'created_at' => $address->getAttribute('created_at'),
            'updated_at' => $address->getAttribute('updated_at'),
        ];

        return $this->formatDateTimeFields(
            $data,
            dateFields: ['created_at', 'updated_at']
        );
    }

    /**
     * Get the full name from first_name + last_name
     */
    protected function getFullName(): ?string
    {
        $address = $this->address();

        $parts = array_filter([
            $address->getAttribute('first_name'),
            $address->getAttribute('last_name'),
        ]);

        return $parts !== [] ? implode(' ', $parts) : null;
    }

    /**
     * Get single-line full address
     */
    protected function getFullAddress(): string
    {
        $address = $this->address();

        $parts = array_filter([
            $address->getAttribute('address1'),
            $address->getAttribute('address2'),
            $address->getAttribute('city'),
            $address->getAttribute('state'),
            $address->getAttribute('zip'),
            $this->getCountryName(),
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get multi-line formatted address
     */
    protected function getFormattedAddress(): string
    {
        $address = $this->address();

        $lines = [];

        if ($address->getAttribute('first_name') || $address->getAttribute('last_name')) {
            $lines[] = $this->getFullName();
        }

        if ($address->getAttribute('address1')) {
            $lines[] = $address->getAttribute('address1');
        }

        if ($address->getAttribute('address2')) {
            $lines[] = $address->getAttribute('address2');
        }

        $cityLine = array_filter([
            $address->getAttribute('city'),
            $address->getAttribute('state'),
            $address->getAttribute('zip'),
        ]);
        if ($cityLine !== []) {
            $lines[] = implode(', ', $cityLine);
        }

        if ($address->getAttribute('country_code')) {
            $lines[] = $this->getCountryName();
        }

        return implode("\n", $lines);
    }

    /**
     * Get country name from country code via GeoDataService.
     */
    protected function getCountryName(): ?string
    {
        $address = $this->address();
        $countryCode = $address->getAttribute('country_code');

        if (! $countryCode) {
            return null;
        }

        $country = app(GeoDataService::class)->getCountryByCode($countryCode);

        return $country['name'] ?? $countryCode;
    }

    /**
     * Get label for the addressable relationship
     */
    protected function getAddressableLabel(): ?string
    {
        $address = $this->address();
        $addressableType = $address->getAttribute('addressable_type');
        $addressableId = $address->getAttribute('addressable_id');

        if (! $addressableType || ! $addressableId) {
            return null;
        }

        // Get short class name
        $className = class_basename((string) $addressableType);

        return sprintf('%s #%s', $className, $addressableId);
    }

    /**
     * Get type label for badge
     */
    protected function getTypeLabel(): string
    {
        return ucfirst((string) ($this->address()->getAttribute('type') ?? 'other'));
    }

    /**
     * Get type CSS class for badge
     * ⚠️ CRITICAL: Return FULL Bootstrap CSS classes!
     */
    protected function getTypeClass(): string
    {
        $typeColors = [
            'home' => 'bg-primary-subtle text-primary',
            'work' => 'bg-info-subtle text-info',
            'office' => 'bg-info-subtle text-info',
            'billing' => 'bg-warning-subtle text-warning',
            'shipping' => 'bg-success-subtle text-success',
            'warehouse' => 'bg-secondary-subtle text-secondary',
            'other' => 'bg-secondary-subtle text-secondary',
        ];

        $type = strtolower((string) ($this->address()->getAttribute('type') ?? 'other'));

        return $typeColors[$type] ?? 'bg-secondary-subtle text-secondary';
    }

    private function address(): Address
    {
        throw_unless($this->resource instanceof Address, RuntimeException::class, 'AddressResource expects an Address model instance.');

        return $this->resource;
    }
}
