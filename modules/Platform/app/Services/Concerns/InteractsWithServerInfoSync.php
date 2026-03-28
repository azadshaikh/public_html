<?php

namespace Modules\Platform\Services\Concerns;

use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerSSHService;
use Throwable;

trait InteractsWithServerInfoSync
{
    /**
     * Syncs server info by fetching current state from Hestia server.
     * Uses the custom a-get-server-info script for comprehensive data.
     */
    public function syncServerInfo(Server $server): array
    {
        if (empty($server->ip) || empty($server->access_key_id) || empty($server->access_key_secret)) {
            return $this->errorResponse('Server credentials are incomplete (IP, Access Key ID, and Secret Key required)');
        }

        try {
            $response = $this->executeHestiaCommand(
                'a-get-server-info',
                $server,
                ['json'],
                self::SERVER_INFO_SYNC_TIMEOUT_SECONDS
            );

            if (! $response['success']) {
                return $this->syncServerInfoFallback($server);
            }

            $serverData = $response['data'] ?? [];

            if (empty($serverData) || ! is_array($serverData)) {
                return $this->syncServerInfoFallback($server);
            }

            $updates = [];

            if (! empty($serverData['hostname'])) {
                $server->fqdn = $serverData['hostname'];
                $updates['fqdn'] = $serverData['hostname'];
            }

            if (! empty($serverData['ip_address']) && empty($server->ip)) {
                $server->ip = $serverData['ip_address'];
                $updates['ip_address'] = $serverData['ip_address'];
            }

            if (! empty($serverData['os'])) {
                $server->setMetadata('server_os', $serverData['os']);
                $updates['os'] = $serverData['os'];
            }

            if (! empty($serverData['cpu'])) {
                $server->setMetadata('server_cpu', $serverData['cpu']);
                $updates['cpu'] = $serverData['cpu'];
            }

            if (! empty($serverData['cpu_cores'])) {
                $server->setMetadata('server_ccore', (string) $serverData['cpu_cores']);
                $updates['cpu_cores'] = $serverData['cpu_cores'];
            }

            if (! empty($serverData['ram_mb'])) {
                $server->setMetadata('server_ram', (int) $serverData['ram_mb']);
                $updates['ram_mb'] = $serverData['ram_mb'];
            }

            if (isset($serverData['ram_used_mb'])) {
                $server->setMetadata('server_ram_used', (int) $serverData['ram_used_mb']);
                $updates['ram_used_mb'] = $serverData['ram_used_mb'];
            }

            if (isset($serverData['ram_free_mb'])) {
                $server->setMetadata('server_ram_free', (int) $serverData['ram_free_mb']);
                $updates['ram_free_mb'] = $serverData['ram_free_mb'];
            }

            if (! empty($serverData['storage_total_gb'])) {
                $server->setMetadata('server_storage', (int) $serverData['storage_total_gb']);
                $updates['storage_total_gb'] = $serverData['storage_total_gb'];
            }

            if (! empty($serverData['storage_used_gb'])) {
                $server->setMetadata('server_storage_used', (int) $serverData['storage_used_gb']);
                $updates['storage_used_gb'] = $serverData['storage_used_gb'];
            }

            if (! empty($serverData['storage_free_gb'])) {
                $server->setMetadata('server_storage_free', (int) $serverData['storage_free_gb']);
                $updates['storage_free_gb'] = $serverData['storage_free_gb'];
            }

            if (! empty($serverData['hestia_version'])) {
                $server->setMetadata('hestia_version', $serverData['hestia_version']);
                $updates['hestia_version'] = $serverData['hestia_version'];
            }

            if (! empty($serverData['astero_version'])) {
                $server->setMetadata('astero_version', $serverData['astero_version']);
                $updates['astero_version'] = $serverData['astero_version'];
            }

            if (! empty($serverData['astero_releases'])) {
                $releases = is_array($serverData['astero_releases'])
                    ? $serverData['astero_releases']
                    : explode(',', (string) $serverData['astero_releases']);
                $server->setMetadata('astero_releases', $releases);
                $updates['astero_releases'] = $releases;
            }

            if (! empty($serverData['uptime'])) {
                $server->setMetadata('server_uptime', $serverData['uptime']);
                $updates['uptime'] = $serverData['uptime'];
            }

            if (! empty($serverData['load_average'])) {
                $server->setMetadata('server_load', $serverData['load_average']);
                $updates['load_average'] = $serverData['load_average'];
            }

            $domainCount = $this->resolveDomainCount($serverData, $server);

            if ($domainCount !== null) {
                $server->current_domains = $domainCount;
                $updates['domain_count'] = $domainCount;
            }

            if ($server->hasSshCredentials()) {
                try {
                    $sshService = resolve(ServerSSHService::class);
                    $acmeCheck = $sshService->executeCommand(
                        $server,
                        'id asterossl &>/dev/null && test -f /home/asterossl/.acme.sh/acme.sh && echo "ACME_OK" || echo "ACME_MISSING"',
                        15
                    );

                    $acmeInstalled = $acmeCheck['success'] && str_contains(trim($acmeCheck['data']['output'] ?? ''), 'ACME_OK');

                    if ($acmeInstalled !== (bool) $server->acme_configured) {
                        $server->acme_configured = $acmeInstalled;
                        $updates['acme_configured'] = $acmeInstalled;
                    }
                } catch (Throwable) {
                    // SSH check failed — don't block sync for this
                }
            }

            $server->setMetadata('last_synced_at', now()->toIso8601String());
            $server->save();

            if (empty($updates)) {
                return $this->infoResponse('Server synced but no information was updated');
            }

            return $this->successResponse('Server information synced successfully', $updates);
        } catch (Exception $exception) {
            return $this->errorResponse('Failed to sync server: '.$exception->getMessage());
        }
    }

