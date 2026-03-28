<?php

namespace Modules\Platform\Services\Concerns;

use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

trait InteractsWithWebsiteSyncPayload
{
    /**
     * @return array{success: bool, payload: array|null, error: string|null}
     */
    protected function fetchWebsiteInfoPayload(Website $website): array
    {
        $response = HestiaClient::execute(
            'a-get-website-info',
            $website->server,
            [$website->website_username, $website->domain, 'json']
        );

        if (! ($response['success'] ?? false)) {
            $response = HestiaClient::execute(
                'a-get-website-info',
                $website->server,
                [$website->domain, 'json']
            );
        }

        if (! ($response['success'] ?? false)) {
            return [
                'success' => false,
                'payload' => null,
                'error' => $response['message'] ?? 'Failed to fetch website info from server.',
            ];
        }

        $payload = $this->unwrapHestiaResponseData($response['data'] ?? []);
        $payload = $this->normalizeWebsiteInfoPayload($payload);

        if (isset($payload['raw']) && is_string($payload['raw'])) {
            $payload = array_merge($payload, $this->parseRawWebsiteInfoPayload($payload['raw']));
        }

        return [
            'success' => true,
            'payload' => $payload,
            'error' => null,
        ];
    }

    protected function unwrapHestiaResponseData(array $data): array
    {
        if (isset($data['status']) && $data['status'] === 'success' && isset($data['data'])) {
            return is_array($data['data']) ? $data['data'] : [];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return $data;
    }

    protected function normalizeWebsiteInfoPayload(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }

        if (isset($payload['app']) && is_array($payload['app'])) {
            $payload = array_merge($payload, $payload['app']);
        }

        if (isset($payload['versions']) && is_array($payload['versions'])) {
            return array_merge($payload, $payload['versions']);
        }

        return $payload;
    }

    protected function parseRawWebsiteInfoPayload(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decodedJson = json_decode($raw, true);
        if (is_array($decodedJson)) {
            return $decodedJson;
        }

        $jsonLikeParsed = $this->parseJsonLikeWebsiteInfoPayload($raw);
        if ($jsonLikeParsed !== []) {
            return $jsonLikeParsed;
        }

        $lines = preg_split('/\r?\n/', $raw) ?: [];
        $parsed = [];

        foreach ($lines as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$label, $value] = array_map(trim(...), explode(':', $line, 2));
            if ($value === '' || $value === 'N/A') {
                continue;
            }

            $key = strtolower(str_replace([' ', '-'], '_', $label));

            if ($key === 'queue_workers_running' && preg_match('/^(\d+)\s*\/\s*(\d+)$/', $value, $matches) === 1) {
                $parsed['queue_worker_running_count'] = (int) $matches[1];
                $parsed['queue_worker_total_count'] = (int) $matches[2];

                continue;
            }

            $mappedKey = match ($key) {
                'app_name' => 'app_name',
                'astero_version' => 'astero_version',
                'laravel_version' => 'laravel_version',
                'php_version' => 'php_version',
                'app_environment' => 'app_env',
                'app_debug' => 'app_debug',
                'admin_slug' => 'admin_slug',
                'admin_login_url_slug' => 'admin_slug',
                'queue_worker_status' => 'queue_worker_status',
                'cron_status' => 'cron_status',
                default => null,
            };

            if ($mappedKey !== null) {
                $parsed[$mappedKey] = $value;
            }
        }

        return $parsed;
    }

    protected function parseJsonLikeWebsiteInfoPayload(string $raw): array
    {
        $normalized = preg_replace('/\s+/', ' ', $raw) ?? $raw;
        $parsed = [];

        $stringKeyMap = [
            'app_name' => 'app_name',
            'astero_version' => 'astero_version',
            'current_release' => 'current_release',
            'laravel_version' => 'laravel_version',
            'php_version' => 'php_version',
            'app_env' => 'app_env',
            'app_environment' => 'app_env',
            'app_debug' => 'app_debug',
            'admin_slug' => 'admin_slug',
            'ADMIN_SLUG' => 'admin_slug',
            'admin_login_url_slug' => 'admin_slug',
            'queue_worker_status' => 'queue_worker_status',
            'cron_status' => 'cron_status',
        ];

        foreach ($stringKeyMap as $sourceKey => $targetKey) {
            if (preg_match('/"'.preg_quote($sourceKey, '/').'"\s*:\s*"([^"]*)"/i', $normalized, $matches) === 1) {
                $value = trim($matches[1]);
                if ($value !== '') {
                    $parsed[$targetKey] = $value;
                }
            }
        }

        if (preg_match('/"queue_worker"\s*:\s*\{[^}]*"status"\s*:\s*"([^"]*)"/i', $normalized, $matches) === 1) {
            $status = trim($matches[1]);
            if ($status !== '') {
                $parsed['queue_worker_status'] = $status;
            }
        }

        if (preg_match('/"queue_worker"\s*:\s*\{[^}]*"running_count"\s*:\s*([0-9]+)/i', $normalized, $matches) === 1) {
            $parsed['queue_worker_running_count'] = (int) $matches[1];
        }

        if (preg_match('/"queue_worker"\s*:\s*\{[^}]*"total_count"\s*:\s*([0-9]+)/i', $normalized, $matches) === 1) {
            $parsed['queue_worker_total_count'] = (int) $matches[1];
        }

        if (preg_match('/"cron"\s*:\s*\{[^}]*"status"\s*:\s*"([^"]*)"/i', $normalized, $matches) === 1) {
            $status = trim($matches[1]);
            if ($status !== '') {
                $parsed['cron_status'] = $status;
            }
        }

        if (preg_match('/"disk_usage_bytes"\s*:\s*([0-9]+)/i', $normalized, $matches) === 1) {
            $parsed['disk_usage_bytes'] = (int) $matches[1];
        }

        return $parsed;
    }

    protected function pickPayloadValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $payload[$key];
            }
        }

        return null;
    }

    protected function normalizeDebugValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', 'false'], true)) {
                return $normalized === 'true';
            }
        }

        return $value;
    }

    protected function normalizeQueueWorkerStatus(mixed $status, int $runningCount = 0, int $totalCount = 0): ?string
    {
        if ($status === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $status));
        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace([' ', '-'], '_', $normalized);

        if (preg_match('/^(running|degraded|starting|stopped|not_running|not_configured|not_installed|error|unknown|exited)(?:_|$)/', $normalized, $matches) === 1) {
            $normalized = $matches[1];
        }

        if ($normalized === 'exited') {
            $normalized = 'not_running';
        }

        if ($normalized === 'unknown') {
            if ($totalCount > 0 && $runningCount === $totalCount) {
                return 'running';
            }

            if ($runningCount > 0 && $totalCount > $runningCount) {
                return 'degraded';
            }
        }

        return $normalized;
    }

    protected function normalizeVersion(mixed $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $normalized = trim((string) $version);

        if ($normalized === '') {
            return null;
        }

        return ltrim($normalized, 'vV');
    }
}
