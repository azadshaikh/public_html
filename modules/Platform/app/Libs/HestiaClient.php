<?php

namespace Modules\Platform\Libs;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\Server;

/**
 * Exception thrown for HestiaCP API errors.
 */
class HestiaApiException extends Exception {}

/**
 * Unified HestiaCP API Client.
 *
 * This class consolidates all Hestia API interactions into a single, consistent implementation.
 * It handles authentication, API calls, response mapping, and error handling.
 *
 * ## API Architecture
 *
 * All commands (both native Hestia `v-*` and custom Astero `a-*`) are executed through
 * the `a-exec` wrapper script. This provides:
 * - Consistent structured JSON responses
 * - Unified error handling with human-readable messages
 * - Integrated logging (errors always logged, debug based on LARAVEL_DEBUG)
 *
 * ### Response Format
 * ```json
 * {
 *   "status": "success|error",
 *   "code": 0,
 *   "message": "Human-readable message",
 *   "data": { ... }
 * }
 * ```
 *
 * ### Hestia API returncode Parameter
 * - `returncode=yes`: Returns only numeric exit code (0=success, 1-17=standard errors)
 * - `returncode=no`: Returns command output (JSON for list commands, "OK" for success)
 *
 * We use `returncode=no` with `a-exec` wrapper to get structured responses.
 *
 * ## Usage Examples
 *
 * ```php
 * // Using Server model (preferred)
 * $response = HestiaClient::execute('v-list-users', $server, ['admin', 'json']);
 * if ($response['success']) {
 *     $users = $response['data'];
 * }
 *
 * // Using credentials array
 * $response = HestiaClient::execute('v-list-users', [
 *     'host' => '192.168.1.1:8443',
 *     'access_key' => 'xxx',
 *     'secret_key' => 'yyy',
 * ], ['admin', 'json']);
 *
 * // With custom timeout for long operations
 * $response = HestiaClient::execute('a-update-astero', $server, [$domain], 120);
 * ```
 */
class HestiaClient
{
    /**
     * Default port for HestiaCP API.
     */
    public const DEFAULT_PORT = 8443;

    /**
     * Default timeout in seconds.
     */
    public const DEFAULT_TIMEOUT = 120;

    /**
     * Default connection timeout in seconds.
     */
    public const DEFAULT_CONNECT_TIMEOUT = 10;

    /**
     * Dedicated log channel for Hestia API debug traces.
     */
    public const DEBUG_LOG_CHANNEL = 'hestia_api';

    /**
     * Execute a HestiaCP command.
     *
     * @param  string  $command  The HestiaCP command to execute (e.g., 'v-list-users', 'a-sync-releases')
     * @param  Server|array  $server  Server model or credentials array with keys: host, access_key, secret_key
     * @param  array  $args  Arguments to pass to the command (e.g., ['username', 'json'] or ['arg1' => 'val'])
     * @param  int  $timeout  Request timeout in seconds
     * @return array Response: ['success' => bool, 'message' => string, 'data' => array, 'code' => int]
     */
    public static function execute(string $command, Server|array $server, array $args = [], int $timeout = 60): array
    {
        try {
            $credentials = self::resolveCredentials($server);
            $url = self::buildUrl($credentials['host']);
            $verifyTls = self::shouldVerifyTls($server, $credentials['host']);

            // Determine debug mode from Laravel's APP_DEBUG setting
            $debugMode = config('app.debug', false) ? '1' : '0';

            // Normalize args - support both ['arg1' => 'val'] and ['val1', 'val2'] formats
            // Build request parameters for a-exec wrapper
            // a-exec wraps any command and returns structured JSON response
            // Use 'hash' format per official Hestia API documentation: "access_code:secret_code"
            $params = [
                'hash' => $credentials['access_key'].':'.$credentials['secret_key'],
                'returncode' => 'no',
                'cmd' => 'a-exec',
                'arg1' => '--debug='.$debugMode,  // Pass debug flag to a-exec
                'arg2' => $command,
            ];

            // Add command arguments (shifted by 2 since arg1 is debug flag, arg2 is command)
            $argIndex = 3;
            foreach ($args as $arg) {
                $params['arg'.$argIndex] = $arg;
                $argIndex++;
            }

            return self::makeRequest($url, $params, $timeout, $verifyTls);
        } catch (HestiaApiException $e) {
            return self::errorResponse($e->getMessage(), $e->getCode() ?: 1);
        } catch (Exception $e) {
            return self::errorResponse('HestiaCP API request failed: '.$e->getMessage());
        }
    }