    /**
     * Extract domain counts from the server info payload, with a fallback API query when missing.
     */
    protected function resolveDomainCount(array $serverData, Server $server): ?int
    {
        $domainCount = $this->extractDomainCountFromPayload($serverData);

        if ($domainCount === null || $domainCount === 0) {
            return $this->fetchDomainCountFromHestia($server);
        }

        return $domainCount;
    }

    /**
     * Handle the different response shapes we might see for domain counts.
     */
    protected function extractDomainCountFromPayload(array $serverData): ?int
    {
        foreach (['domain_count', 'web_domain_count', 'web_domains', 'domains'] as $domainKey) {
            if (isset($serverData[$domainKey])) {
                return (int) $serverData[$domainKey];
            }
        }

        $nestedCounts = [
            $serverData['domains']['total'] ?? null,
            $serverData['domains']['count'] ?? null,
            $serverData['stats']['domains'] ?? null,
        ];

        foreach ($nestedCounts as $count) {
            if ($count !== null) {
                return (int) $count;
            }
        }

        return null;
    }

    /**
     * Call Hestia API directly to total web domains per user.
     */
    protected function fetchDomainCountFromHestia(Server $server): ?int
    {
        try {
            $usersResponse = HestiaClient::execute('v-list-users', $server, ['json']);

            if (! $usersResponse['success']) {
                return null;
            }

            $data = $usersResponse['data'] ?? [];
            $users = $this->unwrapHestiaResponseData($data);

            if (isset($users['users']) && is_array($users['users'])) {
                $users = $users['users'];
            }

            if ($users === []) {
                return null;
            }

            $quickCount = 0;
            $hasWebDomains = false;
            foreach ($users as $userData) {
                if (is_array($userData) && isset($userData['WEB_DOMAINS'])) {
                    $quickCount += (int) $userData['WEB_DOMAINS'];
                    $hasWebDomains = true;
                }
            }

            if ($hasWebDomains && $quickCount > 0) {
                return $quickCount;
            }

            $domainCount = 0;
            foreach (array_keys($users) as $username) {
                if (! is_string($username)) {
                    continue;
                }

                $domainsResponse = HestiaClient::execute('v-list-web-domains', $server, [$username, 'json']);

                if ($domainsResponse['success']) {
                    $domains = $domainsResponse['data'] ?? [];
                    $domains = $this->unwrapHestiaResponseData($domains);
                    $domainCount += count($domains);
                }
            }

            return $domainCount > 0 ? $domainCount : null;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Normalize the common success wrapper from Hestia API responses.
     */
    protected function unwrapHestiaResponseData(array $data): array
    {
        if (isset($data['status']) && $data['status'] === 'success' && isset($data['data'])) {
            return $data['data'];
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        return $data;
    }

    /**
     * Fallback method using standard Hestia v-list-sys-info command.
     */
    protected function syncServerInfoFallback(Server $server): array
    {
        try {
            $response = HestiaClient::execute('v-list-sys-info', $server, ['json']);

            if (! $response['success']) {
                return $response;
            }

            $sysInfo = $response['data'] ?? [];
            $hostInfo = $sysInfo['sysinfo'] ?? $sysInfo;

            if (empty($hostInfo)) {
                return $this->errorResponse('Empty response from Hestia server');
            }

            $updates = [];

            if (! empty($hostInfo['OS'])) {
                $server->setMetadata('server_os', $hostInfo['OS']);
                $updates['os'] = $hostInfo['OS'];
            }

            if (! empty($hostInfo['HESTIA'])) {
                $server->setMetadata('hestia_version', $hostInfo['HESTIA']);
                $updates['hestia_version'] = $hostInfo['HESTIA'];
            }

            if (! empty($hostInfo['HOSTNAME'])) {
                $server->fqdn = $hostInfo['HOSTNAME'];
                $updates['fqdn'] = $hostInfo['HOSTNAME'];
            }

            $domainCount = $this->fetchDomainCountFromHestia($server);
            if ($domainCount !== null) {
                $server->current_domains = $domainCount;
                $updates['domain_count'] = $domainCount;
            }

            $server->setMetadata('last_synced_at', now()->toIso8601String());
            $server->save();

            if ($updates === []) {
                return $this->infoResponse('Server synced but no information was updated (fallback mode)');
            }

            return $this->successResponse(
                'Server synced (fallback mode). Updated: '.implode(', ', array_keys($updates)),
                $updates
            );
        } catch (Exception $exception) {
            return $this->errorResponse('Failed to sync server (fallback): '.$exception->getMessage());
        }
    }
}
