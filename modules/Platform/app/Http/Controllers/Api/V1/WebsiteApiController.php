<?php

namespace Modules\Platform\Http\Controllers\Api\V1;

use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Http\Requests\Api\V1\ChangeStatusRequest;
use Modules\Platform\Http\Requests\Api\V1\CreateWebsiteRequest;
use Modules\Platform\Http\Requests\Api\V1\UpdateCustomerRequest;
use Modules\Platform\Http\Requests\Api\V1\UpdatePlanRequest;
use Modules\Platform\Http\Resources\Api\V1\WebsiteApiResource;
use Modules\Platform\Jobs\SendAgencyWebhook;
use Modules\Platform\Jobs\WebsiteProvision;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteLifecycleService;
use Modules\Platform\Services\WebsiteService;
use RuntimeException;

/**
 * API controller for agency-to-platform website lifecycle management.
 *
 * All endpoints are scoped to the authenticated agency via AgencyApiKeyMiddleware.
 * The agency is resolved from the X-Agency-Key header and bound to the request.
 */
class WebsiteApiController extends Controller
{
    public function __construct(
        private readonly WebsiteService $websiteService,
        private readonly WebsiteLifecycleService $websiteLifecycleService,
    ) {}

    /**
     * List websites for the authenticated agency.
     *
     * GET /api/platform/v1/websites?status=active&customer_ref=cust_123&plan_ref=plan_abc&page=1&per_page=15
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $agency = $this->resolveAgency($request);

        $query = Website::query()->where('agency_id', $agency->id)
            ->with('server');

        if ($status = $request->input('status')) {
            if ($status === 'trash') {
                $query->withTrashed()->whereNotNull('deleted_at');
            } else {
                $query->where('status', $status);
            }
        }

        if ($customerRef = $request->input('customer_ref')) {
            $query->where('customer_ref', $customerRef);
        }

        if ($planRef = $request->input('plan_ref')) {
            $query->where('plan_ref', $planRef);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        $websites = $query->latest()->paginate($perPage);

        return WebsiteApiResource::collection($websites);
    }

    /**
     * Check whether a domain is already taken for this agency.
     *
     * GET /api/platform/v1/websites/domain-check?domain=sub.example.com
     *
     * Checks the platform_websites table by exact `domain` column match,
     * including soft-deleted rows, so a recently deleted website still
     * blocks the same domain from being re-used immediately.
     *
     * Response 200: {"available": true}  — domain is free
     * Response 200: {"available": false} — domain already in use
     */
    public function domainCheck(Request $request): JsonResponse
    {
        $domain = trim((string) $request->input('domain', ''));

        if ($domain === '') {
            return response()->json(['error' => 'domain parameter is required.'], 422);
        }

        $agency = $this->resolveAgency($request);

        $taken = Website::query()
            ->where('agency_id', $agency->id)
            ->where('domain', $domain)
            ->withTrashed()
            ->exists();

        return response()->json(['available' => ! $taken]);
    }

    /**
     * Create a new website and dispatch provisioning.
     *
     * POST /api/platform/v1/websites
     */
    public function store(CreateWebsiteRequest $request): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $validated = $request->validated();

