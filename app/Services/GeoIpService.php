<?php

namespace App\Services;

use Exception;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Support\Facades\Log;

/**
 * GeoIP Service for IP-to-location lookups using MaxMind GeoLite2 database.
 *
 * The database is stored server-wide at /usr/local/hestia/data/astero/geoip/
 * and shared across all websites.
 */
class GeoIpService
{
    private ?Reader $reader = null;

    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $locationCache = [];

    private readonly bool $enabled;

    private readonly string $databasePath;

    private readonly string $edition;

    public function __construct()
    {
        $this->enabled = config('geodata.maxmind.enabled', true);
        $this->databasePath = config('geodata.maxmind.database_path', '/usr/local/hestia/data/astero/geoip/GeoLite2-City.mmdb');
        $this->edition = config('geodata.maxmind.edition', 'GeoLite2-City');
    }

    /**
     * Get country info from IP address.
     *
     * @return array{iso_code: string, name: string}|null
     */
    public function getCountryFromIp(string $ip): ?array
    {
        $location = $this->getLocationFromIp($ip);

        if ($location === null) {
            return null;
        }

        return $location['country'];
    }

    /**
     * Get detailed location from IP address.
     *
     * @return array{
     *     country: array{iso_code: string, name: string},
     *     city?: array{name: string|null},
     *     region?: array{iso_code: string|null, name: string|null},
     *     postal?: array{code: string|null},
     *     location?: array{latitude: float|null, longitude: float|null, timezone: string|null}
     * }|null
     */
    public function getLocationFromIp(string $ip): ?array
    {
        if (array_key_exists($ip, $this->locationCache)) {
            return $this->locationCache[$ip];
        }

        if (! $this->enabled || ! $this->isDatabaseAvailable()) {
            $this->locationCache[$ip] = null;

            return null;
        }

        try {
            $reader = $this->getReader();
            if (! $reader instanceof Reader) {
                $this->locationCache[$ip] = null;

                return null;
            }

            // Use city() for GeoLite2-City, country() for GeoLite2-Country
            if (str_contains($this->edition, 'City')) {
                $record = $reader->city($ip);

                $location = [
                    'country' => [
                        'iso_code' => $record->country->isoCode,
                        'name' => $record->country->name,
                    ],
                    'city' => [
                        'name' => $record->city->name,
                    ],
                    'region' => [
                        'iso_code' => $record->mostSpecificSubdivision->isoCode,
                        'name' => $record->mostSpecificSubdivision->name,
                    ],
                    'postal' => [
                        'code' => $record->postal->code,
                    ],
                    'location' => [
                        'latitude' => $record->location->latitude,
                        'longitude' => $record->location->longitude,
                        'timezone' => $record->location->timeZone,
                    ],
                ];

                $this->locationCache[$ip] = $location;

                return $location;
            }

            // Country-only lookup
            $record = $reader->country($ip);

            $location = [
                'country' => [
                    'iso_code' => $record->country->isoCode,
                    'name' => $record->country->name,
                ],
            ];

            $this->locationCache[$ip] = $location;

            return $location;
        } catch (AddressNotFoundException) {
            // IP not found in database - this is normal for private/reserved IPs
            Log::debug('GeoIP: Address not found for IP: '.$ip);

            $this->locationCache[$ip] = null;

            return null;
        } catch (Exception $e) {
            Log::warning(sprintf('GeoIP lookup failed for IP %s: ', $ip).$e->getMessage());

            $this->locationCache[$ip] = null;

            return null;
        }
    }

    /**
     * Check if the database file exists and is readable.
     */
    public function isDatabaseAvailable(): bool
    {
        return file_exists($this->databasePath) && is_readable($this->databasePath);
    }

    /**
     * Get database metadata.
     *
     * @return array{
     *     available: bool,
     *     database_edition?: string,
     *     build_date?: string,
     *     ip_version?: int,
     *     node_count?: int,
     *     file_size?: int
     * }
     */
    public function getDatabaseInfo(): array
    {
        if (! $this->isDatabaseAvailable()) {
            return ['available' => false];
        }

        try {
            $reader = $this->getReader();
            if (! $reader instanceof Reader) {
                return ['available' => false];
            }

            $metadata = $reader->metadata();

            return [
                'available' => true,
                'database_edition' => $metadata->databaseType,
                'build_date' => date('c', $metadata->buildEpoch),
                'ip_version' => $metadata->ipVersion,
                'node_count' => $metadata->nodeCount,
                'file_size' => filesize($this->databasePath),
            ];
        } catch (Exception $exception) {
            Log::warning('GeoIP: Failed to read database metadata: '.$exception->getMessage());

            return ['available' => false];
        }
    }

    /**
     * Get the database path.
     */
    public function getDatabasePath(): string
    {
        return $this->databasePath;
    }

    /**
     * Get or create the MaxMind Reader instance (lazy loading).
     */
    private function getReader(): ?Reader
    {
        if ($this->reader instanceof Reader) {
            return $this->reader;
        }

        try {
            $this->reader = new Reader($this->databasePath);

            return $this->reader;
        } catch (Exception $exception) {
            Log::error('GeoIP: Failed to open database: '.$exception->getMessage());

            return null;
        }
    }
}
