<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Agency\Models\AgencyWebsite;

/**
 * Receives webhook events from the Platform (console) instance.
 *
 * Validates the HMAC-SHA256 signature in the X-Webhook-Signature header
 * using the AGENCY_SECRET_KEY, then updates local records accordingly.
 *
 * Endpoint: POST /api/agency/v1/webhooks/platform
 */
class WebhookController extends Controller
{
    /**
     * Handle an incoming Platform webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Webhook-Signature');
        $event = $request->header('X-Webhook-Event', 'unknown');

        if (! $this->verifySignature($request, $signature)) {
            Log::warning('Platform webhook signature verification failed', [
                'event' => $event,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();

        Log::info('Platform webhook received', [
            'event' => $event,
            'site_id' => $payload['site_id'] ?? null,
        ]);

        return match ($event) {
            'website.provisioned' => $this->handleWebsiteProvisioned($payload),
            'website.provision_failed' => $this->handleWebsiteProvisionFailed($payload),
            'website.status_changed' => $this->handleWebsiteStatusChanged($payload),
            'website.updated' => $this->handleWebsiteUpdated($payload),
            'website.deleted' => $this->handleWebsiteDeleted($payload),
            'website.restored' => $this->handleWebsiteRestored($payload),
            default => $this->handleUnknownEvent($event, $payload),
        };
    }

    /**
     * Website provisioning completed successfully.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleWebsiteProvisioned(array $payload): JsonResponse
    {
        $website = AgencyWebsite::syncFromWebhook($payload);

        if (! $website instanceof AgencyWebsite) {
            Log::warning('Webhook: website.provisioned — no matching local record', [
                'site_id' => $payload['site_id'] ?? null,
            ]);

            return response()->json(['message' => 'accepted_without_local_record'], 202);
        }

        // TODO: Send notification to the website owner about successful provisioning

        return response()->json(['message' => 'ok']);
    }

    /**
     * Website provisioning failed.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleWebsiteProvisionFailed(array $payload): JsonResponse
    {
        $website = AgencyWebsite::syncFromWebhook($payload);

        if (! $website instanceof AgencyWebsite) {
            Log::warning('Webhook: website.provision_failed — no matching local record', [
                'site_id' => $payload['site_id'] ?? null,
            ]);

            return response()->json(['message' => 'accepted_without_local_record'], 202);
        }

        Log::error('Website provisioning failed', [
            'site_id' => $website->site_id,
            'domain' => $website->domain,
            'error' => $payload['error'] ?? null,
        ]);

        // TODO: Send notification to the owner / admin about the failure

        return response()->json(['message' => 'ok']);
    }

    /**
     * Website status changed (suspend, unsuspend, expire, etc.).
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleWebsiteStatusChanged(array $payload): JsonResponse
    {
        $website = AgencyWebsite::syncFromWebhook($payload);

        if (! $website instanceof AgencyWebsite) {
            return response()->json(['message' => 'accepted_without_local_record'], 202);
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * Website infrastructure data updated (version, server, admin slug, etc.).
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleWebsiteUpdated(array $payload): JsonResponse
    {
        $website = AgencyWebsite::syncFromWebhook($payload);

        if (! $website instanceof AgencyWebsite) {
            Log::warning('Webhook: website.updated — no matching local record', [
                'site_id' => $payload['site_id'] ?? null,
            ]);

            return response()->json(['message' => 'accepted_without_local_record'], 202);
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * Website has been soft-deleted (trashed).
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleWebsiteDeleted(array $payload): JsonResponse
    {
        $siteId = $payload['site_id'] ?? null;
        $website = $siteId ? AgencyWebsite::query()->where('site_id', $siteId)->first() : null;

        if ($website) {
            $website->delete(); // soft-delete locally
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * Website has been restored from trash.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleWebsiteRestored(array $payload): JsonResponse
    {
        $siteId = $payload['site_id'] ?? null;
        $website = $siteId ? AgencyWebsite::withTrashed()->where('site_id', $siteId)->first() : null;

        if ($website && $website->trashed()) {
            $website->restore();
        }

        // Also sync any updated data from payload
        if ($website) {
            AgencyWebsite::syncFromWebhook($payload);
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * Unknown event — log and acknowledge.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleUnknownEvent(string $event, array $payload): JsonResponse
    {
        Log::info('Platform webhook: unhandled event', [
            'event' => $event,
            'payload_keys' => array_keys($payload),
        ]);

        return response()->json(['message' => 'ok']);
    }

    /**
     * Verify the HMAC-SHA256 signature from the Platform.
     */
    private function verifySignature(Request $request, ?string $signature): bool
    {
        if (! $signature) {
            return false;
        }

        $secretKey = (string) config('agency.agency_secret_key');
        if ($secretKey === '') {
            return false;
        }

        $rawBody = $request->getContent();
        $computed = hash_hmac('sha256', $rawBody, $secretKey);

        return hash_equals($computed, $signature);
    }
}
