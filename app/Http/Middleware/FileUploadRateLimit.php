<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class FileUploadRateLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to POST requests with files
        if (! $request->isMethod('POST') || ! $request->hasFile('theme_zip')) {
            return $next($request);
        }

        $key = 'file_upload_'.$request->ip();
        $maxAttempts = 5; // Max 5 uploads per hour
        $decayMinutes = 60;

        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            return response()->json([
                'error' => 'Too many file upload attempts. Please try again later.',
            ], 429);
        }

        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        $response = $next($request);

        // Reset counter on successful upload
        if ($response->getStatusCode() === 200) {
            Cache::forget($key);
        }

        return $response;
    }
}
