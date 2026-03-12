<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unpoly Middleware
 *
 * Detects Unpoly AJAX requests and enables partial page rendering.
 * Handles flash messages by converting session flash to response headers.
 * When an Unpoly request is detected, views can skip rendering the
 * full layout and return only the target content.
 */
class UnpolyMiddleware
{
    /**
     * Flash message keys to check in session
     */
    protected array $flashKeys = ['success', 'error', 'warning', 'info', 'message'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Detect Unpoly request via X-Up-Target header
        $isUnpoly = $request->hasHeader('X-Up-Target');
        $upTarget = $request->header('X-Up-Target');
        $upMode = $request->header('X-Up-Mode', 'root');
        $upFailTarget = $request->header('X-Up-Fail-Target');
        $upValidate = $request->header('X-Up-Validate');

        // Store Unpoly info on request for later use
        $request->attributes->set('unpoly', [
            'enabled' => $isUnpoly,
            'target' => $upTarget,
            'mode' => $upMode,
            'failTarget' => $upFailTarget,
            'validate' => $upValidate,
        ]);

        // Share with views
        view()->share('isUnpoly', $isUnpoly);
        view()->share('upTarget', $upTarget);
        view()->share('upMode', $upMode);

        $response = $next($request);

        // If a model mutation happened during this request, ensure Unpoly's client cache
        // is cleared so subsequent navigations/DataGrid loads show fresh data.
        // Important: our AJAX form handler submits via fetch (not Unpoly), so we must
        // attach this header even for non-Unpoly/JSON responses.
        if ($request->attributes->get('astero.unpoly_clear_cache') === true) {
            // Unpoly 3 expects X-Up-Expire-Cache. Keep X-Up-Clear-Cache for backwards compatibility.
            $response->headers->set('X-Up-Expire-Cache', '*');
            $response->headers->set('X-Up-Clear-Cache', '*');
        }

        // Process response for Unpoly requests
        if ($isUnpoly) {
            $this->processUnpolyResponse($request, $response);
        }

        return $response;
    }

    /**
     * Process response for Unpoly requests
     */
    protected function processUnpolyResponse(Request $request, Response $response): void
    {
        // Add target header
        $upTarget = $request->header('X-Up-Target');
        $response->headers->set('X-Up-Target', $upTarget);

        // Inject notification count for authenticated users
        // This piggybacks notification updates on every Unpoly navigation,
        // reducing the need for frequent polling
        $this->injectNotificationCount($request, $response);

        // Handle flash messages for redirect responses
        if ($response instanceof RedirectResponse) {
            $this->handleFlashMessages($request, $response);
        }

        // Handle validation errors
        if ($response->getStatusCode() === 422 && $response instanceof JsonResponse) {
            $this->handleValidationErrors($response);
        }

        // Note: cache-clearing header is handled in handle() for all responses.
    }

    /**
     * Inject unread notification count into response headers
     *
     * This allows the frontend to update the notification badge on every
     * Unpoly navigation without dedicated polling requests.
     */
    protected function injectNotificationCount(Request $request, Response $response): void
    {
        $user = $request->user();

        if (! $user) {
            return;
        }

        $unreadCount = Cache::remember(
            'notifications.unread_count.'.$user->id,
            now()->addSeconds(5),
            fn () => $user->notifications()->whereNull('read_at')->count()
        );

        $response->headers->set('X-Notification-Count', (string) $unreadCount);
    }

    /**
     * Convert session flash messages to response headers
     */
    protected function handleFlashMessages(Request $request, Response $response): void
    {
        $session = $request->session();

        foreach ($this->flashKeys as $key) {
            $message = $session->get($key);

            if ($message) {
                // Handle array messages (with HTML actions, etc.)
                if (is_array($message)) {
                    $message = $message['message'] ?? $message['text'] ?? json_encode($message);
                }

                // Set headers
                $response->headers->set('X-Flash-Message', rawurlencode((string) $message));
                $response->headers->set('X-Flash-Type', $this->normalizeFlashType($key));

                // Don't remove from session - let it flash for the next page too
                // This ensures non-JS fallback works
                break; // Only send one flash message
            }
        }
    }

    /**
     * Handle validation errors from JSON response
     */
    protected function handleValidationErrors(Response $response): void
    {
        $content = json_decode($response->getContent(), true);

        if (isset($content['errors'])) {
            $response->headers->set(
                'X-Validation-Errors',
                rawurlencode(json_encode($content['errors']))
            );
        }
    }

    /**
     * Normalize flash message type to standard types
     */
    protected function normalizeFlashType(string $key): string
    {
        return match ($key) {
            'success' => 'success',
            'error', 'danger' => 'error',
            'warning' => 'warning',
            'info', 'message' => 'info',
            default => 'info',
        };
    }
}
