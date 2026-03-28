<?php

namespace Modules\Platform\Services\Concerns;

use Modules\Platform\Models\Server;
use Throwable;

trait InteractsWithServerReleaseSync
{
    /**
     * Updates local releases by triggering the a-sync-releases script on the Hestia server.
     * The script handles fetching from central server and updating local repository.
     */
    public function updateLocalReleases(Server $server): array
    {
        if (empty($server->ip) || empty($server->access_key_id) || empty($server->access_key_secret)) {
            return $this->errorResponse('Server credentials are incomplete');
        }

        $syncArgs = ['application', 'main', '--set-active'];
        if ($server->isLocalhostType()) {
            $syncArgs[] = '--insecure';
        }

        try {
            $response = $this->executeHestiaCommand(
                'a-sync-releases',
                $server,
                $syncArgs,
                self::RELEASE_SYNC_TIMEOUT_SECONDS
            );

            if (! ($response['success'] ?? false)) {
                $message = (string) ($response['message'] ?? 'Unknown error');
                if ($this->isReleaseNoUpdateFoundMessage($message)) {
                    return $this->successResponse('No published release available yet. Skipping release sync.', [
                        'no_update' => true,
                        'execution_path' => 'hestia-api',
                    ]);
                }

                return $this->errorResponse('Failed to update releases: '.$message);
            }

            return $this->finalizeReleaseSyncResponse($server, $response, 'hestia-api');
        } catch (Throwable $throwable) {
            return $this->errorResponse('Failed to update releases: '.$throwable->getMessage());
        }
    }

    protected function isReleaseNoUpdateFoundMessage(string $message): bool
    {
        return str_contains(strtolower($message), 'no update found');
    }

    protected function extractReleaseVersionFromOutput(string $output): ?string
    {
        if (preg_match('/Synced:\s+\S+\/\S+\s+v([0-9A-Za-z._-]+)/', $output, $matches)) {
            return $matches[1];
        }

        if (preg_match('/Latest version:\s+v([0-9A-Za-z._-]+)/', $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function finalizeReleaseSyncResponse(Server $server, array $response, string $executionPath): array
    {
        $data = (array) ($response['data'] ?? []);
        $version = $data['version'] ?? null;

        if (! is_string($version) || $version === '') {
            $rawOutput = (string) ($data['raw'] ?? $data['output_tail'] ?? '');
            $version = $this->extractReleaseVersionFromOutput($rawOutput);
        }

        if (is_string($version) && $version !== '') {
            $server->setMetadata('astero_version', $version);
            if ($server->exists) {
                $server->save();
            }
        } else {
            $version = null;
        }

        return $this->successResponse(
            (string) ($response['message'] ?? 'Releases updated successfully.'),
            array_merge($data, [
                'version' => $version,
                'execution_path' => $executionPath,
            ])
        );
    }
}
