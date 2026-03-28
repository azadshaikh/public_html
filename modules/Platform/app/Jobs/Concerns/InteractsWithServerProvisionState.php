<?php

namespace Modules\Platform\Jobs\Concerns;

use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\Server;
use RuntimeException;
use Throwable;

trait InteractsWithServerProvisionState
{
    /**
     * Initialize provisioning steps in metadata (preserves existing step status).
     */
    protected function initSteps(Server $server): void
    {
        $existingSteps = $server->getMetadata('provisioning_steps') ?? [];

        foreach (self::STEPS as $key => $label) {
            if (! isset($existingSteps[$key])) {
                $existingSteps[$key] = [
                    'label' => $label,
                    'status' => 'pending',
                    'started_at' => null,
                    'completed_at' => null,
                    'data' => null,
                ];
            }
        }

        $server->setMetadata('provisioning_steps', $existingSteps);

        if (! $server->getMetadata('provisioning_started_at')) {
            $server->setMetadata('provisioning_started_at', now()->toISOString());
        }

        $server->save();
    }

    /**
     * Update a provisioning step status.
     */
    protected function updateStep(Server $server, string $step, string $status, ?array $data = null): void
    {
        $steps = $server->getMetadata('provisioning_steps') ?? [];

        if (! isset($steps[$step])) {
            return;
        }

        $steps[$step]['status'] = $status;

        if ($status === 'running') {
            if (empty($steps[$step]['started_at'])) {
                $steps[$step]['started_at'] = now()->toISOString();
            }
        } elseif (in_array($status, ['completed', 'failed', 'skipped'], true)) {
            $steps[$step]['completed_at'] = now()->toISOString();
        }

        if ($data !== null) {
            $steps[$step]['data'] = $data;
        }

        $server->setMetadata('provisioning_steps', $steps);
        $server->save();

        $this->debugStepTrace($server, $step, $status, $data);
    }

    /**
     * Check if a step is already completed or skipped.
     */
    protected function isStepDone(Server $server, string $step): bool
    {
        $steps = $server->getMetadata('provisioning_steps') ?? [];
        $status = $steps[$step]['status'] ?? 'pending';

        return in_array($status, ['completed', 'skipped'], true);
    }

