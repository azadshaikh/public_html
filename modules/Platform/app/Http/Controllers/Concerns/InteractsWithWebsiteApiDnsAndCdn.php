<?php

namespace Modules\Platform\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;

trait InteractsWithWebsiteApiDnsAndCdn
{
    /**
     * List DNS records for a website's domain zone.
     *
     * GET /api/platform/v1/websites/{siteId}/dns-records
     */
    public function dnsRecords(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        $domainRecord = $website->domainRecord;

        if ($domainRecord && ($website->dns_mode === 'external' || $domainRecord->dns_mode === 'external')) {
            $dnsInstructions = $domainRecord->getMetadata('dns_instructions');

            return response()->json([
                'data' => [
                    'domain' => $domainRecord->name,
                    'dns_mode' => 'external',
                    'dns_status' => $domainRecord->dns_status ?? 'unknown',
                    'records' => $dnsInstructions['records'] ?? [],
                    'nameservers' => [],
                ],
            ]);
        }

        if (! $domainRecord || ! $domainRecord->dns_zone_id) {
            return response()->json([
                'message' => 'No DNS zone configured for this website.',
                'data' => [
                    'records' => [],
                    'nameservers' => [],
                    'dns_mode' => $website->dns_mode ?? 'subdomain',
                ],
            ]);
        }

        $dnsProvider = $website->getProvider(Provider::TYPE_DNS);
        if (! $dnsProvider) {
            return response()->json(['message' => 'No DNS provider configured.'], 400);
        }

        try {
            $zoneData = BunnyApi::getDnsZone($dnsProvider, (int) $domainRecord->dns_zone_id);

            $records = [];
            $rawRecords = $zoneData['data']['Records'] ?? [];
            foreach ($rawRecords as $record) {
                $typeName = $this->dnsRecordTypeName((int) ($record['Type'] ?? 0));
                $name = $record['Name'] ?? '';
                $value = $record['Value'] ?? '';

                $records[] = [
                    'id' => $record['Id'] ?? null,
                    'type' => $typeName,
                    'type_code' => (int) ($record['Type'] ?? 0),
                    'name' => $name,
                    'value' => $value,
                    'ttl' => (int) ($record['Ttl'] ?? 300),
                    'priority' => $record['Priority'] ?? null,
                    'weight' => $record['Weight'] ?? null,
                    'disabled' => (bool) ($record['Disabled'] ?? false),
                    'system' => $this->isSystemDnsRecord($typeName, $name, $value),
                ];
            }

            $nameservers = array_filter([
                $zoneData['data']['Nameserver1'] ?? null,
                $zoneData['data']['Nameserver2'] ?? null,
            ]);

            return response()->json([
                'data' => [
                    'domain' => $domainRecord->name,
                    'zone_id' => (int) $domainRecord->dns_zone_id,
                    'dns_mode' => $domainRecord->dns_mode ?? 'managed',
                    'dns_status' => $domainRecord->dns_status ?? 'unknown',
                    'nameservers' => array_values($nameservers),
                    'records' => $records,
                ],
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'Failed to fetch DNS records: '.$exception->getMessage(),
            ], 502);
        }
    }

    /**
     * Add a DNS record to the website's domain zone.
     *
     * POST /api/platform/v1/websites/{siteId}/dns-records
     */
    public function addDnsRecord(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        if ($this->isExternalDnsMode($website)) {
            return response()->json(['message' => 'DNS records cannot be modified in external DNS mode.'], 403);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:A,AAAA,CNAME,TXT,MX,CAA,SRV,NS,REDIRECT,FLATTEN'],
            'name' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:4096'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'weight' => ['nullable', 'integer', 'min:0'],
        ]);

        $domainRecord = $website->domainRecord;
        if (! $domainRecord || ! $domainRecord->dns_zone_id) {
            return response()->json(['message' => 'No DNS zone configured for this website.'], 400);
        }

        $dnsProvider = $website->getProvider(Provider::TYPE_DNS);
        if (! $dnsProvider) {
            return response()->json(['message' => 'No DNS provider configured.'], 400);
        }

        if ($this->isSystemDnsRecord($validated['type'], $validated['name'], $validated['value'])) {
            return response()->json(['message' => 'This name/type combination is reserved for system-managed DNS records.'], 403);
        }

