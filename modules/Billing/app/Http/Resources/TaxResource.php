<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Resources;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Modules\Billing\Definitions\TaxDefinition;
use Modules\Billing\Models\Tax;

/** @mixin Tax */
class TaxResource extends ScaffoldResource
{
    // ================================================================
    // REQUIRED METHOD
    // ================================================================

    protected function definition(): ScaffoldDefinition
    {
        return new TaxDefinition;
    }

    // ================================================================
    // CUSTOM FIELDS FOR DATAGRID
    // ================================================================

    protected function customFields(): array
    {
        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $this->id),

            // Basic fields
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'rate' => (float) $this->rate,
            'formatted_rate' => $this->formatted_rate,

            // Type badge
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'type_badge' => $this->getTypeBadge(),

            // Location
            'country' => $this->country,
            'country_label' => $this->getCountryLabel(),
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'location_display' => $this->getLocationDisplay(),

            // Settings
            'applies_to' => $this->applies_to,
            'excludes' => $this->excludes,
            'is_compound' => $this->is_compound,
            'priority' => $this->priority,

            // Status
            'is_active' => $this->is_active,
            'status' => $this->is_active ? 'active' : 'inactive',
            'status_label' => $this->is_active ? 'Active' : 'Inactive',
            'status_badge' => $this->is_active ? 'success' : 'secondary',

            // Dates
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_to' => $this->effective_to?->format('Y-m-d'),
            'is_effective' => $this->isEffective(),
        ];
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    protected function getTypeLabel(): string
    {
        return match ($this->type) {
            'percentage' => 'Percentage',
            'fixed' => 'Fixed Amount',
            default => 'Unknown',
        };
    }

    protected function getTypeBadge(): string
    {
        return match ($this->type) {
            'percentage' => 'default',
            'fixed' => 'info',
            default => 'secondary',
        };
    }

    protected function getCountryLabel(): string
    {
        $countries = [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'IN' => 'India',
            'DE' => 'Germany',
            'FR' => 'France',
            'JP' => 'Japan',
            'CN' => 'China',
        ];

        return $countries[$this->country] ?? $this->country ?? 'Global';
    }

    protected function getLocationDisplay(): string
    {
        $parts = [];

        if ($this->country) {
            $parts[] = $this->getCountryLabel();
        }

        if ($this->state) {
            $parts[] = $this->state;
        }

        if ($this->postal_code) {
            $parts[] = $this->postal_code;
        }

        return $parts !== [] ? implode(', ', $parts) : 'Global';
    }
}
