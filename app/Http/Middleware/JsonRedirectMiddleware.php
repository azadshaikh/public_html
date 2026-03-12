<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convert classic 302 redirects into JSON redirects for AJAX form submissions.
 *
 * Why:
 * - Many forms submit via fetch with `Accept: application/json`.
 * - A RedirectResponse becomes an HTML response after the browser follows it,
 *   which forces a full reload (and can also consume flash messages).
 *
 * This middleware returns a small JSON payload so the frontend can navigate
 * via Unpoly without a hard reload, while keeping flash messages intact.
 */
class JsonRedirectMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Only for fetch/AJAX style requests.
        if (! $request->expectsJson()) {
            return $response;
        }

        // If Unpoly made the request, let Unpoly handle redirects normally.
        if ($request->hasHeader('X-Up-Target')) {
            return $response;
        }

        // Only transform non-GET/HEAD redirects.
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)) {
            return $response;
        }

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        $redirectUrl = $response->getTargetUrl();

        $message = null;
        $flashType = null;

        if ($request->hasSession()) {
            foreach (['success', 'error', 'warning', 'info'] as $type) {
                if ($request->session()->has($type)) {
                    $flashType = $type;
                    $message = $request->session()->get($type);
                    break;
                }
            }
        }

        // Always return 200 here; the frontend decides how to display it.
        return new JsonResponse([
            'status' => 'success',
            'redirect' => $redirectUrl,
            'message' => $message,
            'flash_type' => $flashType,
        ]);
    }
}
