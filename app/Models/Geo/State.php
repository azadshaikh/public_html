<?php

namespace App\Models\Geo;

use App\Services\GeoDataService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class State
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
     * Find state by ISO 3166-2 code
     */
    public static function find(string $code): ?self
    {
        $geoService = App::make(GeoDataService::class);
        $data = $geoService->getStateByCode($code);

        return $data ? new self($data) : null;
    }

    /**
     * Get states by country code
     */
    public static function byCountry(string $countryCode): Collection
    {
        $geoService = App::make(GeoDataService::class);
        $states = $geoService->getStatesByCountryCode($countryCode);

        return collect($states)->map(fn ($state): State => new self($state));
    }

    /**
     * Get country for this state
     */
    public function country(): ?Country
    {
        $countryCode = $this->attributes['country_code'] ?? null;

        return is_string($countryCode) && $countryCode !== '' ? Country::find($countryCode) : null;
    }

    /**
     * Get cities for this state
     */
    public function cities(): Collection
    {
        $iso3166 = $this->attributes['iso3166_2'] ?? null;
        if (! is_string($iso3166) || $iso3166 === '') {
            return collect();
        }

        $cities = $this->geoService->getCitiesByStateCode($iso3166);

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
