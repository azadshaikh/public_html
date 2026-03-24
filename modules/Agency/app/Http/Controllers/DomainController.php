<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Agency\Exceptions\PlatformApiException;
use Modules\Agency\Models\AgencyWebsite;
use Modules\Agency\Services\PlatformApiClient;

class DomainController extends Controller
{
    public function __construct(
        private PlatformApiClient $platformApiClient,
    ) {}

    // ─────────────────────────────────────────────────────────
    // Index — List all customer domains
    // ─────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $user = auth()->user();

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $dnsMode = (string) $request->input('dns_mode', 'all');
        $sortBy = (string) $request->input('sort', 'created_at');
        $sortDir = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);

        $allowedStatuses = ['all', 'active', 'provisioning', 'waiting_for_dns', 'failed', 'suspended', 'expired'];
        $allowedDnsModes = ['all', 'managed', 'external'];
        $sortableColumns = ['domain', 'status', 'created_at'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        if (! in_array($dnsMode, $allowedDnsModes, true)) {
            $dnsMode = 'all';
        }

        $query = AgencyWebsite::query()
            ->where('owner_id', $user->id)
            ->whereNull('deleted_at')
            ->whereRaw("(metadata->>'dns_mode') IN ('managed', 'external')");

        if ($search !== '') {
            $escaped = $this->escapeLikePattern($search);
            $pattern = sprintf('%%%s%%', $escaped);

            $query->where(function (Builder $builder) use ($pattern): void {
                $builder->where('domain', 'ilike', $pattern)
                    ->orWhere('name', 'ilike', $pattern);
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($dnsMode !== 'all') {
            $query->whereRaw("metadata->>'dns_mode' = ?", [$dnsMode]);
        }

        if (in_array($sortBy, $sortableColumns, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest();
        }

        $rows = $query
            ->paginate($perPage)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (AgencyWebsite $website): array => [
                'id' => $website->id,
                'name' => $website->name,
                'domain' => $website->domain,
                'status' => $website->status->value,
                'status_label' => $website->status->label(),
                'dns_mode' => $website->getMetadata('dns_mode', 'subdomain'),
                'show_url' => route('agency.domains.show', $website->id),
                'created_at' => $website->created_at?->toIso8601String(),
            ]);

        return Inertia::render('agency/domains/index', [
            'config' => $this->getDatagridConfig(),
            'rows' => $rows,
            'statistics' => [],
            'filters' => [
                'search' => $search,
                'status' => $status === 'all' ? '' : $status,
                'dns_mode' => $dnsMode === 'all' ? '' : $dnsMode,
                'sort' => $sortBy,
                'direction' => $sortDir,
                'per_page' => $perPage,
                'view' => (string) $request->input('view', 'cards'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Show — Domain detail with DNS records
    // ─────────────────────────────────────────────────────────

    public function show(int $id): Response|RedirectResponse
    {
        $user = auth()->user();

        $website = AgencyWebsite::query()
            ->where('owner_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $dnsMode = $website->getMetadata('dns_mode', 'subdomain');

        // Subdomain-based domains are not customer-managed — block direct access
        if ($dnsMode === 'subdomain') {
            abort(404);
        }
        $dnsData = null;
        $dnsError = null;

        // Fetch DNS records for managed domains (full CRUD) or external domains (read-only instructions)
        if (in_array($dnsMode, ['managed', 'external']) && $website->site_id) {
            try {
                $response = $this->platformApiClient->getDnsRecords($website->site_id);
                $dnsData = $response['data'] ?? null;
            } catch (\Exception $e) {
                Log::warning('Failed to fetch DNS records for domain', [
                    'website_id' => $website->id,
                    'site_id' => $website->site_id,
                    'error' => $e->getMessage(),
                ]);
                $dnsError = 'Unable to load DNS records. Please try again later.';
            }
        }

        return Inertia::render('agency/domains/show', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'domain' => $website->domain,
                'status' => $website->status->value,
                'status_label' => $website->status->label(),
                'created_at' => $website->created_at?->toDateString(),
            ],
            'dnsMode' => $dnsMode,
            'dnsData' => $dnsData,
            'dnsError' => $dnsError,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // DNS Record CRUD (proxied to Platform API)
    // ─────────────────────────────────────────────────────────

    /**
     * Add a DNS record.
     *
     * POST /domains/{id}/dns-records
     */
    public function storeDnsRecord(Request $request, int $id): JsonResponse
    {
        $website = $this->findOwnedWebsite($id);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:A,AAAA,CNAME,TXT,MX,CAA,SRV'],
            'name' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:4096'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if (! $website->site_id) {
            return response()->json(['message' => 'Website has no platform association.'], 400);
        }

        try {
            $result = $this->platformApiClient->addDnsRecord($website->site_id, $validated);

            return response()->json([
                'message' => $result['message'] ?? 'DNS record added.',
                'data' => $result['data'] ?? null,
            ], 201);
        } catch (\Exception $e) {
            Log::warning('Failed to add DNS record', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
            ]);

            $status = $e instanceof PlatformApiException ? $e->statusCode : 422;

            return response()->json([
                'message' => 'Failed to add DNS record: '.$e->getMessage(),
            ], $status ?: 422);
        }
    }

    /**
     * Update a DNS record.
     *
     * PUT /domains/{id}/dns-records/{recordId}
     */
    public function updateDnsRecord(Request $request, int $id, int $recordId): JsonResponse
    {
        $website = $this->findOwnedWebsite($id);

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:A,AAAA,CNAME,TXT,MX,CAA,SRV'],
            'name' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:4096'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if (! $website->site_id) {
            return response()->json(['message' => 'Website has no platform association.'], 400);
        }

        try {
            $result = $this->platformApiClient->updateDnsRecord($website->site_id, $recordId, $validated);

            return response()->json([
                'message' => $result['message'] ?? 'DNS record updated.',
                'data' => $result['data'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update DNS record', [
                'website_id' => $website->id,
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);

            $status = $e instanceof PlatformApiException ? $e->statusCode : 422;

            return response()->json([
                'message' => 'Failed to update DNS record: '.$e->getMessage(),
            ], $status ?: 422);
        }
    }

    /**
     * Delete a DNS record.
     *
     * DELETE /domains/{id}/dns-records/{recordId}
     */
    public function destroyDnsRecord(int $id, int $recordId): JsonResponse
    {
        $website = $this->findOwnedWebsite($id);

        if (! $website->site_id) {
            return response()->json(['message' => 'Website has no platform association.'], 400);
        }

        try {
            $result = $this->platformApiClient->deleteDnsRecord($website->site_id, $recordId);

            return response()->json([
                'message' => $result['message'] ?? 'DNS record deleted.',
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to delete DNS record', [
                'website_id' => $website->id,
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);

            $status = $e instanceof PlatformApiException ? $e->statusCode : 422;

            return response()->json([
                'message' => 'Failed to delete DNS record: '.$e->getMessage(),
            ], $status ?: 422);
        }
    }

    // ─────────────────────────────────────────────────────────
    // CDN Management
    // ─────────────────────────────────────────────────────────

    /**
     * Get CDN status for a domain's website.
     *
     * GET /domains/{id}/cdn/status (AJAX)
     */
    public function cdnStatus(int $id): JsonResponse
    {
        $website = $this->findOwnedWebsite($id);

        if (! $website->site_id) {
            return response()->json([
                'data' => ['enabled' => false, 'message' => 'No platform association.'],
            ]);
        }

        try {
            $result = $this->platformApiClient->getCdnStatus($website->site_id, live: true);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::warning('Failed to fetch CDN status', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'data' => ['enabled' => false, 'message' => 'Unable to load CDN status.'],
            ]);
        }
    }

    /**
     * Purge CDN cache for a domain's website.
     *
     * POST /domains/{id}/cdn/purge (AJAX)
     */
    public function purgeCdnCache(Request $request, int $id): JsonResponse
    {
        $website = $this->findOwnedWebsite($id);

        if (! $website->site_id) {
            return response()->json(['message' => 'No platform association.'], 400);
        }

        try {
            $url = $request->input('url');
            $result = $this->platformApiClient->purgeCdnCache($website->site_id, $url);

            return response()->json([
                'message' => $result['message'] ?? 'CDN cache purged.',
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to purge CDN cache', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
            ]);

            $status = $e instanceof PlatformApiException ? $e->statusCode : 422;

            return response()->json([
                'message' => 'Failed to purge CDN cache: '.$e->getMessage(),
            ], $status ?: 422);
        }
    }

    private function getDatagridConfig(): array
    {
        return [
            'columns' => [
                [
                    'key' => 'domain',
                    'label' => 'Domain',
                    'sortable' => true,
                    'width' => '320px',
                ],
                [
                    'key' => 'dns_mode',
                    'label' => 'DNS Mode',
                    'width' => '140px',
                ],
                [
                    'key' => 'status_label',
                    'label' => 'Status',
                    'sortable' => true,
                    'width' => '140px',
                ],
                [
                    'key' => 'created_at',
                    'label' => 'Created',
                    'sortable' => true,
                    'width' => '130px',
                ],
            ],
            'filters' => [
                [
                    'key' => 'dns_mode',
                    'type' => 'select',
                    'label' => 'DNS Mode',
                    'placeholder' => 'All DNS modes',
                    'options' => [
                        ['value' => '', 'label' => 'All DNS modes'],
                        ['value' => 'managed', 'label' => 'Managed DNS'],
                        ['value' => 'external', 'label' => 'External DNS'],
                    ],
                ],
                [
                    'key' => 'status',
                    'type' => 'select',
                    'label' => 'Status',
                    'placeholder' => 'All statuses',
                    'options' => [
                        ['value' => '', 'label' => 'All statuses'],
                        ['value' => 'active', 'label' => 'Active'],
                        ['value' => 'provisioning', 'label' => 'Provisioning'],
                        ['value' => 'waiting_for_dns', 'label' => 'Waiting for DNS'],
                        ['value' => 'failed', 'label' => 'Failed'],
                        ['value' => 'suspended', 'label' => 'Suspended'],
                        ['value' => 'expired', 'label' => 'Expired'],
                    ],
                ],
            ],
            'actions' => [],
            'statusTabs' => [],
            'form' => [],
            'settings' => [
                'perPage' => 15,
                'defaultSort' => 'created_at',
                'defaultDirection' => 'desc',
                'enableBulkActions' => false,
                'enableExport' => false,
                'hasNotes' => false,
                'entityName' => 'domain',
                'entityPlural' => 'domains',
                'statusField' => 'status',
            ],
        ];
    }

    private function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    private function findOwnedWebsite(int $id): AgencyWebsite
    {
        return AgencyWebsite::query()
            ->where('owner_id', auth()->id())
            ->where('id', $id)
            ->firstOrFail();
    }
}
