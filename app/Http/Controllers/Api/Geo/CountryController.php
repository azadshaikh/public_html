<?php

namespace App\Http\Controllers\Api\Geo;

use App\Http\Controllers\Api\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Geo\CountrySearchRequest;
use App\Services\GeoDataService;
use Exception;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    use ApiResponds;

    public function __construct(
        private readonly GeoDataService $geoService
    ) {}

    /**
     * Get all countries
     */
    public function index(): JsonResponse
    {
        try {
            $countries = $this->geoService->getAllCountries();

            return $this->success($countries, 'countries', [
                'count' => count($countries),
            ]);
        } catch (Exception $exception) {
            return $this->error('Failed to fetch countries', $exception->getMessage(), 500);
        }
    }

    /**
     * Get a specific country by ISO2 code
     */
    public function show(string $code): JsonResponse
    {
        try {
            $country = $this->geoService->getCountryByCode($code);

            if (! $country) {
                return $this->error('Country not found', 'No country found with code: '.$code, 404);
            }

            return $this->success($country, 'country');
        } catch (Exception $exception) {
            return $this->error('Failed to fetch country', $exception->getMessage(), 500);
        }
    }

    /**
     * Get states for a specific country
     */
    public function states(string $code): JsonResponse
    {
        try {
            $states = $this->geoService->getStatesByCountryCode($code);

            return $this->success($states, 'states', [
                'count' => count($states),
                'country_code' => strtoupper($code),
            ]);
        } catch (Exception $exception) {
            return $this->error('Failed to fetch states', $exception->getMessage(), 500);
        }
    }

    /**
     * Get cities for a specific country
     */
    public function cities(string $code): JsonResponse
    {
        try {
            $cities = $this->geoService->getCitiesByCountryCode($code);

            return $this->success($cities, 'cities', [
                'count' => count($cities),
                'country_code' => strtoupper($code),
            ]);
        } catch (Exception $exception) {
            return $this->error('Failed to fetch cities', $exception->getMessage(), 500);
        }
    }

    /**
     * Search countries by name
     */
    public function search(CountrySearchRequest $request): JsonResponse
    {
        try {
            $query = (string) $request->validated('q');
            $limit = $request->validated('limit');
            $offset = (int) ($request->validated('offset') ?? 0);

            $countries = $this->geoService->getAllCountries();

            $matched = [];
            $matchedCount = 0;

            foreach ($countries as $country) {
                if (stripos($country['name'] ?? '', $query) === false) {
                    continue;
                }

                if ($matchedCount++ < $offset) {
                    continue;
                }

                $matched[] = $country;

                if ($limit !== null && count($matched) >= $limit) {
                    break;
                }
            }

            return $this->success($matched, 'countries', [
                'count' => count($matched),
                'query' => $query,
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (Exception $exception) {
            return $this->error('Failed to search countries', $exception->getMessage(), 500);
        }
    }
}
