<?php

namespace Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Website;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the X-Website-Key header against platform_websites.secret_key.
 *
 * On match, binds the resolved Website model to the request so downstream
 * controllers can access it via $request->attributes->get('website').
 */
class WebsiteApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = (string) $request->header('X-Website-Key', '');

        if ($providedKey === '') {
            return $this->unauthorized($request, 'header_missing', 'API key missing.');
        }

        $website = $this->resolveWebsite($providedKey);

        if (! $website instanceof Website) {
            return $this->unauthorized($request, 'key_mismatch', 'Invalid API key.');
        }

        $isActiveWebsite = $website->status instanceof WebsiteStatus
            ? $website->status === WebsiteStatus::Active
            : $website->status === WebsiteStatus::Active->value;

        if (! $isActiveWebsite) {
            return response()->json([
                'message' => 'Website is not active.',
            ], 403);
        }

        // Bind the website to the request for downstream use
        $request->attributes->set('website', $website);

        return $next($request);
    }

    /**
     * Find the website whose decrypted secret_key matches the provided key.
     */
    protected function resolveWebsite(string $providedKey): ?Website
    {
        /** @var Collection<int, Website> $websites */
        $websites = Website::query()
            ->whereNotNull('secret_key')
            ->get(['id', 'secret_key', 'status', 'domain']);

        foreach ($websites as $website) {
            $plainKey = $website->plain_secret_key;

            if ($plainKey !== null && hash_equals($plainKey, $providedKey)) {
                return $website;
            }
        }

        return null;
    }

    protected function unauthorized(Request $request, string $reason, string $message): Response
    {
        Log::warning('Website API unauthorized request', [
            'path' => '/'.$request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'failure_reason' => $reason,
        ]);

        return response()->json([
            'message' => $message,
        ], 401);
    }
}
