<?php

declare(strict_types=1);

namespace Modules\Agency\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Agency\Exceptions\PlatformApiException;
use Throwable;

/**
 * HTTP client for the Platform Provisioning API.
 *
 * All methods throw PlatformApiException on failure.
 * Response data is returned as associative arrays — the Agency module
 * never touches Platform Eloquent models directly.
 */
class PlatformApiClient
{
    private readonly string $baseUrl;

    private readonly string $secretKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('agency.platform_api_url'), '/');
        $this->secretKey = (string) config('agency.agency_secret_key');
    }

    // ──────────────────────────────────────────────────────────
    // Website endpoints
    // ──────────────────────────────────────────────────────────

    /**
     * List websites for this agency.
     *
     * @param  array{status?: string, page?: int, per_page?: int}  $params
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listWebsites(array $params = []): array
    {
        $response = $this->request()->get('/api/platform/v1/websites', $params);

        return $this->parseResponse($response);
    }

    /**
     * Create (provision) a new website.
     *
     * @param  array{domain: string, name: string, type?: string, is_www?: bool, dns_mode?: string, customer?: array{ref?: string, email?: string, name?: string, company?: string, phone?: string}, plan?: array{ref?: string, name?: string, quotas?: array<string, mixed>, features?: array<string, mixed>}}  $data
     * @return array{data: array<string, mixed>}
     */
    public function createWebsite(array $data): array
    {
        $response = $this->request()->post('/api/platform/v1/websites', $data);

        return $this->parseResponse($response, 201);
    }

    /**
     * Replace the plan snapshot on a website.
     *
     * @param  array{ref?: string|null, name?: string, quotas?: array<string, mixed>, features?: array<string, mixed>}  $data
     * @return array{data: array<string, mixed>}
     */
    public function updateWebsitePlan(string $siteId, array $data): array
    {
        $response = $this->request()->patch(sprintf('/api/platform/v1/websites/%s/plan', $siteId), $data);

        return $this->parseResponse($response);
    }

    /**
     * Replace the customer snapshot on a website.
     *
     * @param  array{ref?: string|null, email?: string, name?: string, company?: string, phone?: string}  $data
     * @return array{data: array<string, mixed>}
     */
    public function updateWebsiteCustomer(string $siteId, array $data): array
    {
        $response = $this->request()->patch(sprintf('/api/platform/v1/websites/%s/customer', $siteId), $data);

        return $this->parseResponse($response);
    }

    /**
     * Get a single website by its site_id (the Platform-assigned identifier).
     *
     * @return array{data: array<string, mixed>}
     */
    public function getWebsite(string $siteId): array
    {
        $response = $this->request()->get('/api/platform/v1/websites/'.$siteId);

        return $this->parseResponse($response);
    }

    /**
     * Change a website's status.
     *
     * @return array{data: array<string, mixed>}
     */
    public function changeWebsiteStatus(string $siteId, string $status): array
    {
        $response = $this->request()->patch(sprintf('/api/platform/v1/websites/%s/status', $siteId), [
            'status' => $status,
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Soft-delete (trash) a website.
     *
     * @return array{data: array<string, mixed>}
     */
    public function destroyWebsite(string $siteId): array
    {
        $response = $this->request()->delete('/api/platform/v1/websites/'.$siteId);

        return $this->parseResponse($response);
    }

    /**
     * Permanently delete a trashed website.
     *
     * @return array{data: array<string, mixed>}
     */
    public function forceDeleteWebsite(string $siteId): array
    {
        $response = $this->request()->delete(sprintf('/api/platform/v1/websites/%s/force-delete', $siteId));

        return $this->parseResponse($response);
    }

    /**
     * Restore a trashed website.
     *
     * @return array{data: array<string, mixed>}
     */
    public function restoreWebsite(string $siteId): array
    {
        $response = $this->request()->post(sprintf('/api/platform/v1/websites/%s/restore', $siteId));

        return $this->parseResponse($response);
    }

    /**
     * Check whether a domain is available (not already provisioned).
     *
     * Returns true if the domain is free, false if it is already in use.
     * Throws PlatformApiException on connection failure — callers should
     * treat a thrown exception as "unavailable" to fail safely.
     */
    public function checkDomainAvailable(string $domain): bool
    {
        $response = $this->request()->get('/api/platform/v1/websites/domain-check', [
            'domain' => $domain,
        ]);

        $data = $this->parseResponse($response);

        return (bool) ($data['available'] ?? false);
    }

    /**
     * Get provisioning progress for a website with ordered provisioning steps.
     *
     * @return array{data: array<string, mixed>}
     */
    public function getWebsiteProvisioningStatus(string $siteId): array
    {
        $response = $this->request()->get(sprintf('/api/platform/v1/websites/%s/provisioning', $siteId));

        return $this->parseResponse($response);
    }

    /**
     * Sync website info from Platform (pulls latest status, version, etc.)
     *
     * @return array{data: array<string, mixed>}
     */
    public function syncWebsite(string $siteId): array
    {
        $response = $this->request()->post(sprintf('/api/platform/v1/websites/%s/sync', $siteId));

        return $this->parseResponse($response);
    }

    /**
     * Retry failed provisioning for a website.
     *
     * @return array{data: array<string, mixed>}
     */
    public function retryProvision(string $siteId): array
    {
        $response = $this->request()->post(sprintf('/api/platform/v1/websites/%s/retry-provision', $siteId));

        return $this->parseResponse($response);
    }

    /**
     * Confirm that the user has updated their DNS records.
     *
     * Tells Platform to start DNS verification polling for this website.
     *
     * @return array{data: array<string, mixed>, message: string}
     */
    public function confirmDns(string $siteId): array
    {
        $response = $this->request()->post(sprintf('/api/platform/v1/websites/%s/confirm-dns', $siteId));

        return $this->parseResponse($response);
    }

    // ──────────────────────────────────────────────────────────
    // DNS Record Management
    // ──────────────────────────────────────────────────────────

    /**
     * Get DNS records for a website's domain zone.
     *
     * @return array{data: array<string, mixed>}
     */
    public function getDnsRecords(string $siteId): array
    {
        $response = $this->request()->get(sprintf('/api/platform/v1/websites/%s/dns-records', $siteId));

        return $this->parseResponse($response);
    }

    /**
     * Add a DNS record to a website's domain zone.
     *
     * @param  array{type: string, name: string, value: string, ttl?: int, priority?: int, weight?: int}  $data
     * @return array{data: array<string, mixed>, message: string}
     */
    public function addDnsRecord(string $siteId, array $data): array
    {
        $response = $this->request()->post(sprintf('/api/platform/v1/websites/%s/dns-records', $siteId), $data);

        return $this->parseResponse($response);
    }

    /**
     * Update a DNS record in a website's domain zone.
     *
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, message: string}
     */
    public function updateDnsRecord(string $siteId, int $recordId, array $data): array
    {
        $response = $this->request()->put(sprintf('/api/platform/v1/websites/%s/dns-records/%d', $siteId, $recordId), $data);

        return $this->parseResponse($response);
    }

    /**
     * Delete a DNS record from a website's domain zone.
     *
     * @return array{message: string}
     */
    public function deleteDnsRecord(string $siteId, int $recordId): array
    {
        $response = $this->request()->delete(sprintf('/api/platform/v1/websites/%s/dns-records/%d', $siteId, $recordId));

        return $this->parseResponse($response);
    }

    // ──────────────────────────────────────────────────────────
    // CDN Management
    // ──────────────────────────────────────────────────────────

    /**
     * Get CDN status for a website.
     *
     * @return array{data: array<string, mixed>}
     */
    public function getCdnStatus(string $siteId, bool $live = false): array
    {
        $url = sprintf('/api/platform/v1/websites/%s/cdn/status', $siteId);
        if ($live) {
            $url .= '?live=1';
        }

        $response = $this->request()->get($url);

        return $this->parseResponse($response);
    }

    /**
     * Purge CDN cache for a website.
     *
     * @return array{message: string}
     */
    public function purgeCdnCache(string $siteId, ?string $url = null): array
    {
        $response = $this->request()->post(
            sprintf('/api/platform/v1/websites/%s/cdn/purge', $siteId),
            $url ? ['url' => $url] : [],
        );

        return $this->parseResponse($response);
    }

    // ──────────────────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────────────────

    /**
     * Build a configured HTTP client instance.
     */
    private function request(): PendingRequest
    {
        throw_if($this->baseUrl === '' || $this->secretKey === '', PlatformApiException::class, 'Platform API is not configured. Set PLATFORM_API_URL and AGENCY_SECRET_KEY in .env.', 0);

        $client = Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'X-Agency-Key' => $this->secretKey,
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->connectTimeout(10);

        if (app()->environment('local')) {
            $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Parse a response and throw on non-success status codes.
     *
     * @return array<string, mixed>
     */
    private function parseResponse(Response $response, int $expectedStatus = 200): array
    {
        try {
            $body = $response->json() ?? [];
        } catch (Throwable) {
            $body = [];
        }

        if ($response->status() !== $expectedStatus && ! $response->successful()) {
            Log::warning('Platform API error', [
                'status' => $response->status(),
                'body' => $body,
                'url' => $response->effectiveUri()?->__toString(),
            ]);

            throw PlatformApiException::fromResponse($response->status(), $body);
        }

        return $body;
    }
}