        try {
            $options = [];
            if (isset($validated['priority'])) {
                $options['Priority'] = $validated['priority'];
            }
            if (isset($validated['weight'])) {
                $options['Weight'] = $validated['weight'];
            }

            $result = BunnyApi::addDnsRecord(
                $dnsProvider,
                (int) $domainRecord->dns_zone_id,
                $validated['type'],
                $validated['name'],
                $validated['value'],
                $validated['ttl'] ?? 300,
                $options
            );

            if (($result['status'] ?? '') !== 'success') {
                return response()->json([
                    'message' => 'Failed to add DNS record: '.($result['message'] ?? 'Unknown error'),
                ], 422);
            }

            return response()->json([
                'message' => 'DNS record added successfully.',
                'data' => [
                    'id' => $result['data']['Id'] ?? null,
                    'type' => $validated['type'],
                    'name' => $validated['name'],
                    'value' => $validated['value'],
                    'ttl' => $validated['ttl'] ?? 300,
                ],
            ], 201);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'Failed to add DNS record: '.$exception->getMessage(),
            ], 502);
        }
    }

    /**
     * Update a DNS record in the website's domain zone.
     *
     * PUT /api/platform/v1/websites/{siteId}/dns-records/{recordId}
     */
    public function updateDnsRecord(Request $request, string $siteId, int $recordId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        if ($this->isExternalDnsMode($website)) {
            return response()->json(['message' => 'DNS records cannot be modified in external DNS mode.'], 403);
        }

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:A,AAAA,CNAME,TXT,MX,CAA,SRV,NS,REDIRECT,FLATTEN'],
            'name' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:4096'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'weight' => ['nullable', 'integer', 'min:0'],
        ]);

        $domainRecord = $website->domainRecord;
        if (! $domainRecord || ! $domainRecord->dns_zone_id) {
            return response()->json(['message' => 'No DNS zone configured for this website.'], 400);
        }

        $dnsProvider = $website->getProvider(Provider::TYPE_DNS);
        if (! $dnsProvider) {
            return response()->json(['message' => 'No DNS provider configured.'], 400);
        }

        if ($this->isSystemRecord($dnsProvider, (int) $domainRecord->dns_zone_id, $recordId)) {
            return response()->json(['message' => 'This is a system-managed DNS record and cannot be modified.'], 403);
        }

        try {
            $updateData = array_filter([
                'Name' => $validated['name'] ?? null,
                'Value' => $validated['value'] ?? null,
                'Ttl' => $validated['ttl'] ?? null,
                'Priority' => $validated['priority'] ?? null,
                'Weight' => $validated['weight'] ?? null,
            ], fn ($value) => $value !== null);

            if (isset($validated['type'])) {
                $updateData['Type'] = $this->dnsRecordTypeCode($validated['type']);
            }

            if (empty($updateData)) {
                return response()->json(['message' => 'No fields provided to update.'], 422);
            }

            $result = BunnyApi::updateDnsRecord(
                $dnsProvider,
                (int) $domainRecord->dns_zone_id,
                $recordId,
                $updateData
            );

            if (($result['status'] ?? '') !== 'success') {
                return response()->json([
                    'message' => 'Failed to update DNS record: '.($result['message'] ?? 'Unknown error'),
                ], 422);
            }

            return response()->json([
                'message' => 'DNS record updated successfully.',
                'data' => ['id' => $recordId],
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'Failed to update DNS record: '.$exception->getMessage(),
            ], 502);
        }
    }

    /**
     * Delete a DNS record from the website's domain zone.
     *
     * DELETE /api/platform/v1/websites/{siteId}/dns-records/{recordId}
     */
    public function deleteDnsRecord(Request $request, string $siteId, int $recordId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        if ($this->isExternalDnsMode($website)) {
            return response()->json(['message' => 'DNS records cannot be modified in external DNS mode.'], 403);
        }

        $domainRecord = $website->domainRecord;
        if (! $domainRecord || ! $domainRecord->dns_zone_id) {
            return response()->json(['message' => 'No DNS zone configured for this website.'], 400);
        }

        $dnsProvider = $website->getProvider(Provider::TYPE_DNS);
        if (! $dnsProvider) {
            return response()->json(['message' => 'No DNS provider configured.'], 400);
        }

        if ($this->isSystemRecord($dnsProvider, (int) $domainRecord->dns_zone_id, $recordId)) {
            return response()->json(['message' => 'This is a system-managed DNS record and cannot be deleted.'], 403);
        }

        try {
            $result = BunnyApi::deleteDnsRecord(
                $dnsProvider,
                (int) $domainRecord->dns_zone_id,
                $recordId
            );

            if (($result['status'] ?? '') !== 'success') {
                return response()->json([
                    'message' => 'Failed to delete DNS record: '.($result['message'] ?? 'Unknown error'),
                ], 422);
            }

            return response()->json([
                'message' => 'DNS record deleted successfully.',
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'Failed to delete DNS record: '.$exception->getMessage(),
            ], 502);
        }
    }

    /**
     * Get CDN status for a website.
     *
     * GET /api/platform/v1/websites/{siteId}/cdn/status
     */
    public function getCdnStatus(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        $pullzoneId = $website->pullzone_id;
        if (! $pullzoneId) {
            return response()->json([
                'data' => [
                    'enabled' => false,
                    'message' => 'CDN is not configured for this website.',
                ],
            ]);
        }

        $cdnProvider = $website->cdnProvider ?? $website->dnsProvider;
        $cdnMeta = $website->getMetadata('cdn', []);
        $cdnSslMeta = $website->getMetadata('cdn_ssl', []);

        $data = [
            'enabled' => true,
            'pullzone_id' => $pullzoneId,
            'edge_hostname' => $cdnMeta['Hostnames'][0]['Value'] ?? ($cdnMeta['Name'] ?? '').'.b-cdn.net',
            'origin_url' => $cdnMeta['OriginUrl'] ?? null,
            'vendor' => 'bunny',
            'force_ssl' => $cdnSslMeta['force_ssl'] ?? false,
            'auto_ssl' => $cdnSslMeta['auto_ssl'] ?? false,
            'ssl_configured_at' => $cdnSslMeta['configured_at'] ?? null,
            'ssl_expires_at' => $cdnSslMeta['expires_at'] ?? null,
            'created_at' => $cdnMeta['created_at'] ?? null,
        ];

        if ($cdnProvider && $request->boolean('live')) {
            try {
                $liveData = BunnyApi::getPullZone($cdnProvider, $pullzoneId);
                if ($liveData['status'] === 'success' && isset($liveData['data'])) {
                    $data['bandwidth_used'] = $liveData['data']['MonthlyBandwidthUsed'] ?? null;
                    $data['monthly_charges'] = $liveData['data']['MonthlyCharges'] ?? null;
                    $data['cache_enabled'] = ($liveData['data']['EnableCacheSlice'] ?? true);
                    $data['hostnames'] = collect($liveData['data']['Hostnames'] ?? [])->map(fn ($hostname) => [
                        'hostname' => $hostname['Value'] ?? '',
                        'force_ssl' => $hostname['ForceSSL'] ?? false,
                        'has_certificate' => $hostname['HasCertificate'] ?? false,
                    ])->toArray();
                }
            } catch (\Exception) {
                $data['live_error'] = 'Could not fetch live CDN data.';
            }
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Purge CDN cache for a website.
     *
     * POST /api/platform/v1/websites/{siteId}/cdn/purge
     */
    public function purgeCdnCache(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        $pullzoneId = $website->pullzone_id;
        if (! $pullzoneId) {
            return response()->json([
                'message' => 'CDN is not configured for this website.',
            ], 400);
        }

        $cdnProvider = $website->cdnProvider ?? $website->dnsProvider;
        if (! $cdnProvider) {
            return response()->json([
                'message' => 'CDN provider not found.',
            ], 400);
        }

        try {
            $url = $request->input('url');

            $result = $url
                ? BunnyApi::purgeUrl($cdnProvider, $url)
                : BunnyApi::purgePullZoneCache($cdnProvider, $pullzoneId);

            if (($result['status'] ?? '') === 'success') {
                return response()->json([
                    'message' => $url ? 'URL cache purged successfully.' : 'CDN cache purged successfully.',
                ]);
            }

            return response()->json([
                'message' => 'CDN purge returned unexpected response.',
                'details' => $result['message'] ?? null,
            ], 422);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => 'Failed to purge CDN cache: '.$exception->getMessage(),
            ], 500);
        }
    }

    protected function isExternalDnsMode(Website $website): bool
    {
        $domainRecord = $website->domainRecord;

        return $website->dns_mode === 'external'
            || ($domainRecord && $domainRecord->dns_mode === 'external');
    }

    protected function isSystemDnsRecord(string $type, string $name, string $value): bool
    {
        $normalizedName = strtolower(trim($name));
        $normalizedValue = strtolower(trim($value));

        if ($type === 'CNAME' && ($normalizedName === '' || $normalizedName === '@') && str_contains($normalizedValue, '.b-cdn.net')) {
            return true;
        }

        if ($type === 'CNAME' && $normalizedName === 'www' && str_contains($normalizedValue, '.b-cdn.net')) {
            return true;
        }

        if ($type === 'A' && $normalizedName === 'origin') {
            return true;
        }

        return false;
    }

    protected function isSystemRecord(Provider $dnsProvider, int $zoneId, int $recordId): bool
    {
        try {
            $zoneData = BunnyApi::getDnsZone($dnsProvider, $zoneId);
            $records = $zoneData['data']['Records'] ?? [];

            foreach ($records as $record) {
                if (($record['Id'] ?? null) === $recordId) {
                    $type = $this->dnsRecordTypeName((int) ($record['Type'] ?? 0));
                    $name = $record['Name'] ?? '';
                    $value = $record['Value'] ?? '';

                    return $this->isSystemDnsRecord($type, $name, $value);
                }
            }
        } catch (\Exception) {
            return true;
        }

        return false;
    }

    protected function dnsRecordTypeName(int $typeCode): string
    {
        return match ($typeCode) {
            0 => 'A',
            1 => 'AAAA',
            2 => 'CNAME',
            3 => 'TXT',
            4 => 'MX',
            5 => 'REDIRECT',
            6 => 'FLATTEN',
            7 => 'PULLZONE',
            8 => 'SRV',
            9 => 'CAA',
            10 => 'PTR',
            11 => 'SCRIPT',
            12 => 'NS',
            default => 'UNKNOWN',
        };
    }

    protected function dnsRecordTypeCode(string $type): int
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
