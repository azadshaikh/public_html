<?php

namespace Modules\Platform\Jobs;

use App\Traits\IsMonitored;
use BackedEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Website;
use Throwable;

/**
 * Sends a webhook callback to an agency's configured webhook URL.
 *
 * Dispatched after provisioning completes, fails, or a website status changes.
 * The payload is signed with HMAC-SHA256 using the agency's secret key.
 */
class SendAgencyWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public int $agencyId,
        public string $event,
        public array $payload
    ) {}

    /**
     * Dispatch a webhook for a website if it belongs to an agency.
     *
     * @param  array<string, mixed>  $extraPayload  Merged into the standard website payload.
     */
    public static function dispatchForWebsite(Website $website, string $event, array $extraPayload = []): void
    {
        if (! $website->agency_id) {
            return;
        }

        $payload = array_merge([
            'event_id' => 'evt_'.Str::ulid(),
            'site_id' => $website->site_id,
            'site_id_prefix' => $website->site_id_prefix,
            'site_id_zero_padding' => $website->site_id_zero_padding,
            'domain' => $website->domain,
            'status' => $website->status instanceof BackedEnum ? $website->status->value : (string) $website->status,
            'admin_slug' => $website->admin_slug,
        ], $extraPayload);

        try {
            dispatch(new self($website->agency_id, $event, $payload))
                ->onQueue('default');
        } catch (Throwable $throwable) {
            Log::warning('Failed to dispatch agency webhook', [
                'website_id' => $website->id,
                'agency_id' => $website->agency_id,
                'event' => $event,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch a webhook for a website after the current HTTP response is sent.
     *
     * Use this from request-driven lifecycle actions so remote webhook delivery
     * never delays the browser response.
     *
     * @param  array<string, mixed>  $extraPayload
     */
    public static function dispatchForWebsiteAfterResponse(Website $website, string $event, array $extraPayload = []): void
    {
        if (! $website->agency_id) {
            return;
        }

        $payload = array_merge([
            'event_id' => 'evt_'.Str::ulid(),
            'site_id' => $website->site_id,
            'site_id_prefix' => $website->site_id_prefix,
            'site_id_zero_padding' => $website->site_id_zero_padding,
            'domain' => $website->domain,
            'status' => $website->status instanceof BackedEnum ? $website->status->value : (string) $website->status,
            'admin_slug' => $website->admin_slug,
        ], $extraPayload);

        try {
            dispatch(new self($website->agency_id, $event, $payload))
                ->onQueue('default')
                ->afterResponse();
        } catch (Throwable $throwable) {
            Log::warning('Failed to dispatch agency webhook after response', [
                'website_id' => $website->id,
                'agency_id' => $website->agency_id,
                'event' => $event,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Seconds to wait before retrying.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $this->queueMonitorLabel('Agency #'.$this->agencyId.' · '.$this->event);
        $agency = Agency::query()->find($this->agencyId);
        /** @var Agency|null $agency */
        if (! $agency) {
            Log::warning('SendAgencyWebhook: Agency not found', ['agency_id' => $this->agencyId]);

            return;
        }

        $webhookUrl = $agency->webhook_url;

        if (empty($webhookUrl)) {
            Log::info('SendAgencyWebhook: No webhook_url configured, skipping', [
                'agency_id' => $agency->id,
                'event' => $this->event,
            ]);

            return;
        }

        $body = array_merge($this->payload, [
            'event' => $this->event,
            'timestamp' => now()->toIso8601String(),
        ]);

        try {
            $jsonBody = json_encode($body, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            Log::error('SendAgencyWebhook: JSON encoding failed', [
                'agency_id' => $agency->id,
                'event' => $this->event,
                'error' => $jsonException->getMessage(),
            ]);

            return;
        }

        $signature = $this->generateSignature($jsonBody, $agency);

        try {
            $request = Http::timeout(15);

            // Skip SSL verification in local/dev environments (self-signed certs)
            if (app()->environment('local')) {
                $request = $request->withoutVerifying();
            }

            $response = $request
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->event,
                    'User-Agent' => 'Astero-Platform/1.0',
                ])
                ->withBody($jsonBody, 'application/json')
                ->post($webhookUrl);

            if ($response->successful()) {
                Log::info('SendAgencyWebhook: Delivered', [
                    'agency_id' => $agency->id,
                    'event' => $this->event,
                    'status' => $response->status(),
                ]);
            } else {
                Log::warning(
                    'SendAgencyWebhook: Non-success response',
                    $this->nonSuccessLogContext($agency, $webhookUrl, $body, $response->status())
                );

                // Retry on server errors
                if ($response->serverError()) {
                    $this->release($this->attempts() * 30);
                }
            }
        } catch (Throwable $throwable) {
            Log::error(
                'SendAgencyWebhook: Delivery failed',
                $this->deliveryFailedLogContext($agency, $webhookUrl, $body, $throwable)
            );

            throw $throwable; // Let queue retry
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function nonSuccessLogContext(Agency $agency, string $webhookUrl, array $body, int $status): array
    {
        return [
            'agency_id' => $agency->id,
            'event' => $this->event,
            'status' => $status,
            'webhook_url' => $webhookUrl,
            'request_body' => $body,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function deliveryFailedLogContext(Agency $agency, string $webhookUrl, array $body, Throwable $e): array
    {
        return [
            'agency_id' => $agency->id,
            'event' => $this->event,
            'error' => $e->getMessage(),
            'webhook_url' => $webhookUrl,
            'request_body' => $body,
        ];
    }

    /**
     * Generate HMAC-SHA256 signature for the webhook payload.
     */
    protected function generateSignature(string $jsonBody, Agency $agency): string
    {
        $secretKey = $agency->plain_secret_key ?? '';

        return hash_hmac('sha256', $jsonBody, $secretKey);
    }
}
