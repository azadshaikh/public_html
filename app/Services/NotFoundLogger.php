<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotFoundLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * NotFoundLogger
 *
 * Service for recording 404 errors with rate limiting to prevent log flooding.
 */
class NotFoundLogger
{
    /**
     * Default rate limit: max logs per IP per minute
     */
    protected const DEFAULT_RATE_LIMIT_PER_MINUTE = 10;

    /**
     * Paths to ignore (assets, common false positives)
     */
    protected const IGNORED_PATHS = [
        '/favicon.ico',
        '/robots.txt',
        '/sitemap.xml',
        '/apple-touch-icon',
        '/browserconfig.xml',
        '/manifest.json',
        '/.well-known/assetlinks.json',
        '/ads.txt',
        '/sw.js',
        '/service-worker.js',
    ];

    /**
     * File extensions to ignore (static assets)
     */
    protected const IGNORED_EXTENSIONS = [
        '.map',
        '.woff',
        '.woff2',
        '.ttf',
        '.eot',
        '.otf',
    ];

    /**
     * Log a 404 error from the current request.
     */
    public function log(Request $request): ?NotFoundLog
    {
        $path = $request->path();
        $ipAddress = $request->ip();

        // Check if path should be ignored
        if ($this->shouldIgnore($path)) {
            return null;
        }

        // Rate limit logging per IP to prevent log flooding attacks
        $rateLimitKey = 'not_found_log:'.$ipAddress;
        if (! $this->checkRateLimit($rateLimitKey)) {
            return null;
        }

        // Record the 404
        return NotFoundLog::record(
            url: '/'.$path,
            ipAddress: $ipAddress,
            fullUrl: $request->fullUrl(),
            referer: $request->header('referer'),
            userAgent: $request->userAgent(),
            userId: Auth::id(),
            method: $request->method(),
            metadata: $this->buildMetadata($request)
        );
    }

    /**
     * Log a 404 with custom parameters (for testing or manual logging).
     */
    public function logCustom(
        string $url,
        string $ipAddress,
        ?string $fullUrl = null,
        ?string $referer = null,
        ?string $userAgent = null,
        ?int $userId = null,
        string $method = 'GET',
        array $metadata = []
    ): NotFoundLog {
        return NotFoundLog::record(
            url: $url,
            ipAddress: $ipAddress,
            fullUrl: $fullUrl,
            referer: $referer,
            userAgent: $userAgent,
            userId: $userId,
            method: $method,
            metadata: $metadata
        );
    }

    /**
     * Check if the path should be ignored.
     */
    protected function shouldIgnore(string $path): bool
    {
        $pathWithSlash = '/'.ltrim($path, '/');
        $pathLower = strtolower($pathWithSlash);

        // Check ignored paths
        foreach (self::IGNORED_PATHS as $ignored) {
            if (str_starts_with($pathLower, strtolower($ignored))) {
                return true;
            }
        }

        // Check ignored extensions
        foreach (self::IGNORED_EXTENSIONS as $ext) {
            if (str_ends_with($pathLower, $ext)) {
                return true;
            }
        }

        // Ignore build/asset paths
        if (preg_match('#^/(build|assets|vendor|storage|_debugbar)/#i', $pathWithSlash)) {
            return true;
        }

        // Ignore hot module replacement requests
        return str_contains($path, '@vite') || str_contains($path, '__vite');
    }

    /**
     * Check rate limit and increment counter.
     */
    protected function checkRateLimit(string $key): bool
    {
        $maxAttempts = (int) config('astero.not_found_logs.rate_limit_per_minute', self::DEFAULT_RATE_LIMIT_PER_MINUTE);

        return RateLimiter::attempt(
            $key,
            max($maxAttempts, 1),
            fn (): true => true,
            60 // 1 minute decay
        );
    }

    /**
     * Build metadata array with additional context.
     */
    protected function buildMetadata(Request $request): array
    {
        $metadata = [];

        // Add query string if present
        if ($request->getQueryString()) {
            $metadata['query_string'] = $request->getQueryString();
        }

        // Add accept header (helps identify API vs browser requests)
        if ($accept = $request->header('accept')) {
            $metadata['accept'] = substr($accept, 0, 100);
        }

        // Add content type for POST requests
        if ($request->isMethod('POST') && $contentType = $request->header('content-type')) {
            $metadata['content_type'] = $contentType;
        }

        // Check if it's an AJAX request
        if ($request->ajax()) {
            $metadata['is_ajax'] = true;
        }

        return $metadata;
    }
}
