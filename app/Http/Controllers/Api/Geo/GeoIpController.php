<?php

namespace App\Http\Controllers\Api\Geo;

use App\Http\Controllers\Controller;
use App\Services\GeoIpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GeoIP Controller for IP-to-location lookups.
 *
 * Public API - no authentication required.
 */
class GeoIpController extends Controller
{
    public function __construct(
        private readonly GeoIpService $geoIpService
    ) {}

    /**
     * Lookup location from request's IP address.
     *
     * GET /api/geo/geoip/lookup
     */
    public function lookup(Request $request): JsonResponse
    {
        $ip = $request->ip();

        // Handle local/null IP
        if (empty($ip) || $ip === '127.0.0.1' || $ip === '::1') {
            return response()->json([
                'data' => [
                    'ip' => $ip,
                    'country' => null,
                    'message' => 'Cannot geolocate localhost IP',
                ],
                'meta' => [
                    'type' => 'geoip_lookup',
                    'database_available' => $this->geoIpService->isDatabaseAvailable(),
                ],
            ]);
        }

        return $this->lookupIp($ip);
    }

    /**
     * Lookup location from specified IP address.
     *
     * GET /api/geo/geoip/lookup/{ip}
     */
    public function lookupIp(string $ip): JsonResponse
    {
        // Validate IP format
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json([
                'error' => 'Invalid IP',
                'message' => 'The provided IP address is invalid.',
            ], 422);
        }

        // Check if database is available
        if (! $this->geoIpService->isDatabaseAvailable()) {
            return response()->json([
                'error' => 'Service Unavailable',
                'message' => 'GeoIP database is not available.',
            ], 503);
        }

        $location = $this->geoIpService->getLocationFromIp($ip);

        if ($location === null) {
            return response()->json([
                'data' => [
                    'ip' => $ip,
                    'country' => null,
                    'message' => 'IP address not found in database',
                ],
                'meta' => [
                    'type' => 'geoip_lookup',
                ],
            ]);
        }

        $dbInfo = $this->geoIpService->getDatabaseInfo();

        return response()->json([
            'data' => array_merge(['ip' => $ip], $location),
            'meta' => [
                'type' => 'geoip_lookup',
                'database_edition' => $dbInfo['database_edition'] ?? null,
            ],
        ]);
    }

    /**
     * Get GeoIP database status.
     *
     * GET /api/geo/geoip/status
     */
    public function status(): JsonResponse
    {
        $info = $this->geoIpService->getDatabaseInfo();

        return response()->json([
            'data' => $info,
            'meta' => [
                'type' => 'geoip_status',
            ],
        ]);
    }
}
