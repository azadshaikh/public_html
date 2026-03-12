<?php

namespace App\Models\Geo;

use App\Services\GeoDataService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class City
{
    public function __construct(private array $attributes = []) {}

    /**
     * Magic getter for attributes
     */
    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Magic isset
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Find city by ID
     */
    public static function find(int $id): ?self
    {
        $geoService = App::make(GeoDataService::class);
        $data = $geoService->getCityById($id);

        return $data ? new self($data) : null;
    }

    /**
     * Get all cities (for dropdowns)
     */
    public static function all(): array
    {
        App::make(GeoDataService::class);

        // This would need to be implemented in GeoDataService if needed
        // For now, return empty array as cities are typically fetched by country/state
        return [];
    }

    /**
     * Get cities by country code
     */
    public static function byCountry(string $countryCode): Collection
    {
        $geoService = App::make(GeoDataService::class);
        $cities = $geoService->getCitiesByCountryCode($countryCode);

        return collect($cities)->map(fn ($city): City => new self($city));
    }

    /**
     * Get cities by state code
     */
    public static function byState(string $stateCode): Collection
    {
        $geoService = App::make(GeoDataService::class);
        $cities = $geoService->getCitiesByStateCode($stateCode);

        return collect($cities)->map(fn ($city): City => new self($city));
    }

    /**
     * Get country for this city
     */
    public function country(): ?Country
    {
        $countryCode = $this->attributes['country_code'] ?? null;

        return is_string($countryCode) && $countryCode !== '' ? Country::find($countryCode) : null;
    }

    /**
     * Get state for this city
     */
    public function state(): ?State
    {
        $stateCode = $this->attributes['state_code'] ?? null;

        return is_string($stateCode) && $stateCode !== '' ? State::find($stateCode) : null;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->attributes);
    }
}
