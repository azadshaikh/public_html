<?php

namespace Modules\Platform\Http\Controllers\Api\V1;

use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Platform\Http\Controllers\Concerns\InteractsWithWebsiteApiDnsAndCdn;
use Modules\Platform\Http\Controllers\Concerns\InteractsWithWebsiteApiProvisioning;
use Modules\Platform\Http\Requests\Api\V1\ChangeStatusRequest;
use Modules\Platform\Http\Requests\Api\V1\CreateWebsiteRequest;
use Modules\Platform\Http\Requests\Api\V1\UpdateCustomerRequest;
use Modules\Platform\Http\Requests\Api\V1\UpdatePlanRequest;
use Modules\Platform\Http\Resources\Api\V1\WebsiteApiResource;
use Modules\Platform\Jobs\SendAgencyWebhook;
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
    use InteractsWithWebsiteApiDnsAndCdn;
    use InteractsWithWebsiteApiProvisioning;

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
}