    /**
     * Detect retryable errors where releasing the job is safer than failing.
     */
    protected function shouldReleaseForRetry(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'Failed to update releases:')) {
            return false;
        }

        $transientMarkers = [
            self::RETRYABLE_HESTIA_INSTALL_MARKER,
            'timed out',
            'Connection failed',
            'Host is unreachable',
            'Broken pipe',
            'Connection reset',
            'Could not resolve host',
        ];

        foreach ($transientMarkers as $marker) {
            if (stripos($message, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function isProvisioningStopException(Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), self::STOP_REQUESTED_MARKER);
    }

    /**
     * Resolve retry delay for the current attempt.
     */
    protected function backoffSeconds(): int
    {
        $index = max(0, $this->attempts() - 1);
        $backoff = $this->backoff();

        return $backoff[$index] ?? end($backoff);
    }

    /**
     * Persist retry metadata for visibility in the UI/debugging.
     */
    protected function recordRetryMetadata(Server $server, string $errorMessage, int $delay): void
    {
        $server->setMetadata('provisioning_retry', [
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
            'last_error' => $errorMessage,
            'next_retry_in_seconds' => $delay,
            'updated_at' => now()->toISOString(),
        ]);
        $server->save();
    }

    protected function abortIfProvisioningStopRequested(Server $server): void
    {
        $server->refresh();

        if (! (bool) $server->getMetadata('provisioning_control.stop_requested', false)) {
            return;
        }

        throw new RuntimeException(self::STOP_REQUESTED_MARKER.': provisioning stop requested by user');
    }

    /**
     * Mark the current running step as failed with error message.
     */
    protected function markCurrentStepAsFailed(Server $server, string $errorMessage): void
    {
        $steps = $server->getMetadata('provisioning_steps') ?? [];
        $sanitizedError = $this->sanitizeSensitiveMessage($errorMessage);

        foreach ($steps as &$stepData) {
            if (($stepData['status'] ?? '') === 'running') {
                $stepData['status'] = 'failed';
                $stepData['completed_at'] = now()->toISOString();
                $stepData['data'] = ['error' => $sanitizedError];
                break;
            }
        }

        unset($stepData);

        $server->setMetadata('provisioning_steps', $steps);
        $server->save();
    }

    protected function debugStepTrace(Server $server, string $step, string $status, ?array $data = null): void
    {
        if (! config('app.debug')) {
            return;
        }

        $payload = $data;
        if (is_array($payload)) {
            foreach (['log_tail', 'output_tail', 'os_info'] as $field) {
                if (isset($payload[$field]) && is_string($payload[$field])) {
                    $payload[$field] = trim($this->sanitizeSensitiveMessage(substr($payload[$field], -1000)));
                }
            }
        }

        Log::info('ServerProvision: step state updated', [
            'server_id' => $server->id,
            'step' => $step,
            'status' => $status,
            'data' => $payload,
        ]);
    }

    protected function resolveApiAllowedIp(Server $server): string
    {
        if ($server->isLocalhostType()) {
            return 'allow-all';
        }

        $provisionerIps = $this->normalizeProvisionerIps(config('platform.provisioner_ips', []));
        if ($provisionerIps === []) {
            $autoDetectedProvisionerIp = trim($this->resolveProvisionerIp($server) ?? '');
            $provisionerIps = $this->normalizeProvisionerIps($autoDetectedProvisionerIp);
        }

        throw_if($provisionerIps === [], RuntimeException::class, 'Unable to resolve provisioner IP for API allowlist. Set PROVISIONER_IPS.');

        return implode(PHP_EOL, $provisionerIps);
    }

    protected function resolveProvisionerIp(Server $server): ?string
    {
        $targetIp = trim((string) ($server->ip ?? ''));
        if ($targetIp !== '' && filter_var($targetIp, FILTER_VALIDATE_IP) && function_exists('socket_create')) {
            $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket !== false) {
                try {
                    @socket_connect($socket, $targetIp, 53);
                    $localIp = null;
                    $localPort = null;
                    if (@socket_getsockname($socket, $localIp, $localPort) && filter_var($localIp, FILTER_VALIDATE_IP)) {
                        return $localIp;
                    }
                } finally {
                    @socket_close($socket);
                }
            }
        }

        $hostnameIp = @gethostbyname(gethostname());
        if (filter_var($hostnameIp, FILTER_VALIDATE_IP) && ! str_starts_with($hostnameIp, '127.')) {
            return $hostnameIp;
        }

        return null;
    }

    protected function sanitizeSensitiveMessage(string $message): string
    {
        $sanitized = preg_replace('/(SECRET_ACCESS_KEY|ACCESS_KEY_ID|X-Release-Key|release[_\s-]*api[_\s-]*key|password|secret|private[_\s-]*key)\s*[:=]\s*([^\s,;]+)/i', '$1=[REDACTED]', $message);
        $sanitized = preg_replace('/-----BEGIN [A-Z ]+-----.*?-----END [A-Z ]+-----/s', '[REDACTED_KEY]', (string) $sanitized);

        return (string) $sanitized;
    }

    protected function normalizeProvisionerIps(array|string|null $candidateIps): array
    {
        $ips = is_array($candidateIps) ? $candidateIps : explode(',', (string) $candidateIps);

        $normalized = [];
        $invalid = [];

        foreach ($ips as $ip) {
            $trimmedIp = trim((string) $ip);
            if ($trimmedIp === '') {
                continue;
            }

            if (! filter_var($trimmedIp, FILTER_VALIDATE_IP)) {
                $invalid[] = $trimmedIp;

                continue;
            }

            $normalized[] = $trimmedIp;
        }

        if ($invalid !== []) {
            throw new RuntimeException('Invalid IP value(s) in PROVISIONER_IPS: '.implode(', ', $invalid));
        }

        return array_values(array_unique($normalized));
    }
}
