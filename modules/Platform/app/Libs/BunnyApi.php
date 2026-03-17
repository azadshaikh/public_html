<?php

namespace Modules\Platform\Libs;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\Provider;

/**
 * Exception thrown for Bunny.net API errors.
 */
class BunnyApiException extends Exception {}

/**
 * Class BunnyApi
 *
 * API client for interacting with Bunny.net CDN. Handles authentication, API calls, and response mapping.
 *
 * Usage: Use static methods to perform actions on Bunny.net accounts. Handles logging and error mapping.
 */
class BunnyApi
{
    public const TYPE_BASE = 'BASE';

    public const TYPE_STORAGE = 'STORAGE';

    public const TYPE_VIDEO = 'VIDEO';

    private const string API_URL = 'https://api.bunny.net/';

    private const string STORAGE_API_URL = 'https://storage.bunnycdn.com/';

    private const string VIDEO_API_URL = 'https://video.bunnycdn.com/';

    /**
     * Execute a request to the Bunny.net API.
     *
     * @param  string  $method  HTTP method (GET, POST, PUT, DELETE)
     * @param  string  $endpoint  API endpoint path
     * @param  Provider|array  $account  Account model or credentials array
     * @param  array  $data  Request body data for POST/PUT
     * @param  array  $query  Query parameters
     * @param  string  $type  API type (BASE, STORAGE, VIDEO)
     * @param  int  $timeout  Timeout in seconds
     * @return array API response
     *
     * @throws BunnyApiException on authentication or API errors
     */
    public static function execute(
        string $method,
        string $endpoint,
        Provider|array $account,
        array $data = [],
        array $query = [],
        string $type = self::TYPE_BASE,
        int $timeout = 30
    ): array {
        $credentials = self::resolveCredentials($account);

        $apiKey = $credentials['api_key'] ?? null;
        throw_if(empty($apiKey), BunnyApiException::class, 'API key is required for Bunny.net API calls.');

        $baseUrl = self::getBaseUrl($type, $credentials['storage_region'] ?? 'DE');
        $url = rtrim($baseUrl, '/').'/'.ltrim($endpoint, '/');

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        return self::sendRequest($method, $url, $apiKey, $data, $timeout, $type);
    }

    // =============================================================================
    // PULL ZONE (CDN) METHODS
    // =============================================================================

    /**
     * List all pull zones.
     */
    public static function listPullZones(Provider|array $account, int $page = 0, int $perPage = 100): array
    {
        return self::execute('GET', 'pullzone', $account, [], [
            'page' => $page,
            'perPage' => $perPage,
            'includeCertificate' => 'true',
        ]);
    }

    /**
     * Get a specific pull zone.
     */
    public static function getPullZone(Provider|array $account, int $pullZoneId): array
    {
        return self::execute('GET', 'pullzone/'.$pullZoneId, $account);
    }

    /**
     * Create a new pull zone.
     */
    public static function createPullZone(
        Provider|array $account,
        string $name,
        string $originUrl,
        array $options = []
    ): array {
        $data = array_merge([
            'Name' => $name,
            'OriginUrl' => $originUrl,
        ], $options);

        return self::execute('POST', 'pullzone', $account, $data);
    }

    /**
     * Update a pull zone.
     */
    public static function updatePullZone(Provider|array $account, int $pullZoneId, array $data): array
    {
        return self::execute('POST', 'pullzone/'.$pullZoneId, $account, $data);
    }

    /**
     * Delete a pull zone.
     */
    public static function deletePullZone(Provider|array $account, int $pullZoneId): array
    {
        return self::execute('DELETE', 'pullzone/'.$pullZoneId, $account);
    }

    /**
     * Purge cache for a pull zone.
     */
    public static function purgePullZoneCache(Provider|array $account, int $pullZoneId): array
    {
        return self::execute('POST', sprintf('pullzone/%d/purgeCache', $pullZoneId), $account);
    }

    /**
     * Add hostname to pull zone.
     */
    public static function addPullZoneHostname(Provider|array $account, int $pullZoneId, string $hostname): array
    {
        return self::execute('POST', 'pullzone/addHostname', $account, [
            'PullZoneId' => $pullZoneId,
            'Hostname' => $hostname,
        ]);
    }

    /**
     * Remove hostname from pull zone.
     */
    public static function removePullZoneHostname(Provider|array $account, int $pullZoneId, string $hostname): array
    {
        return self::execute('DELETE', 'pullzone/deleteHostname', $account, [
            'PullZoneId' => $pullZoneId,
            'Hostname' => $hostname,
        ]);
    }

