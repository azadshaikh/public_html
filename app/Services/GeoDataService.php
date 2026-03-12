<?php

namespace App\Services;

use Exception;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoDataService
{
    private readonly string $baseUrl;

    private readonly bool $cacheEnabled;

    private readonly int $cacheTtl;

    private readonly string $cachePrefix;

    private array $endpoints;

    private array $defaults;

    public function __construct()
    {
        $this->baseUrl = config('geodata.api_base_url');
        $this->cacheEnabled = config('geodata.cache_enabled');
        $this->cacheTtl = config('geodata.cache_ttl');
        $this->cachePrefix = config('geodata.cache_prefix');
        $this->endpoints = config('geodata.endpoints');
        $this->defaults = config('geodata.defaults');
    }

    /**
     * Get all countries
     */
    public function getAllCountries(): array
    {
        $data = $this->makeRequest($this->endpoints['countries']);

        return $data['data'] ?? [];
    }

    /**
     * Get country by ISO2 code
     */
    public function getCountryByCode(string $code): ?array
    {
        return $this->makeRequest($this->endpoints['country'], ['code' => strtolower($code)]);
    }

    /**
     * Get states by country code
     */
    public function getStatesByCountryCode(string $countryCode): array
    {
        $data = $this->makeRequest($this->endpoints['states_by_country'], ['code' => strtolower($countryCode)]);

        return $data['data'] ?? [];
    }

    /**
     * Get state by ISO 3166-2 code
     */
    public function getStateByCode(string $code): ?array
    {
        return $this->makeRequest($this->endpoints['state'], ['code' => strtolower($code)]);
    }

    /**
     * Get cities by country code
     */
    public function getCitiesByCountryCode(string $countryCode): array
    {
        $data = $this->makeRequest($this->endpoints['cities_by_country'], ['code' => strtolower($countryCode)]);

        return $data['data'] ?? [];
    }

    /**
     * Get cities by state code
     */
    public function getCitiesByStateCode(string $stateCode): array
    {
        $data = $this->makeRequest($this->endpoints['cities_by_state'], ['code' => strtolower($stateCode)]);

        return $data['data'] ?? [];
    }

    /**
     * Get city by ID using batch processing
     */
    public function getCityById(int $id): ?array
    {
        try {
            // Get batch index
            $batchIndex = $this->getCityBatchIndex();

            // Find the batch containing this city ID
            $batch = null;
            foreach ($batchIndex as $batchInfo) {
                if ($id >= $batchInfo['start_id'] && $id <= $batchInfo['end_id']) {
                    $batch = $batchInfo;
                    break;
                }
            }

            if (! $batch) {
                return null;
            }

            // Get the batch data
            $batchData = $this->getCityBatch($batch['filename']);

            return $batchData[$id] ?? null;
        } catch (Exception $exception) {
            Log::error('Failed to get city by ID: '.$id, [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search locations
     */
    public function searchLocations(string $query): array
    {
        $data = $this->makeRequest($this->endpoints['search']);

        if (! $data || ! isset($data['data'])) {
            return [];
        }

        // Filter results by query
        return array_filter($data['data'], fn (array $item): bool => stripos($item['name'] ?? '', $query) !== false);
    }

    /**
     * Get city batch index
     */
    public function getCityBatchIndex(): array
    {
        $cacheKey = $this->cachePrefix.':city_batches_index';

        $fetch = function (): array {
            $url = $this->buildUrl('/cities/batches.json');
            $data = $this->fetchFromApi($url);

            return $data['data'] ?? [];
        };

        if ($this->cacheEnabled) {
            return Cache::remember($cacheKey, $this->cacheTtl, $fetch);
        }

        return $fetch();
    }

    /**
     * Get city batch data
     */
    public function getCityBatch(string $filename): array
    {
        $cacheKey = $this->cachePrefix.':batch:'.md5($filename);

        $fetch = function () use ($filename): array {
            $url = $this->buildUrl('/'.$filename);
            $data = $this->fetchFromApi($url);

            return $data['data'] ?? [];
        };

        if ($this->cacheEnabled) {
            return Cache::remember($cacheKey, $this->cacheTtl, $fetch);
        }

        return $fetch();
    }

    /**
     * Get multiple cities by IDs using batch processing
     */
    public function getCitiesByIds(array $ids): array
    {
        $results = [];
        $batchGroups = [];

        // Group city IDs by batch
        $batchIndex = $this->getCityBatchIndex();

        foreach ($ids as $id) {
            $batch = null;
            foreach ($batchIndex as $batchInfo) {
                if ($id >= $batchInfo['start_id'] && $id <= $batchInfo['end_id']) {
                    $batch = $batchInfo;
                    break;
                }
            }

            if ($batch) {
                if (! isset($batchGroups[$batch['filename']])) {
                    $batchGroups[$batch['filename']] = [];
                }

                $batchGroups[$batch['filename']][] = $id;
            }
        }

        // Fetch each batch once and extract needed cities
        foreach ($batchGroups as $filename => $cityIds) {
            $batchData = $this->getCityBatch($filename);

            foreach ($cityIds as $id) {
                if (isset($batchData[$id])) {
                    $results[$id] = $batchData[$id];
                }
            }
        }

        return $results;
    }

    /**
     * Clear cache for specific endpoint or all geodata cache
     */
    public function clearCache(?string $endpoint = null, array $params = []): void
    {
        if ($endpoint) {
            $cacheKey = $this->buildCacheKey($endpoint, $params);
            Cache::forget($cacheKey);
        } else {
            // Clear only geodata cache by pattern
            $this->clearAllGeoDataCache();
        }
    }

    /**
     * Warm up cache for commonly used data
     */
    public function warmCache(): void
    {
        Log::info('Starting geodata cache warming...');

        // Warm up countries cache
        $this->getAllCountries();
        Log::info('Warmed up countries cache');

        // Note: We don't warm up all states/cities as that would be too much data
        // Only warm up the most commonly accessed data
    }

    /**
     * Make API request with caching support
     */
    private function makeRequest(string $endpoint, array $params = []): ?array
    {
        $url = $this->buildUrl($endpoint, $params);
        $cacheKey = $this->buildCacheKey($endpoint, $params);

        if ($this->cacheEnabled) {
            return Cache::remember($cacheKey, $this->cacheTtl, fn (): ?array => $this->fetchFromApi($url));
        }

        return $this->fetchFromApi($url);
    }

    /**
     * Fetch data from API
     */
    private function fetchFromApi(string $url): ?array
    {
        try {
            $response = Http::timeout($this->defaults['timeout'])
                ->retry($this->defaults['retry_attempts'], $this->defaults['retry_delay'])
                ->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Geo Data API request failed: '.$url, [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (Exception $exception) {
            Log::error('Geo Data API exception: '.$url, [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build API URL
     */
    private function buildUrl(string $endpoint, array $params = []): string
    {
        $url = $this->baseUrl.$endpoint;

        foreach ($params as $key => $value) {
            $url = str_replace(sprintf('{%s}', $key), $value, $url);
        }

        return $url;
    }

    /**
     * Build cache key with readable parameters for better discoverability
     */
    private function buildCacheKey(string $endpoint, array $params = []): string
    {
        // Parse endpoint to create readable key
        return $this->buildReadableCacheKey($endpoint, $params);
    }

    /**
     * Build a human-readable cache key from endpoint and parameters
     */
    private function buildReadableCacheKey(string $endpoint, array $params = []): string
    {
        // Remove leading slash and file extension
        $cleanEndpoint = trim($endpoint, '/');
        $cleanEndpoint = preg_replace('/\.json$/', '', $cleanEndpoint);

        // Split by slashes to understand the structure
        $parts = explode('/', (string) $cleanEndpoint);

        // Build readable key based on endpoint pattern
        $key = $this->cachePrefix;

        foreach ($parts as $part) {
            if (str_contains($part, '{') && str_contains($part, '}')) {
                // This is a parameter placeholder - replace with actual value
                $paramName = trim($part, '{}');
                if (isset($params[$paramName])) {
                    $key .= ':'.$params[$paramName];
                } else {
                    // Fallback to original format if param not found
                    $key .= ':'.$part;
                }
            } else {
                $key .= ':'.$part;
            }
        }

        // Handle any remaining parameters not in the endpoint path
        $usedParams = [];
        foreach ($parts as $part) {
            if (preg_match('/\{(\w+)\}/', $part, $matches)) {
                $usedParams[] = $matches[1];
            }
        }

        foreach ($params as $paramName => $paramValue) {
            if (! in_array($paramName, $usedParams)) {
                $key .= ':'.$paramName.':'.$paramValue;
            }
        }

        return $key;
    }

    /**
     * Clear all geodata cache entries
     */
    private function clearAllGeoDataCache(): void
    {
        // Get all cache keys that start with our prefix
        $keys = [
            // Standard endpoints
            $this->cachePrefix.':countries',
            $this->cachePrefix.':countries:*',
            $this->cachePrefix.':states:country:*',
            $this->cachePrefix.':states:*',
            $this->cachePrefix.':cities:country:*',
            $this->cachePrefix.':cities:state:*',
            $this->cachePrefix.':cities:*',
            $this->cachePrefix.':search:*',
            // Batch related
            $this->cachePrefix.':city_batches_index',
            $this->cachePrefix.':cities:batches',
            $this->cachePrefix.':cities:batch:*',
            $this->cachePrefix.':batch:*',
        ];

        foreach ($keys as $pattern) {
            if (str_contains($pattern, '*')) {
                // For Redis, use keys pattern matching
                // For other cache drivers, this is a limitation
                $store = Cache::getStore();
                if ($store instanceof RedisStore) {
                    $matches = $store->connection()->keys($pattern);
                    foreach ($matches as $key) {
                        Cache::forget($key);
                    }
                }
            } else {
                Cache::forget($pattern);
            }
        }
    }
}
