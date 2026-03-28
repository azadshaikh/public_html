<?php

namespace Modules\Platform\Http\Controllers\Concerns;

use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Jobs\WebsiteProvision;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Website;

trait InteractsWithWebsiteApiProvisioning
{
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

        $website->setMetadata('dns_confirmed_by_user', true);
        $website->setMetadata('dns_confirmed_at', now()->toIso8601String());
        $website->setMetadata('dns_check_count', 0);
        $website->save();

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

    protected function normalizeProvisioningStepStatus(string $rawStatus): string
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

    protected function stepStatusLabel(string $rawStatus): string
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
