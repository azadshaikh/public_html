<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureReleaseApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredReleaseApiKey = $this->resolveConfiguredReleaseApiKey();
        $providedReleaseApiKey = (string) $request->header('X-Release-Key', '');

        if ($configuredReleaseApiKey === '' || ! hash_equals($configuredReleaseApiKey, $providedReleaseApiKey)) {
            $this->logUnauthorizedAttempt($request, $configuredReleaseApiKey, $providedReleaseApiKey);

            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }

    protected function logUnauthorizedAttempt(Request $request, string $configuredReleaseApiKey, string $providedReleaseApiKey): void
    {
        $configuredTrimmed = trim($configuredReleaseApiKey);
        $providedTrimmed = trim($providedReleaseApiKey);
        $failureReason = $configuredReleaseApiKey === ''
            ? 'configured_key_missing'
            : ($providedReleaseApiKey === '' ? 'header_missing' : 'key_mismatch');

        if (
            $configuredReleaseApiKey !== ''
            && $providedReleaseApiKey !== ''
            && hash_equals($configuredTrimmed, $providedTrimmed)
            && ! hash_equals($configuredReleaseApiKey, $providedReleaseApiKey)
        ) {
            $failureReason = 'key_whitespace_mismatch';
        }

        Log::warning('Release API unauthorized request', [
            'path' => '/'.$request->path(),
            'method' => $request->method(),
            'host' => $request->getHost(),
            'ip' => $request->ip(),
            'user_agent' => (string) ($request->userAgent() ?? ''),
            'failure_reason' => $failureReason,
            'configured_key_present' => $configuredReleaseApiKey !== '',
            'configured_key_length' => strlen($configuredReleaseApiKey),
            'configured_key_hash' => $configuredReleaseApiKey !== '' ? hash('sha256', $configuredReleaseApiKey) : null,
            'provided_key_present' => $providedReleaseApiKey !== '',
            'provided_key_length' => strlen($providedReleaseApiKey),
            'provided_key_trimmed_length' => strlen($providedTrimmed),
            'provided_key_hash' => $providedReleaseApiKey !== '' ? hash('sha256', $providedReleaseApiKey) : null,
            'provided_key_trimmed_hash' => $providedTrimmed !== '' ? hash('sha256', $providedTrimmed) : null,
            'request_id' => (string) ($request->header('X-Request-Id') ?? ''),
            'cf_ray' => (string) ($request->header('CF-Ray') ?? ''),
        ]);
    }

    protected function resolveConfiguredReleaseApiKey(): string
    {
        $fromReleaseManagerConfig = trim((string) config('releasemanager.api.release_key', ''));
        if ($fromReleaseManagerConfig !== '') {
            return $fromReleaseManagerConfig;
        }

        $fromPlatformConfig = trim((string) config('platform.release_api_key', ''));
        if ($fromPlatformConfig !== '') {
            return $fromPlatformConfig;
        }

        $fromProcessEnv = trim((string) (getenv('RELEASE_API_KEY') ?: ''));
        if ($fromProcessEnv !== '') {
            return $fromProcessEnv;
        }

        return $this->readEnvValueFromDotEnv('RELEASE_API_KEY');
    }

    protected function readEnvValueFromDotEnv(string $key): string
    {
        $envPath = base_path('.env');
        if (! is_readable($envPath)) {
            return '';
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return '';
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            if (! str_contains($trimmed, '=')) {
                continue;
            }

            [$lineKey, $lineValue] = explode('=', $trimmed, 2);
            if (trim($lineKey) !== $key) {
                continue;
            }

            $value = trim($lineValue);
            if ($value === '') {
                return '';
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                return trim(substr($value, 1, -1));
            }

            return trim((string) preg_replace('/\s+#.*$/', '', $value));
        }

        return '';
    }
}
