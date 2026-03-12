<?php

namespace App\Models\Geo;

use App\Services\GeoDataService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class Country
{
    private readonly GeoDataService $geoService;

    public function __construct(private array $attributes = [])
    {
        $this->geoService = App::make(GeoDataService::class);
    }

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
     * Find country by ISO2 code
     */
    public static function find(string $code): ?self
    {
        $geoService = App::make(GeoDataService::class);
        $data = $geoService->getCountryByCode($code);

        return $data ? new self($data) : null;
    }

    /**
     * Get all countries
     */
    public static function all(): Collection
    {
        $geoService = App::make(GeoDataService::class);
        $countries = $geoService->getAllCountries();

        return collect($countries)->map(fn ($country): Country => new self($country));
    }

    /**
     * Get states for this country
     */
    public function states(): Collection
    {
        $iso2 = $this->attributes['iso2'] ?? null;
        if (! is_string($iso2) || $iso2 === '') {
            return collect();
        }

        $states = $this->geoService->getStatesByCountryCode($iso2);

        return collect($states)->map(fn ($state): State => new State($state));
    }

    /**
     * Get cities for this country
     */
    public function cities(): Collection
    {
        $iso2 = $this->attributes['iso2'] ?? null;
        if (! is_string($iso2) || $iso2 === '') {
            return collect();
        }

        $cities = $this->geoService->getCitiesByCountryCode($iso2);

        return collect($cities)->map(fn ($city): City => new City($city));
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