        // Guard against duplicate domain within the same agency
        $duplicate = Website::query()->where('agency_id', $agency->id)
            ->where('domain', $validated['domain'])
            ->withTrashed()
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'A website with this domain already exists.',
            ], 409);
        }

        // Flatten nested customer/plan arrays into the data bag expected by WebsiteService
        $customerPayload = $validated['customer'] ?? [];
        $planPayload = $validated['plan'] ?? [];

        $data = [
            'domain' => $validated['domain'],
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'paid',
            'plan_tier' => $validated['plan_tier'] ?? 'basic',
            'agency_id' => $agency->id,
            'is_www' => $validated['is_www'] ?? false,
            'primary_category_id' => $validated['primary_category_id'] ?? null,
            'sub_category_id' => $validated['sub_category_id'] ?? null,
            'dns_mode' => $validated['dns_mode'] ?? null,
            'skip_dns' => $validated['skip_dns'] ?? false,
            'skip_cdn' => $validated['skip_cdn'] ?? false,
            'skip_ssl_issue' => $validated['skip_ssl_issue'] ?? false,
            // Customer snapshot
            'customer_ref' => $customerPayload['ref'] ?? null,
            'customer_data' => $customerPayload ?: null,
            // Plan snapshot
            'plan_ref' => $planPayload['ref'] ?? null,
            'plan_data' => $planPayload ?: null,
        ];

        // Inherit DNS and CDN providers from the agency
        $agencyDnsProvider = $agency->getProvider(Provider::TYPE_DNS);
        if ($agencyDnsProvider) {
            $data['dns_provider_id'] = $agencyDnsProvider->id;
        }

        $agencyCdnProvider = $agency->getProvider(Provider::TYPE_CDN);
        if ($agencyCdnProvider) {
            $data['cdn_provider_id'] = $agencyCdnProvider->id;
        }

        try {
            $website = $this->websiteService->create($data);
            /** @var Website $website */

            return response()->json([
                'data' => new WebsiteApiResource($website->load('server')),
            ], 201);
        } catch (RuntimeException $runtimeException) {
            $statusCode = str_contains($runtimeException->getMessage(), 'No available server') ? 503 : 422;

            return response()->json([
                'message' => $runtimeException->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Get a single website's details.
     *
     * GET /api/platform/v1/websites/{siteId}
     */
    public function show(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        return response()->json([
            'data' => new WebsiteApiResource($website->load('server')),
        ]);
    }

    /**
     * Get website provisioning progress with ordered step sequence.
     *
     * GET /api/platform/v1/websites/{siteId}/provisioning
     */
    public function provisioning(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        $stepsConfig = config('platform.website.steps', []);
        $stepsData = $website->getProvisioningSteps();
        $steps = [];

        foreach ($stepsConfig as $stepKey => $stepConfig) {
            $rawStatus = (string) ($stepsData[$stepKey]['status'] ?? 'pending');

            $steps[] = [
                'key' => $stepKey,
                'title' => (string) ($stepConfig['title'] ?? ucfirst(str_replace('_', ' ', $stepKey))),
                'description' => (string) ($stepConfig['info'] ?? ''),
                'status' => $this->normalizeProvisioningStepStatus($rawStatus),
                'status_label' => $this->stepStatusLabel($rawStatus),
                'raw_status' => $rawStatus,
                'message' => (string) ($stepsData[$stepKey]['message'] ?? ''),
                'updated_at' => $stepsData[$stepKey]['updated_at'] ?? null,
                'is_email_step' => $stepKey === 'send_emails',
            ];
        }

        // Show one active "in_progress" step while provisioning is running.
        $websiteStatus = $website->status instanceof BackedEnum
            ? $website->status->value
            : (string) $website->status;

        if ($websiteStatus === 'provisioning') {
            foreach ($steps as &$step) {
                if ($step['status'] === 'pending') {
                    $step['status'] = 'in_progress';
                    $step['status_label'] = 'In Progress';
                    break;
                }
            }

            unset($step);
        }

        $completedSteps = count(array_filter($steps, fn (array $step): bool => $step['status'] === 'completed'));
        $failedSteps = count(array_filter($steps, fn (array $step): bool => $step['status'] === 'failed'));
        $inProgressSteps = count(array_filter($steps, fn (array $step): bool => $step['status'] === 'in_progress'));
        $pendingSteps = count(array_filter($steps, fn (array $step): bool => $step['status'] === 'pending'));
        $waitingSteps = count(array_filter($steps, fn (array $step): bool => $step['status'] === 'waiting'));
        $totalSteps = count($steps);
        $percentage = $totalSteps > 0 ? (int) round(($completedSteps + $inProgressSteps) / $totalSteps * 100) : 0;

        // Include DNS instructions when waiting for DNS verification
        $dnsInstructions = null;
        if ($waitingSteps > 0 && $website->domainRecord) {
            $dnsInstructions = $website->domainRecord->getMetadata('dns_instructions');
        }

        $responseData = [
            'site_id' => $website->site_id,
            'site_id_prefix' => $website->site_id_prefix,
            'site_id_zero_padding' => $website->site_id_zero_padding,
            'website_status' => $websiteStatus,
            'website_status_label' => $website->status instanceof BackedEnum ? $website->status->label() : ucfirst($websiteStatus),
            'progress' => [
                'total_steps' => $totalSteps,
                'completed_steps' => $completedSteps,
                'failed_steps' => $failedSteps,
                'in_progress_steps' => $inProgressSteps,
                'pending_steps' => $pendingSteps,
                'percentage' => $percentage,
            ],
            'steps' => $steps,
            'email_step' => collect($steps)->firstWhere('key', 'send_emails'),
            'updated_at' => $website->updated_at?->toIso8601String(),
            'created_at' => $website->created_at?->toIso8601String(),
        ];

        if ($dnsInstructions) {
            $responseData['dns_instructions'] = $dnsInstructions;
        }

        // Include DNS check tracking metadata when waiting for DNS
        if ($waitingSteps > 0 || $websiteStatus === 'waiting_for_dns') {
            $responseData['dns_confirmed_by_user'] = (bool) $website->getMetadata('dns_confirmed_by_user');
            $responseData['dns_confirmed_at'] = $website->getMetadata('dns_confirmed_at');
            $responseData['dns_check_count'] = (int) ($website->getMetadata('dns_check_count') ?? 0);
            $responseData['dns_last_checked_at'] = $website->getMetadata('dns_last_checked_at');
            $responseData['dns_check_result'] = $website->getMetadata('dns_check_result') ?? null;
            $responseData['dns_domain_not_registered'] = (bool) ($website->getMetadata('dns_domain_not_registered') ?? false);
        }

        return response()->json(['data' => $responseData]);
    }

    /**
     * Replace the plan snapshot on a website.
     *
     * PATCH /api/platform/v1/websites/{siteId}/plan
     *
     * Semantics: top-level key replacement.
     * Keys present in the request body are replaced wholesale;
     * keys absent are left unchanged.
     */
    public function updatePlan(UpdatePlanRequest $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);
        $validated = $request->validated();

        $currentData = $website->plan_data ?? [];
        $updates = [];
        $previous = [
            'plan_ref' => $website->plan_ref,
            'plan_data' => $website->plan_data,
        ];

        if ($request->has('ref')) {
            $updates['plan_ref'] = $validated['ref'];
        }

        // Merge remaining plan_data fields
        $dataKeys = ['name', 'quotas', 'features'];
        $newData = $currentData;
        foreach ($dataKeys as $key) {
            if ($request->has($key)) {
                $newData[$key] = $validated[$key];
            }
        }

        // Persist ref inside plan_data as well, keeping it consistent
        if ($request->has('ref')) {
            $newData['ref'] = $validated['ref'];
        }

        $updates['plan_data'] = empty($newData) ? null : $newData;

        $website->update($updates);

        /** @var Website $fresh */
        $fresh = $website->fresh()->load('server');
        SendAgencyWebhook::dispatchForWebsite($fresh, 'website.updated', [
            'changes' => ['plan'],
            'previous' => $previous,
            'plan' => $fresh->plan_info,
        ]);

        return response()->json([
            'data' => new WebsiteApiResource($fresh),
        ]);
    }

    /**
     * Replace the customer snapshot on a website.
     *
     * PATCH /api/platform/v1/websites/{siteId}/customer
     *
     * Semantics: top-level key replacement.
     * Pass null for ref to disassociate the customer entirely.
     */
    public function updateCustomer(UpdateCustomerRequest $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);
        $validated = $request->validated();

        $currentData = $website->customer_data ?? [];
        $updates = [];
        $previous = [
            'customer_ref' => $website->customer_ref,
            'customer_data' => $website->customer_data,
        ];

        // Disassociation: when ref is explicitly null, wipe the entire customer snapshot
        if ($request->has('ref') && $validated['ref'] === null) {
            $updates['customer_ref'] = null;
            $updates['customer_data'] = null;
        } else {
            if ($request->has('ref')) {
                $updates['customer_ref'] = $validated['ref'];
            }

            $dataKeys = ['email', 'name', 'company', 'phone'];
            $newData = $currentData;
            foreach ($dataKeys as $key) {
                if ($request->has($key)) {
                    $newData[$key] = $validated[$key];
                }
            }

            if ($request->has('ref')) {
                $newData['ref'] = $validated['ref'];
            }

            $updates['customer_data'] = empty($newData) ? null : $newData;
        }

        $website->update($updates);

        /** @var Website $fresh */
        $fresh = $website->fresh()->load('server');
        SendAgencyWebhook::dispatchForWebsite($fresh, 'website.updated', [
            'changes' => ['customer'],
            'previous' => $previous,
            'customer' => $fresh->customer_info,
        ]);

        return response()->json([
            'data' => new WebsiteApiResource($fresh),
        ]);
    }

    /**
     * Change a website's status (suspend, unsuspend, expire, activate).
     *
     * PATCH /api/platform/v1/websites/{siteId}/status
     */
    public function changeStatus(ChangeStatusRequest $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);
        $validated = $request->validated();

        $previousStatus = $website->status instanceof BackedEnum ? $website->status->value : (string) $website->status;

        $result = $this->websiteLifecycleService->updateStatus($website, $validated['status']);

        if ($result['status'] === 'error') {
            return response()->json([
                'message' => $result['message'],
            ], $result['code'] ?? 422);
        }

        $website->refresh();

        return response()->json([
            'data' => [
                'site_id' => $website->site_id,
                'site_id_prefix' => $website->site_id_prefix,
                'site_id_zero_padding' => $website->site_id_zero_padding,
                'status' => $website->status instanceof BackedEnum ? $website->status->value : (string) $website->status,
                'previous_status' => $previousStatus,
            ],
        ]);
    }

    /**
     * Trash (soft-delete) a website.
     *
     * DELETE /api/platform/v1/websites/{siteId}
     */
    public function destroy(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        $this->websiteLifecycleService->destroy($website);

        return response()->json([
            'message' => 'Website queued for deletion.',
            'data' => [
                'site_id' => $website->site_id,
                'site_id_prefix' => $website->site_id_prefix,
                'site_id_zero_padding' => $website->site_id_zero_padding,
                'status' => 'trashing',
            ],
        ]);
    }

    /**
     * Permanently delete a trashed website.
     *
     * DELETE /api/platform/v1/websites/{siteId}/force-delete
     */
    public function forceDelete(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);

        $website = Website::withTrashed()
            ->where('uid', $siteId)
            ->where('agency_id', $agency->id)
            ->first();
        /** @var Website|null $website */
        if (! $website) {
            return response()->json(['message' => 'Website not found.'], 404);
        }

        if (! $website->trashed()) {
            return response()->json(['message' => 'Website must be in trash before permanent deletion.'], 422);
        }

        // destroy() already handles permanent deletion when the website is trashed
        $this->websiteLifecycleService->destroy($website);

        return response()->json([
            'message' => 'Website permanently deleted.',
            'data' => [
                'site_id' => $website->site_id,
            ],
        ]);
    }

    /**
     * Restore a trashed website.
     *
     * POST /api/platform/v1/websites/{siteId}/restore
     */
    public function restore(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);

        $website = Website::withTrashed()
            ->where('uid', $siteId)
            ->where('agency_id', $agency->id)
            ->first();
        /** @var Website|null $website */
        if (! $website) {
            return response()->json(['message' => 'Website not found.'], 404);
        }

        $this->websiteLifecycleService->restore($website);

        $website->refresh();

        return response()->json([
            'data' => [
                'site_id' => $website->site_id,
                'site_id_prefix' => $website->site_id_prefix,
                'site_id_zero_padding' => $website->site_id_zero_padding,
                'status' => $website->status instanceof BackedEnum ? $website->status->value : (string) $website->status,
            ],
        ]);
    }

    /**
     * Sync website info from the remote server.
     *
     * POST /api/platform/v1/websites/{siteId}/sync
     */
    public function sync(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        $result = $this->websiteService->syncWebsiteInfo($website);

        if ($result['success']) {
            $website->refresh();

            return response()->json([
                'message' => $result['message'],
                'data' => new WebsiteApiResource($website->load(['server:id,name'])),
            ]);
        }

        return response()->json([
            'message' => $result['message'],
        ], 500);
    }

    /**
     * Retry provisioning for a failed website.
     *
     * POST /api/platform/v1/websites/{siteId}/retry-provision
     */
    public function retryProvision(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        $statusValue = $website->status instanceof BackedEnum ? $website->status->value : (string) $website->status;

        if ($statusValue !== 'failed') {
            return response()->json([
                'message' => 'Website is not in a failed state.',
            ], 400);
        }

        // Reset failed provisioning steps to pending
        $metadata = $website->metadata ?? [];
        if (isset($metadata['provisioning_steps'])) {
            foreach ($metadata['provisioning_steps'] as $key => $step) {
                if (isset($step['status']) && $step['status'] === 'failed') {
                    $metadata['provisioning_steps'][$key]['status'] = 'pending';
                    $metadata['provisioning_steps'][$key]['message'] = null;
                    $metadata['provisioning_steps'][$key]['started_at'] = null;
                    $metadata['provisioning_steps'][$key]['completed_at'] = null;
                }
            }

            $website->metadata = $metadata;
        }

        $website->status = WebsiteStatus::Provisioning;
        $website->save();
        $website->resetProvisioningRun();

        dispatch(new WebsiteProvision($website));

        return response()->json([
            'message' => 'Provisioning retry has been initiated.',
            'data' => [
                'site_id' => $website->site_id,
                'status' => 'provisioning',
            ],
        ]);
    }

    /**
     * Confirm that the user has updated their DNS records.
     *
     * POST /api/platform/v1/websites/{siteId}/confirm-dns
     *
     * Sets dns_confirmed_by_user metadata so the poll command starts checking.
     * Resets check count for a clean start.
     */
    public function confirmDns(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        $statusValue = $website->status instanceof BackedEnum ? $website->status->value : (string) $website->status;

        if ($statusValue !== 'waiting_for_dns') {
            return response()->json([
                'message' => 'Website is not waiting for DNS verification.',
            ], 400);
        }

        // Mark as confirmed — the poll command will start checking
        $website->setMetadata('dns_confirmed_by_user', true);
        $website->setMetadata('dns_confirmed_at', now()->toIso8601String());
        $website->setMetadata('dns_check_count', 0);
        $website->save();

        // Update the step message to reflect confirmation
        $website->updateProvisioningStep(
            'verify_dns',
            'User confirmed DNS update. Verification checks starting.',
            'waiting'
        );

        return response()->json([
            'message' => 'DNS confirmation received. Verification checks will begin shortly.',
            'data' => [
                'site_id' => $website->site_id,
                'dns_confirmed_at' => $website->getMetadata('dns_confirmed_at'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // DNS Record Management
    // ─────────────────────────────────────────────────────────

    /**
     * List DNS records for a website's domain zone.
     *
     * GET /api/platform/v1/websites/{siteId}/dns-records
     *
     * Fetches records directly from Bunny DNS (source of truth).
     */
    public function dnsRecords(Request $request, string $siteId): JsonResponse
    {
        $agency = $this->resolveAgency($request);
        $website = $this->findWebsiteOrFail($siteId, $agency);

        $domainRecord = $website->domainRecord;

        // External mode: return dns_instructions from domain metadata (no Bunny zone)
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
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch DNS records: '.$e->getMessage(),
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

        // Block writes for external DNS mode (zone is not under our control)
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

        // Guard: block adding records that would conflict with system-managed records
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
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add DNS record: '.$e->getMessage(),
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

        // Block writes for external DNS mode (zone is not under our control)
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

        // Guard: prevent modification of system records
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
            ], fn ($v) => $v !== null);

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
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update DNS record: '.$e->getMessage(),
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

        // Block writes for external DNS mode (zone is not under our control)
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

        // Guard: prevent deletion of system records
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
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete DNS record: '.$e->getMessage(),
            ], 502);
        }
    }

    /**
     * Check if the website's domain is using external DNS mode.
     */
    private function isExternalDnsMode(Website $website): bool
    {
        $domainRecord = $website->domainRecord;

        return $website->dns_mode === 'external'
            || ($domainRecord && $domainRecord->dns_mode === 'external');
    }

    /**
     * Check if a DNS record is a system-managed record by its attributes.
     *
     * System records are those created by BunnySetupDnsCommand:
     * - CNAME at apex ("") → *.b-cdn.net (CDN routing)
     * - CNAME at "www" → *.b-cdn.net (CDN routing)
     * - A at "origin" → server IP (origin bypass)
     */
    private function isSystemDnsRecord(string $type, string $name, string $value): bool
    {
        $normalizedName = strtolower(trim($name));
        $normalizedValue = strtolower(trim($value));

        // Apex CNAME → b-cdn.net (CDN pull zone hostname)
        if ($type === 'CNAME' && ($normalizedName === '' || $normalizedName === '@') && str_contains($normalizedValue, '.b-cdn.net')) {
            return true;
        }

        // www CNAME → b-cdn.net
        if ($type === 'CNAME' && $normalizedName === 'www' && str_contains($normalizedValue, '.b-cdn.net')) {
            return true;
        }

        // origin A record → server IP
        if ($type === 'A' && $normalizedName === 'origin') {
            return true;
        }

        return false;
    }

    /**
     * Check if a record (by ID) in a zone is a system record.
     *
     * Fetches zone records from Bunny and looks up the record by ID.
     */
    private function isSystemRecord(Provider $dnsProvider, int $zoneId, int $recordId): bool
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
            // Fail-closed: deny mutation when protection check cannot be completed
            return true;
        }

        return false;
    }

    /**
     * Convert Bunny DNS record type integer to human-readable name.
     */
    private function dnsRecordTypeName(int $typeCode): string
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

    /**
     * Convert DNS record type name to Bunny integer code.
     */
    private function dnsRecordTypeCode(string $type): int
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

    // ─────────────────────────────────────────────────────────
    // CDN Management
    // ─────────────────────────────────────────────────────────

    /**
     * Get CDN status for a website.
     *
     * GET /api/platform/v1/websites/{siteId}/cdn/status
     *
     * Returns pull zone details, edge hostname, SSL config, and cache status.
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

        // Optionally fetch live pull zone data from Bunny API
        if ($cdnProvider && $request->boolean('live')) {
            try {
                $liveData = BunnyApi::getPullZone($cdnProvider, $pullzoneId);
                if ($liveData['status'] === 'success' && isset($liveData['data'])) {
                    $data['bandwidth_used'] = $liveData['data']['MonthlyBandwidthUsed'] ?? null;
                    $data['monthly_charges'] = $liveData['data']['MonthlyCharges'] ?? null;
                    $data['cache_enabled'] = ($liveData['data']['EnableCacheSlice'] ?? true);
                    $data['hostnames'] = collect($liveData['data']['Hostnames'] ?? [])->map(fn ($h) => [
                        'hostname' => $h['Value'] ?? '',
                        'force_ssl' => $h['ForceSSL'] ?? false,
                        'has_certificate' => $h['HasCertificate'] ?? false,
                    ])->toArray();
                }
            } catch (\Exception $e) {
                $data['live_error'] = 'Could not fetch live CDN data.';
            }
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Purge CDN cache for a website.
     *
     * POST /api/platform/v1/websites/{siteId}/cdn/purge
     *
     * Purges the entire pull zone cache or a specific URL.
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

            if ($url) {
                // Purge specific URL
                $result = BunnyApi::purgeUrl($cdnProvider, $url);
            } else {
                // Purge entire pull zone
                $result = BunnyApi::purgePullZoneCache($cdnProvider, $pullzoneId);
            }

            if (($result['status'] ?? '') === 'success') {
                return response()->json([
                    'message' => $url ? 'URL cache purged successfully.' : 'CDN cache purged successfully.',
                ]);
            }

            return response()->json([
                'message' => 'CDN purge returned unexpected response.',
                'details' => $result['message'] ?? null,
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to purge CDN cache: '.$e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    /**
     * Resolve the authenticated agency from the request.
     */
    protected function resolveAgency(Request $request): Agency
    {
        $agency = $request->attributes->get('agency');

        abort_unless($agency instanceof Agency, response()->json(['message' => 'Unauthorized agency context.'], 401));

        return $agency;
    }

    /**
     * Find a website by site_id (uid) scoped to the given agency, or return 404.
     *
     * site_id is a virtual accessor that returns uid, so we query by uid.
     * Returns a JSON response directly rather than using abort(404) to bypass
     * the CMS frontend 404 handler registered in bootstrap/app.php.
     */
    protected function findWebsiteOrFail(string $siteId, Agency $agency): Website
    {
        $website = Website::query()->where('uid', $siteId)
            ->where('agency_id', $agency->id)
            ->first();
        /** @var Website|null $website */
        abort_unless((bool) $website, response()->json(['message' => 'Website not found.'], 404));

        return $website;
    }

    private function normalizeProvisioningStepStatus(string $rawStatus): string
    {
        return match ($rawStatus) {
            'completed', 'done' => 'completed',
            'in_progress', 'running', 'provisioning' => 'in_progress',
            'pending' => 'pending',
            'failed' => 'failed',
            'reverted' => 'reverted',
            'waiting' => 'waiting',
            default => 'pending',
        };
    }

    private function stepStatusLabel(string $rawStatus): string
    {
        return match ($rawStatus) {
            'completed', 'done' => 'Completed',
            'in_progress', 'running', 'provisioning' => 'In Progress',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'reverted' => 'Reverted',
            'waiting' => 'Waiting for DNS',
            default => 'Pending',
        };
    }
}
