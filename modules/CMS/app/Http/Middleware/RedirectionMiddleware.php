<?php

namespace Modules\CMS\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\CMS\Events\RedirectionHit;
use Modules\CMS\Models\Redirection;
use Modules\CMS\Services\RedirectionCacheService;
use Symfony\Component\HttpFoundation\Response;

class RedirectionMiddleware
{
    /**
     * Maximum redirect hops to prevent infinite loops.
     */
    private const int MAX_HOPS = 5;

    /**
     * Cookie name for tracking redirect hops.
     */
    private const string HOP_COOKIE = 'cms_redirect_hops';

    /**
     * Hop cookie TTL in minutes.
     */
    private const int HOP_COOKIE_TTL_MINUTES = 1;

    public function __construct(
        private readonly RedirectionCacheService $cacheService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isAdminRequest($request)) {
            return $next($request);
        }

        if (! active_modules('cms')) {
            return $next($request);
        }

        // Only process GET requests
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        // Get the current path (without domain, with leading slash)
        $currentPath = '/'.trim($request->path(), '/');

        // Normalize path: ensure it starts with / and remove trailing slashes
        if ($currentPath !== '/') {
            $currentPath = rtrim($currentPath, '/');
        }

        // Get query string for full URL matching
        $queryString = $request->getQueryString();
        $currentPathWithQuery = $queryString ? $currentPath.'?'.$queryString : $currentPath;

        // Check for redirect loop protection via cookie
        $hopData = $this->getHopData($request);
        if ($hopData['count'] >= self::MAX_HOPS) {
            // Too many redirects - show error page with the redirect chain
            return $this->renderLoopError($hopData['chain']);
        }

        // Get active redirections from cache service (uses two-tier caching)
        $redirections = $this->cacheService->getActive();

        // Find matching redirect (try with query string first, then without)
        $matchResult = $this->findMatchingRedirect($redirections, $currentPath, $currentPathWithQuery);

        if ($matchResult) {
            [$redirection, $matches] = $matchResult;

            // Check if target would create an immediate loop
            $targetPath = $redirection->target_url;
            $loopChain = [];
            if ($redirection->url_type === 'internal' && $this->wouldCreateLoop($redirections, $currentPath, $targetPath, $loopChain)) {
                // Show error page with the detected loop chain
                return $this->renderLoopError($loopChain);
            }

            // Build target URL with captured groups
            $targetUrl = $redirection->buildTargetUrl($currentPath, $matches);

            // For internal URLs, use the url() helper
            if ($redirection->url_type === 'internal') {
                $targetUrl = url($targetUrl);
            }

            // Get valid redirect status code
            $statusCode = $this->getValidStatusCode($redirection->redirect_type);

            // Fire hit event asynchronously
            $this->recordHit($redirection);

            // Create redirect response with hop counter cookie
            $response = redirect($targetUrl, $statusCode);

            // Update hop data (cookie expires in 1 minute)
            $newHopData = [
                'count' => $hopData['count'] + 1,
                'chain' => array_merge($hopData['chain'], [$currentPathWithQuery]),
            ];

            return $response->withCookie(cookie(self::HOP_COOKIE, json_encode($newHopData), self::HOP_COOKIE_TTL_MINUTES));
        }

        // No redirect - clear the hop counter if it exists
        $response = $next($request);
        if ($hopData['count'] > 0) {
            if (method_exists($response, 'withoutCookie')) {
                $response->withoutCookie(self::HOP_COOKIE);
            } else {
                $response->headers->clearCookie(self::HOP_COOKIE);
            }
        }

