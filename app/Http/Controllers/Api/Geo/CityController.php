<?php

namespace App\Http\Controllers\Api\Geo;

use App\Http\Controllers\Api\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Geo\CityBatchRequest;
use App\Http\Requests\Api\Geo\CityIndexRequest;
use App\Http\Requests\Api\Geo\CitySearchRequest;
use App\Services\GeoDataService;
use Exception;
use Illuminate\Http\JsonResponse;

class CityController extends Controller
{
    use ApiResponds;

    public function __construct(
        private readonly GeoDataService $geoService
    ) {}

    /**
     * Get cities by country or state code
     */
    public function index(CityIndexRequest $request): JsonResponse
    {
        try {
            $countryCode = $request->validated('country_code');
            $stateCode = $request->validated('state_code');
            $limit = $request->validated('limit');
            $offset = (int) ($request->validated('offset') ?? 0);

            if ($stateCode) {
                $cities = $this->geoService->getCitiesByStateCode($stateCode);
                $meta = [
                    'type' => 'cities',
                    'state_code' => strtoupper((string) $stateCode),
                ];
            } else {
                $cities = $this->geoService->getCitiesByCountryCode($countryCode);
                $meta = [
                    'type' => 'cities',
                    'country_code' => strtoupper((string) $countryCode),
                ];
            }

            $total = count($cities);
            if ($limit !== null || $offset > 0) {
                $cities = array_slice($cities, $offset, $limit ?? null);
            }

            return $this->success($cities, 'cities', array_merge($meta, [
                'count' => count($cities),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ]));
        } catch (Exception $exception) {
            return $this->error('Failed to fetch cities', $exception->getMessage(), 500);
        }
    }

    /**
     * Get a specific city by ID using batch processing
     */
    public function show(int $id): JsonResponse
    {
        try {
            $city = $this->geoService->getCityById($id);

            if (! $city) {
                return $this->error('City not found', 'No city found with ID: '.$id, 404);
            }

            return $this->success($city, 'city');
        } catch (Exception $exception) {
            return $this->error('Failed to fetch city', $exception->getMessage(), 500);
        }
    }

    /**
     * Get multiple cities by IDs using batch processing
     */
    public function batch(CityBatchRequest $request): JsonResponse
    {
        try {
            $ids = $request->validated('ids');

            $cities = $this->geoService->getCitiesByIds($ids);

            return $this->success($cities, 'cities', [
                'count' => count($cities),
                'requested' => count($ids),
            ]);
        } catch (Exception $exception) {
            return $this->error('Failed to fetch cities', $exception->getMessage(), 500);
        }
    }

    /**
     * Search cities by name
     */
    public function search(CitySearchRequest $request): JsonResponse
    {
        try {
            $query = (string) $request->validated('q');
            $countryCode = $request->validated('country_code');
            $stateCode = $request->validated('state_code');
            $limit = $request->validated('limit');
            $offset = (int) ($request->validated('offset') ?? 0);

            if ($stateCode) {
                $cities = $this->geoService->getCitiesByStateCode($stateCode);
                $meta = [
                    'type' => 'cities',
                    'state_code' => strtoupper((string) $stateCode),
                ];
            } elseif ($countryCode) {
                $cities = $this->geoService->getCitiesByCountryCode($countryCode);
                $meta = [
                    'type' => 'cities',
                    'country_code' => strtoupper((string) $countryCode),
                ];
            } else {
                // This is already validated, but keep a guardrail.
                return $this->error('Location code required', 'Please provide either country_code or state_code parameter for city search', 422);
            }

            $matched = [];
            $matchedCount = 0;

            foreach ($cities as $city) {
                if (stripos($city['name'] ?? '', $query) === false) {
                    continue;
                }

                if ($matchedCount++ < $offset) {
                    continue;
                }

                $matched[] = $city;

                if ($limit !== null && count($matched) >= $limit) {
                    break;
                }
            }

            return $this->success($matched, 'cities', array_merge($meta, [
                'count' => count($matched),
                'query' => $query,
                'limit' => $limit,
                'offset' => $offset,
            ]));
        } catch (Exception $exception) {
            return $this->error('Failed to search cities', $exception->getMessage(), 500);
        }
    }

    /**
     * Get city batch index for efficient lookups
     */
    public function batches(): JsonResponse
    {
        try {
            $batchIndex = $this->geoService->getCityBatchIndex();

            return $this->success($batchIndex, 'batch_index', [
                'count' => count($batchIndex),
            ]);
        } catch (Exception $exception) {
            return $this->error('Failed to fetch batch index', $exception->getMessage(), 500);
        }
    }
}