    /**
     * Build the API URL for a Hestia server.
     *
     * @param  Server  $server  Server model
     * @return string|null The API URL or null if server lacks required information
     */
    public static function buildApiUrl(Server $server): ?string
    {
        $host = $server->fqdn ?: $server->ip;

        if (empty($host)) {
            return null;
        }

        $port = $server->port ?: self::DEFAULT_PORT;

        return sprintf('https://%s:%s/api/', $host, $port);
    }

    /**
     * Get human-readable message for a response code.
     */
    public static function getResponseMessage(string $code): string
    {
        $map = self::getResponseCodeMap();

        return $map[$code] ?? 'Unknown response code: '.$code;
    }

    /**
     * Get the HestiaCP response code map.
     *
     * Contains standard Hestia error codes (0-17) and custom Astero codes (200+).
     */
    public static function getResponseCodeMap(): array
    {
        return [
            // Standard HestiaCP response codes
            '0' => 'Command has been successfully performed',
            '1' => 'Not enough arguments provided',
            '2' => 'Object or argument is not valid',
            '3' => "Object doesn't exist",
            '4' => 'Object already exists',
            '5' => 'Object is already suspended',
            '6' => 'Object is already unsuspended',
            '7' => "Object can't be deleted because it is used by another object",
            '8' => 'Object cannot be created because of hosting package limits',
            '9' => 'Wrong / Invalid password',
            '10' => 'Object cannot be accessed by this user',
            '11' => 'Subsystem is disabled',
            '12' => 'Configuration is broken',
            '13' => 'Not enough disk space to complete the action',
            '14' => 'Server is too busy to complete the action',
            '15' => 'Connection failed. Host is unreachable',
            '16' => 'FTP server is not responding',
            '17' => 'Database server is not responding',

            // Custom Astero codes (200+) for platform-specific helper scripts
            '200' => 'Prerequisite check failed (system or SSL support disabled)',
            '201' => 'Object validation failed (user/domain invalid or suspended)',
            '202' => 'Certificate generation failed',
            '203' => 'Failed to apply or enforce SSL certificate',
            '204' => 'Releases directory not found',
            '205' => 'No application release versions found',
            '206' => 'File/directory operation failed (create/write/symlink)',
            '207' => 'Required dependency missing (curl, jq)',
            '208' => 'API connection to deployment site failed',
            '209' => 'Failed to parse deployment API response',
            '210' => 'Directory operation failed (create/chdir)',
            '211' => 'Application file download failed from deployment',
            '212' => 'Application archive extraction failed',
            '213' => 'File/directory ownership or move failed',
            '214' => 'Invalid or incomplete JSON data',
            '215' => 'Composer install failed',
            '216' => 'Artisan install/update failed',
            '217' => 'Invalid application type',
            '218' => 'Target version not found',
            '219' => 'Generic revert operation failure',
        ];
    }

    // =========================================================================
    // PROTECTED METHODS
    // =========================================================================

