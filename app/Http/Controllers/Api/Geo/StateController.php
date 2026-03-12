<?php

namespace App\Http\Controllers\Api\Geo;

use App\Http\Controllers\Api\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Geo\StateIndexRequest;
use App\Http\Requests\Api\Geo\StateSearchRequest;
use App\Services\GeoDataService;
use Exception;
use Illuminate\Http\JsonResponse;

class StateController extends Controller
{
    use ApiResponds;

    public function __construct(
        private readonly GeoDataService $geoService
    ) {}

    /**
     * Get states by country code
     */
    public function index(StateIndexRequest $request): JsonResponse
    {
        try {
            $countryCode = (string) $request->validated('country_code');
            $limit = $request->validated('limit');
            $offset = (int) ($request->validated('offset') ?? 0);

            $states = $this->geoService->getStatesByCountryCode($countryCode);

            $total = count($states);
            if ($limit !== null || $offset > 0) {
                $states = array_slice($states, $offset, $limit ?? null);
            }

            return $this->success($states, 'states', [
                'count' => count($states),
                'total' => $total,
                'country_code' => strtoupper($countryCode),
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (Exception $exception) {
            return $this->error('Failed to fetch states', $exception->getMessage(), 500);
        }
    }

    /**
     * Get a specific state by ISO 3166-2 code
     */
    public function show(string $code): JsonResponse
    {
        try {
            $state = $this->geoService->getStateByCode($code);

            if (! $state) {
                return $this->error('State not found', 'No state found with code: '.$code, 404);
            }

            return $this->success($state, 'state');
        } catch (Exception $exception) {
            return $this->error('Failed to fetch state', $exception->getMessage(), 500);
        }
    }

    /**
     * Get cities for a specific state
     */
    public function cities(string $code): JsonResponse
    {
        try {
            $cities = $this->geoService->getCitiesByStateCode($code);

            return $this->success($cities, 'cities', [
                'count' => count($cities),
                'state_code' => strtoupper($code),
            ]);
        } catch (Exception $exception) {
            return $this->error('Failed to fetch cities', $exception->getMessage(), 500);
        }
    }

    /**
     * Search states by name
     */
    public function search(StateSearchRequest $request): JsonResponse
    {
        try {
            $query = (string) $request->validated('q');
            $countryCode = (string) $request->validated('country_code');
            $limit = $request->validated('limit');
            $offset = (int) ($request->validated('offset') ?? 0);

            $states = $this->geoService->getStatesByCountryCode($countryCode);

            $matched = [];
            $matchedCount = 0;

            foreach ($states as $state) {
                if (stripos($state['name'] ?? '', $query) === false) {
                    continue;
                }

                if ($matchedCount++ < $offset) {
                    continue;
                }

                $matched[] = $state;

                if ($limit !== null && count($matched) >= $limit) {
                    break;
                }
            }

            return $this->success($matched, 'states', [
                'count' => count($matched),
                'query' => $query,
                'country_code' => strtoupper($countryCode),
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (Exception $exception) {
            return $this->error('Failed to search states', $exception->getMessage(), 500);
        }
    }
}