    /**
     * Add free SSL certificate for hostname.
     */
    public static function addFreeCertificate(Provider|array $account, string $hostname): array
    {
        return self::execute('GET', 'pullzone/loadFreeCertificate', $account, [], [
            'hostname' => $hostname,
        ]);
    }

    /**
     * Set force SSL for hostname.
     */
    public static function setForceSSL(Provider|array $account, int $pullZoneId, string $hostname, bool $forceSSL = true): array
    {
        return self::execute('POST', 'pullzone/setForceSSL', $account, [
            'PullZoneId' => $pullZoneId,
            'Hostname' => $hostname,
            'ForceSSL' => $forceSSL,
        ]);
    }

    // =============================================================================
    // DNS ZONE METHODS
    // =============================================================================

    /**
     * List all DNS zones, optionally filtered by search term.
     */
    public static function listDnsZones(Provider|array $account, ?string $search = null, int $page = 1, int $perPage = 1000): array
    {
        $query = ['page' => $page, 'perPage' => $perPage];
        if ($search !== null) {
            $query['search'] = $search;
        }

        return self::execute('GET', 'dnszone', $account, [], $query);
    }

    /**
     * Find an existing DNS zone by domain name.
     *
     * @return array|null The zone data array or null if not found
     */
    public static function findDnsZoneByDomain(Provider|array $account, string $domain): ?array
    {
        $result = self::listDnsZones($account, $domain);

        if (($result['status'] ?? '') !== 'success') {
            return null;
        }

        $zones = $result['data']['Items'] ?? $result['data'] ?? [];

        if (! is_array($zones)) {
            return null;
        }

        foreach ($zones as $zone) {
            if (isset($zone['Domain']) && strtolower($zone['Domain']) === strtolower($domain)) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Get a specific DNS zone.
     */
    public static function getDnsZone(Provider|array $account, int $zoneId): array
    {
        return self::execute('GET', 'dnszone/'.$zoneId, $account);
    }

    /**
     * Create a new DNS zone.
     */
    public static function createDnsZone(Provider|array $account, string $domain): array
    {
        return self::execute('POST', 'dnszone', $account, [
            'Domain' => $domain,
        ]);
    }

    /**
     * Delete a DNS zone.
     */
    public static function deleteDnsZone(Provider|array $account, int $zoneId): array
    {
        return self::execute('DELETE', 'dnszone/'.$zoneId, $account);
    }

    /**
     * Add DNS record to a zone.
     */
    public static function addDnsRecord(
        Provider|array $account,
        int $zoneId,
        string $type,
        string $name,
        string $value,
        int $ttl = 300,
        array $options = []
    ): array {
        $data = array_merge([
            'Type' => self::getDnsRecordTypeCode($type),
            'Name' => $name,
            'Value' => $value,
            'Ttl' => $ttl,
        ], $options);

        return self::execute('PUT', sprintf('dnszone/%d/records', $zoneId), $account, $data);
    }

    /**
     * Update a DNS record.
     */
    public static function updateDnsRecord(
        Provider|array $account,
        int $zoneId,
        int $recordId,
        array $data
    ): array {
        return self::execute('POST', sprintf('dnszone/%d/records/%d', $zoneId, $recordId), $account, $data);
    }

    /**
     * Delete a DNS record.
     */
    public static function deleteDnsRecord(Provider|array $account, int $zoneId, int $recordId): array
    {
        return self::execute('DELETE', sprintf('dnszone/%d/records/%d', $zoneId, $recordId), $account);
    }

    // =============================================================================
    // ACCOUNT/BILLING METHODS
    // =============================================================================

    /**
     * Get account billing information.
     */
    public static function getBilling(Provider|array $account): array
    {
        return self::execute('GET', 'billing', $account);
    }

    /**
     * Get account balance.
     */
    public static function getBalance(Provider|array $account): float
    {
        $result = self::getBilling($account);

        return $result['data']['Balance'] ?? 0.0;
    }

    /**
     * Get current month charges.
     */
    public static function getMonthCharges(Provider|array $account): float
    {
        $result = self::getBilling($account);

        return $result['data']['ThisMonthCharges'] ?? 0.0;
    }

    // =============================================================================
    // STORAGE ZONE METHODS
    // =============================================================================

    /**
     * List all storage zones.
     */
    public static function listStorageZones(Provider|array $account, int $page = 0, int $perPage = 1000): array
    {
        return self::execute('GET', 'storagezone', $account, [], [
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    /**
     * Get a specific storage zone.
     */
    public static function getStorageZone(Provider|array $account, int $storageZoneId): array
    {
        return self::execute('GET', 'storagezone/'.$storageZoneId, $account);
    }

    /**
     * Create a new storage zone.
     */
    public static function createStorageZone(Provider|array $account, string $name, string $region = 'DE'): array
    {
        return self::execute('POST', 'storagezone', $account, [
            'Name' => $name,
            'Region' => $region,
        ]);
    }

    /**
     * Delete a storage zone.
     */
    public static function deleteStorageZone(Provider|array $account, int $storageZoneId): array
    {
        return self::execute('DELETE', 'storagezone/'.$storageZoneId, $account);
    }

    // =============================================================================
    // CACHE PURGE METHODS
    // =============================================================================

    /**
     * Purge URL from cache.
     */
    public static function purgeUrl(Provider|array $account, string $url, bool $async = false): array
    {
        return self::execute('POST', 'purge', $account, [], [
            'url' => $url,
            'async' => $async ? 'true' : 'false',
        ]);
    }

    // =============================================================================
    // STATISTICS METHODS
    // =============================================================================

    /**
     * Get traffic statistics.
     */
    public static function getStatistics(
        Provider|array $account,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $pullZoneId = null,
        bool $hourly = false
    ): array {
        $query = [];

        if ($dateFrom) {
            $query['dateFrom'] = $dateFrom;
        }

        if ($dateTo) {
            $query['dateTo'] = $dateTo;
        }

        if ($pullZoneId) {
            $query['pullZone'] = $pullZoneId;
        }

        if ($hourly) {
            $query['hourly'] = 'true';
        }

        return self::execute('GET', 'statistics', $account, [], $query);
    }

    // =============================================================================
    // ACCOUNT INFO METHODS
    // =============================================================================

    /**
     * Get account information.
     */
    public static function getAccountInfo(Provider|array $account): array
    {
        return self::execute('GET', 'user', $account);
    }

    /**
     * Sync account info and update the Provider model.
     */
    public static function syncAccountInfo(Provider $provider): array
    {
        try {
            // Get billing info
            $billingResult = self::getBilling($provider);
            $billing = $billingResult['data'] ?? [];

            // Get pull zones count
            $pullZonesResult = self::listPullZones($provider, 0, 1);
            $pullZoneCount = count($pullZonesResult['data'] ?? []);

            // Get storage zones count
            $storageZonesResult = self::listStorageZones($provider, 0, 1);
            $storageZoneCount = count($storageZonesResult['data'] ?? []);

            // Get DNS zones count
            $dnsZonesResult = self::listDnsZones($provider, null, 1, 1);
            $dnsZoneCount = $dnsZonesResult['data']['TotalItems'] ?? 0;

            // Update the provider metadata
            $provider->setMetadata('balance', $billing['Balance'] ?? 0);
            $provider->setMetadata('pull_zone_count', $pullZoneCount);
            $provider->setMetadata('storage_zone_count', $storageZoneCount);
            $provider->setMetadata('dns_zone_count', $dnsZoneCount);
            $provider->setMetadata('monthly_charges', $billing['ThisMonthCharges'] ?? 0);
            $provider->setMetadata('last_synced_at', now()->toISOString());
            $provider->save();

            return [
                'status' => 'success',
                'message' => 'Account info synced successfully',
                'data' => [
                    'balance' => $billing['Balance'] ?? 0,
                    'pull_zone_count' => $pullZoneCount,
                    'storage_zone_count' => $storageZoneCount,
                    'dns_zone_count' => $dnsZoneCount,
                    'monthly_charges' => $billing['ThisMonthCharges'] ?? 0,
                ],
            ];
        } catch (Exception $exception) {
            return [
                'status' => 'error',
                'message' => 'Failed to sync account info: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Resolve credentials from a Provider or array.
     */
    private static function resolveCredentials(Provider|array $account): array
    {
        if ($account instanceof Provider) {
            $creds = $account->credentials;

            return [
                'api_key' => $creds['api_key'] ?? null,
                'storage_region' => $account->getMetadata('storage_region') ?? 'DE',
                'stream_library_id' => $account->getMetadata('stream_library_id'),
            ];
        }

        return [
            'api_key' => $account['api_key'] ?? null,
            'storage_region' => $account['storage_region'] ?? 'DE',
            'stream_library_id' => $account['stream_library_id'] ?? null,
        ];
    }

    /**
     * Get the base URL for the API type.
     */
    private static function getBaseUrl(string $type, string $storageRegion = 'DE'): string
    {
        return match ($type) {
            self::TYPE_STORAGE => self::getStorageUrl($storageRegion),
            self::TYPE_VIDEO => self::VIDEO_API_URL,
            default => self::API_URL,
        };
    }

    /**
     * Get storage URL based on region.
     */
    private static function getStorageUrl(string $region): string
    {
        // Bunny.net storage regions
        $regionUrls = [
            'DE' => 'https://storage.bunnycdn.com/',
            'NY' => 'https://ny.storage.bunnycdn.com/',
            'LA' => 'https://la.storage.bunnycdn.com/',
            'SG' => 'https://sg.storage.bunnycdn.com/',
            'SYD' => 'https://syd.storage.bunnycdn.com/',
            'UK' => 'https://uk.storage.bunnycdn.com/',
            'SE' => 'https://se.storage.bunnycdn.com/',
            'BR' => 'https://br.storage.bunnycdn.com/',
            'JH' => 'https://jh.storage.bunnycdn.com/',
        ];

        return $regionUrls[strtoupper($region)] ?? self::STORAGE_API_URL;
    }

    /**
     * Send the HTTP request to Bunny.net API.
     */
    private static function sendRequest(
        string $method,
        string $url,
        string $apiKey,
        array $data,
        int $timeout,
        string $type
    ): array {
        $correlationId = uniqid('bunny_', true);

        $logContext = [
            'url' => $url,
            'method' => $method,
            'type' => $type,
            'correlation_id' => $correlationId,
            'timestamp' => now()->toISOString(),
        ];

        try {
            $request = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'AccessKey' => $apiKey,
            ])
                ->timeout($timeout)
                ->connectTimeout(10);

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'DELETE' => $request->delete($url, $data),
                'PATCH' => $request->patch($url, $data),
                default => throw new BunnyApiException('Unsupported HTTP method: '.$method),
            };

            $statusCode = $response->status();
            $body = $response->body();

            // Try to decode JSON response
            $decodedBody = $response->json();

            $logContext['status_code'] = $statusCode;
            $logContext['response_length'] = strlen($body);

            // Log API calls in local environment only
            if (app()->isLocal()) {
                $logContext['response'] = $decodedBody ?? $body;
                Log::debug('Bunny.net API Call', $logContext);
            }

            // Success responses (2xx)
            if ($response->successful()) {
                return [
                    'status' => 'success',
                    'http_code' => $statusCode,
                    'data' => $decodedBody ?? $body,
                    'message' => self::getStatusMessage($statusCode),
                ];
            }

            // Handle error responses
            $errorMessage = self::extractErrorMessage($decodedBody, $statusCode);
            $logContext['error'] = $errorMessage;
            $logContext['response_body'] = $body;

            // Always log errors regardless of environment
            Log::error('Bunny.net API Error', $logContext);

            throw new BunnyApiException($errorMessage, $statusCode);
        } catch (BunnyApiException $e) {
            throw $e;
        } catch (Exception $e) {
            $logContext['exception'] = $e->getMessage();
            $logContext['exception_type'] = $e::class;

            // Always log exceptions regardless of environment
            Log::error('Bunny.net API Exception', $logContext);

            throw new BunnyApiException('Bunny.net API request failed: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Extract error message from API response.
     */
    private static function extractErrorMessage(?array $response, int $statusCode): string
    {
        if ($response) {
            if (isset($response['Message'])) {
                return $response['Message'];
            }

            if (isset($response['ErrorKey'])) {
                return $response['ErrorKey'];
            }

            if (isset($response['message'])) {
                return $response['message'];
            }
        }

        return self::getStatusMessage($statusCode);
    }

    /**
     * Get human-readable message for HTTP status code.
     */
    private static function getStatusMessage(int $statusCode): string
    {
        return match ($statusCode) {
            200 => 'Request successful',
            201 => 'Resource created successfully',
            204 => 'Request successful (no content)',
            400 => 'Bad request - invalid parameters',
            401 => 'Unauthorized - invalid API key',
            403 => 'Forbidden - access denied',
            404 => 'Resource not found',
            409 => 'Conflict - resource already exists',
            429 => 'Too many requests - rate limited',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
            default => 'HTTP error: '.$statusCode,
        };
    }

    /**
     * Get DNS record type code for the Bunny API.
     *
     * The Bunny API expects Type as an integer.
     */
    private static function getDnsRecordTypeCode(string $type): int
    {
        return match (strtoupper($type)) {
            'A' => 0,
            'AAAA' => 1,
            'CNAME' => 2,
            'TXT' => 3,
            'MX' => 4,
            'REDIRECT' => 5,
            'FLATTEN' => 6,
            'PULLZONE', 'PULL' => 7,
            'SRV' => 8,
            'CAA' => 9,
            'PTR' => 10,
            'SCRIPT' => 11,
            'NS' => 12,
            default => 0,
        };
    }
}
