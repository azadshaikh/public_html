<?php

namespace Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\Agency;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the X-Agency-Key header against platform_agencies.secret_key.
 *
 * On match, binds the resolved Agency model to the request so downstream
 * controllers can access it via $request->attributes->get('agency').
 */
class AgencyApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = (string) $request->header('X-Agency-Key', '');

        if ($providedKey === '') {
            return $this->unauthorized($request, 'header_missing');
        }

        $agency = $this->resolveAgency($providedKey);

        if (! $agency instanceof Agency) {
            return $this->unauthorized($request, 'key_mismatch');
        }

        if ($agency->status !== 'active') {
            return response()->json([
                'message' => 'Agency account is not active.',
            ], 403);
        }

        // Bind the agency to the request for downstream use
        $request->attributes->set('agency', $agency);

        return $next($request);
    }

    /**
     * Find the agency whose decrypted secret_key matches the provided key.
     */
    protected function resolveAgency(string $providedKey): ?Agency
    {
        $agencies = Agency::query()->whereNotNull('secret_key')
            ->where('status', 'active')
            ->get(['id', 'secret_key', 'status', 'name']);
        /** @var Collection<int, Agency> $agencies */
        foreach ($agencies as $agency) {
            $plainKey = $agency->plain_secret_key;

            if ($plainKey !== null && hash_equals($plainKey, $providedKey)) {
                return $agency;
            }
        }

        return null;
    }

    protected function unauthorized(Request $request, string $reason): Response
    {
        Log::warning('Agency API unauthorized request', [
            'path' => '/'.$request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'failure_reason' => $reason,
        ]);

        return response()->json([
            'message' => 'Invalid or missing agency API key.',
        ], 401);
    }
}