        return $response;
    }

    /**
     * Get hop data from cookie.
     */
    protected function getHopData(Request $request): array
    {
        $cookieValue = $request->cookie(self::HOP_COOKIE);

        if (! $cookieValue) {
            return ['count' => 0, 'chain' => []];
        }

        $data = json_decode($cookieValue, true);

        if (! is_array($data)) {
            return ['count' => 0, 'chain' => []];
        }

        return [
            'count' => (int) ($data['count'] ?? 0),
            'chain' => (array) ($data['chain'] ?? []),
        ];
    }

    /**
     * Render the redirect loop error page.
     */
    protected function renderLoopError(array $chain): Response
    {
        // Add the final path that would complete the loop
        if ($chain !== [] && count($chain) > 1) {
            $chain[] = $chain[0].' (loop)';
        }

        return response()
            ->view('cms::errors.redirect-loop', ['chain' => $chain], 508)
            ->withoutCookie(self::HOP_COOKIE);
    }

    /**
     * Check if redirecting from source to target would create an immediate loop.
     *
     * @param  array  $chain  Reference array to store the redirect chain if loop detected
     */
    protected function wouldCreateLoop(Collection $redirections, string $sourcePath, string $targetPath, array &$chain = []): bool
    {
        // Check if target redirects back to source (direct loop)
        $visited = [$sourcePath];
        $currentTarget = $targetPath;
        $hops = 0;

        while ($hops < self::MAX_HOPS) {
            // Normalize the path
            $normalizedTarget = '/'.trim((string) $currentTarget, '/');
            if ($normalizedTarget !== '/') {
                $normalizedTarget = rtrim($normalizedTarget, '/');
            }

            // Check if we've seen this path before (loop detected)
            if (in_array($normalizedTarget, $visited)) {
                // Store the chain for display
                $chain = $visited;
                $chain[] = $normalizedTarget.' (loop)';

                return true;
            }

            $visited[] = $normalizedTarget;

            // Find if this target has its own redirect
            $nextRedirect = null;
            foreach ($redirections as $redirect) {
                if ($redirect->match_type === 'exact' && $redirect->source_url === $normalizedTarget) {
                    $nextRedirect = $redirect;
                    break;
                }
            }

            if (! $nextRedirect || $nextRedirect->url_type === 'external') {
                // No more redirects or external URL - no loop
                return false;
            }

            $currentTarget = $nextRedirect->target_url;
            $hops++;
        }

        // Too many hops - treat as potential loop
        $chain = $visited;
        $chain[] = '... (too many redirects)';

        return true;
    }

    /**
     * Find a matching redirect for the given path.
     *
     * @param  string  $currentPath  The path without query string
     * @param  string  $currentPathWithQuery  The path with query string (if any)
     * @return array|null [Redirection, matches] or null if no match
     */
    protected function findMatchingRedirect(Collection $redirections, string $currentPath, string $currentPathWithQuery): ?array
    {
        // First pass: exact matches (fastest)
        // Try matching with query string first (more specific), then without
        $exactMatches = $redirections->where('match_type', 'exact');
        foreach ($exactMatches as $redirection) {
            // Check if source URL contains a query string
            $sourceHasQuery = str_contains((string) $redirection->source_url, '?');

            if ($sourceHasQuery) {
                // Match against full path with query string
                if ($redirection->source_url === $currentPathWithQuery) {
                    return [$redirection, []];
                }
            } elseif ($redirection->source_url === $currentPath) {
                // Match against path only
                return [$redirection, []];
            }
        }

        // Second pass: wildcard matches
        $wildcardMatches = $redirections->where('match_type', 'wildcard');
        foreach ($wildcardMatches as $redirection) {
            $sourceHasQuery = str_contains((string) $redirection->source_url, '?');
            $pathToMatch = $sourceHasQuery ? $currentPathWithQuery : $currentPath;

            $matches = $redirection->matchesPath($pathToMatch);
            if ($matches !== false) {
                return [$redirection, is_array($matches) ? $matches : []];
            }
        }

        // Third pass: regex matches (slowest, most flexible)
        $regexMatches = $redirections->where('match_type', 'regex');
        foreach ($regexMatches as $redirection) {
            // For regex, check if pattern contains \? (escaped question mark for query string)
            $sourceHasQuery = str_contains((string) $redirection->source_url, '\\?') || str_contains((string) $redirection->source_url, '?');
            $pathToMatch = $sourceHasQuery ? $currentPathWithQuery : $currentPath;

            $matches = $redirection->matchesPath($pathToMatch);
            if ($matches !== false) {
                return [$redirection, is_array($matches) ? $matches : []];
            }
        }

        return null;
    }

    /**
     * Get a valid HTTP redirect status code.
     */
    protected function getValidStatusCode(int $code): int
    {
        // Only accept valid redirect status codes
        $validCodes = [301, 302, 307, 308];

        return in_array($code, $validCodes) ? $code : 301;
    }

    /**
     * Record a hit for the redirect.
     */
    protected function recordHit(Redirection $redirection): void
    {
        try {
            event(new RedirectionHit($redirection));
        } catch (Exception) {
            // Silently fail if event cannot be fired
        }
    }

    private function isAdminRequest(Request $request): bool
    {
        $adminSlug = trim((string) config('app.admin_slug'), '/');
        $path = trim($request->path(), '/');

        if ($adminSlug === '') {
            return false;
        }

        return $path === $adminSlug || str_starts_with($path, $adminSlug.'/');
    }
}