    /**
     * Normalize arguments to arg1, arg2, arg3... format.
     *
     * Supports both formats:
     * - ['arg1' => 'val1', 'arg2' => 'val2']
     * - ['val1', 'val2']
     */
    protected static function normalizeArgs(array $args): array
    {
        $normalized = [];
        $index = 1;

        foreach ($args as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'arg')) {
                // Already in arg1, arg2 format
                $normalized[$key] = $value;
            } else {
                // Convert to arg1, arg2 format
                $normalized['arg'.$index] = $value;
                $index++;
            }
        }

        return $normalized;
    }

    /**
     * Resolve credentials from either a Server model or credentials array.
     *
     * @throws HestiaApiException When credentials are invalid or missing
     */
    protected static function resolveCredentials(Server|array $server): array
    {
        if ($server instanceof Server) {
            $host = $server->fqdn ?: $server->ip;

            throw_if(empty($host), HestiaApiException::class, 'Server host (IP or FQDN) is required.');

            $port = $server->port ?: self::DEFAULT_PORT;

            return [
                'host' => sprintf('%s:%s', $host, $port),
                'access_key' => $server->access_key_id,
                'secret_key' => $server->access_key_secret,
            ];
        }

        // Array credentials
        throw_if(empty($server['host']), HestiaApiException::class, 'Server host is required.');

        throw_if(empty($server['access_key']) || empty($server['secret_key']), HestiaApiException::class, 'Invalid server credentials. Access Key and Secret Key are required.');

        return $server;
    }

    /**
     * Build the API URL from host string.
     */
    protected static function buildUrl(string $host): string
    {
        return sprintf('https://%s/api/', $host);
    }

    protected static function shouldVerifyTls(Server|array $server, ?string $hostWithPort = null): bool
    {
        if ($server instanceof Server) {
            return ! $server->isLocalhostType();
        }

        if (($server['insecure'] ?? false) === true) {
            return false;
        }

        $host = $hostWithPort ?? ($server['host'] ?? '');
        if ($host === '') {
            return true;
        }

        $host = explode(':', (string) $host)[0];

        return ! in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Make the HTTP request to the HestiaCP API.
     */
    protected static function makeRequest(string $url, array $params, int $timeout, bool $verifyTls): array
    {
        $correlationId = uniqid('hestia_', true);

        try {
            $response = Http::withOptions([
                'verify' => $verifyTls,
                'timeout' => $timeout,
                'connect_timeout' => self::DEFAULT_CONNECT_TIMEOUT,
            ])
                ->asForm()
                ->post($url, $params);

            $responseBody = $response->body();

            // Log request details (with credentials masked)
            self::logRequest($url, $params, $responseBody, $correlationId, $verifyTls);

            // Check HTTP-level failure
            if (! $response->successful()) {
                return self::errorResponse(
                    'Hestia API request failed: HTTP '.$response->status(),
                    $response->status()
                );
            }

            // Parse response
            return self::parseResponse($responseBody);
        } catch (HestiaApiException $e) {
            throw $e;
        } catch (Exception $e) {
            self::logError($url, $params, $e, $correlationId);

            return self::errorResponse('HestiaCP API request failed: '.$e->getMessage());
        }
    }

    /**
     * Parse the API response from a-exec wrapper.
     *
     * a-exec returns consistent JSON format:
     * - status: "success" or "error"
     * - code: numeric exit code
     * - message: human-readable message
     * - data.output: parsed JSON (if command returned JSON)
     * - data.raw: plain text (if command returned non-JSON)
     */
    protected static function parseResponse(string $responseBody): array
    {
        $trimmedResponse = trim($responseBody);
        $lowerResponse = strtolower($trimmedResponse);

        // Simple success response
        if ($lowerResponse === 'ok') {
            return self::successResponse('Command executed successfully');
        }

        // Try to parse as JSON
        $jsonData = json_decode($responseBody, true);

        if ($jsonData !== null) {
            // Check for status field in JSON response (common pattern for custom Astero scripts)
            if (isset($jsonData['status'])) {
                if ($jsonData['status'] === 'success') {
                    // Extract actual data from data.output or data.raw (a-exec format)
                    $data = $jsonData['data'] ?? [];

                    // If data has 'output' key, use that as the actual data
                    if (isset($data['output'])) {
                        $data = $data['output'];
                    }

                    // If data has 'raw' key, keep it wrapped for caller to handle

                    return self::successResponse(
                        $jsonData['message'] ?? 'Command executed successfully',
                        $data
                    );
                }

                if ($jsonData['status'] === 'error') {
                    return self::errorResponse(
                        $jsonData['message'] ?? 'Command failed',
                        $jsonData['code'] ?? 1
                    );
                }

                if ($jsonData['status'] === 'info') {
                    return self::successResponse(
                        $jsonData['message'] ?? 'Command completed',
                        $jsonData['data'] ?? []
                    );
                }
            }

            // Plain JSON data response (e.g., from v-list-users)
            return self::successResponse('Command executed', $jsonData);
        }

        // Check if it's a known numeric error code
        if (is_numeric($trimmedResponse)) {
            $code = (int) $trimmedResponse;

            if ($code === 0) {
                return self::successResponse('Command executed successfully');
            }

            return self::errorResponse(self::getResponseMessage($trimmedResponse), $code);
        }

        // Plain text response - could be success data or error
        // Return as raw data for caller to interpret
        return self::successResponse('Command executed', ['raw' => $trimmedResponse]);
    }

    /**
     * Log API request details for debugging.
     */
    protected static function logRequest(string $url, array $params, string $response, string $correlationId, bool $verifyTls = true): void
    {
        if (! self::shouldLogDebugRequests()) {
            return;
        }

        // Mask sensitive credentials (hash contains access_key:secret_key)
        $logParams = self::maskSensitiveParams($params);

        // Build command string for easy debugging
        $commandString = self::buildCommandString($logParams);
        $commandName = $params['arg2'] ?? $params['cmd'] ?? 'unknown';
        $commandArg3 = isset($params['arg3']) ? ' - '.$params['arg3'] : '';

        $context = [
            'url' => $url,
            'request' => $logParams,
            'command' => $commandString,
            'response' => self::maskSensitiveResponse($response),
            'response_length' => strlen($response),
            'verify_tls' => $verifyTls,
            'correlation_id' => $correlationId,
            'timestamp' => now()->toISOString(),
        ];

        try {
            Log::channel(self::DEBUG_LOG_CHANNEL)->debug(sprintf('HestiaCP API Call - %s%s', $commandName, $commandArg3), $context);
        } catch (Exception) {
            Log::debug(sprintf('HestiaCP API Call - %s%s', $commandName, $commandArg3), $context);
        }
    }

    /**
     * Log API error for debugging.
     */
    protected static function logError(string $url, array $params, Exception $e, string $correlationId): void
    {
        // Mask sensitive credentials (hash, --env: args)
        $logParams = self::maskSensitiveParams($params);

        Log::error('HestiaCP API Exception', [
            'url' => $url,
            'request' => $logParams,
            'command' => self::buildCommandString($logParams),
            'exception' => $e->getMessage(),
            'exception_type' => $e::class,
            'correlation_id' => $correlationId,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Mask sensitive values in request params before logging.
     *
     * Redacts: hash (access_key:secret_key), --env:KEY=VALUE args.
     */
    protected static function maskSensitiveParams(array $params): array
    {
        if (isset($params['hash'])) {
            $params['hash'] = '********:********';
        }

        // Mask --env:KEY=VALUE args (e.g. --env:BUNNY_API_KEY=secret)
        foreach ($params as $key => $value) {
            if (is_string($value) && str_starts_with($value, '--env:')) {
                $eqPos = strpos($value, '=');
                if ($eqPos !== false) {
                    $params[$key] = substr($value, 0, $eqPos + 1).'[REDACTED]';
                }
            }
        }

        return $params;
    }

    protected static function maskSensitiveResponse(string $response): string
    {
        $sanitized = preg_replace('/("?(?:secret|password|private_key|access_key|secret_access_key)"?\s*:\s*")[^"]*(")/i', '$1[REDACTED]$2', $response);
        $sanitized = preg_replace('/(SECRET_ACCESS_KEY[:=]\s*)[^\s"]+/i', '$1[REDACTED]', (string) $sanitized);
        $sanitized = preg_replace('/(ACCESS_KEY_ID[:=]\s*)[^\s"]+/i', '$1[REDACTED]', (string) $sanitized);

        return (string) $sanitized;
    }

    /**
     * Build a readable command string for logging.
     * Shows the actual command (arg1) instead of the a-exec wrapper.
     */
    protected static function buildCommandString(array $params): string
    {
        // arg1 is the --debug flag when using a-exec wrapper.
        // arg2 contains the actual command.
        $commandString = $params['arg2'] ?? $params['cmd'] ?? '';
        $args = [];

        // Collect args starting from arg3 (arg1=debug flag, arg2=command).
        foreach ($params as $key => $value) {
            if (preg_match('/^arg(\d+)$/', (string) $key, $matches)) {
                $argNum = (int) $matches[1];
                if ($argNum >= 3) {
                    $args[$argNum] = $value;
                }
            }
        }

        ksort($args);

        foreach ($args as $arg) {
            $commandString .= " '".str_replace("'", "'\\''", $arg)."'";
        }

        return $commandString;
    }

    /**
     * Determine if request debug logs should be written.
     */
    protected static function shouldLogDebugRequests(): bool
    {
        return config('app.env') === 'local' || (bool) config('app.debug', false);
    }

    /**
     * Create a success response array.
     */
    protected static function successResponse(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'code' => 0,
        ];
    }

    /**
     * Create an error response array.
     */
    protected static function errorResponse(string $message, int $code = 1): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => [],
            'code' => $code,
        ];
    }
}
