<?php

namespace Modules\Platform\Jobs;

use App\Enums\ActivityAction;
use App\Models\User;
use App\Traits\ActivityTrait;
use App\Traits\IsMonitored;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\Server;
use Modules\Platform\Notifications\ServerReleasesUpdated as NotificationServerReleasesUpdated;
use Modules\Platform\Services\ServerService;
use RuntimeException;
use Throwable;

/**
 * Updates a server's local releases and then syncs its info so UI reflects the new version.
 */
class ServerUpdateReleases implements ShouldQueue
{
    use ActivityTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /**
     * Allow enough wall time for long-running release sync.
     */
    public int $timeout = 1200;

    /**
     * The server ID to be updated.
     */
    public int $serverId;

    /**
     * Create a new job instance.
     */
    public function __construct(Server $server, /**
     * User ID that initiated the release update.
     */
        public ?int $initiatedById = null)
    {
        $this->serverId = $server->id;
    }

    /**
     * Execute the job.
     */
    public function handle(ServerService $serverService): void
    {
        $this->queueMonitorLabel('Server #'.$this->serverId);
        /** @var Server|null $server */
        $server = Server::withTrashed()->find($this->serverId);

        if (! $server) {
            Log::error('ServerUpdateReleases job failed: Server not found', [
                'server_id' => $this->serverId,
            ]);

            return;
        }

        try {
            Log::info('ServerUpdateReleases job started', [
                'server_id' => $server->id,
                'server_ip' => $server->ip,
            ]);

            $updateResult = $serverService->updateLocalReleases($server);

            if (! ($updateResult['success'] ?? false)) {
                $message = $updateResult['message'] ?? 'Failed to update releases';
                $this->logActivity($server, ActivityAction::UPDATE, 'Server release update failed: '.$message);
                throw new RuntimeException($message);
            }

            $releaseVersion = (string) ($updateResult['data']['version'] ?? '' ?: '');

            $this->logActivity($server, ActivityAction::UPDATE, $updateResult['message'] ?? 'Releases updated successfully');

            $syncResult = $serverService->syncServerInfo($server);
            $syncWarning = false;

            if (! ($syncResult['success'] ?? false)) {
                $message = $syncResult['message'] ?? 'Failed to sync server after updating releases';
                $this->logActivity($server, ActivityAction::UPDATE, 'Server sync after release update failed (non-blocking): '.$message);
                $syncWarning = true;

                Log::warning('Server sync after release update failed (non-blocking)', [
                    'server_id' => $server->id,
                    'message' => $message,
                ]);
            } else {
                $this->logActivity($server, ActivityAction::UPDATE, $syncResult['message'] ?? 'Server synced after release update.');
            }

            $this->notifyInitiator($server, $releaseVersion, $syncWarning);

            Log::info('ServerUpdateReleases job completed', [
                'server_id' => $server->id,
                'astero_version' => $server->getMetadata('astero_version'),
            ]);
        } catch (Throwable $throwable) {
            Log::error('ServerUpdateReleases job failed', [
                'server_id' => $server->id ?? $this->serverId,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            throw $throwable;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception = null): void
    {
        /** @var Server|null $server */
        $server = Server::withTrashed()->find($this->serverId);

        if (! $server) {
            Log::error('ServerUpdateReleases failed() called but server not found', [
                'server_id' => $this->serverId,
                'error' => $exception?->getMessage(),
            ]);

            return;
        }

        Log::error('ServerUpdateReleases failed for server #'.$server->id, [
            'message' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }

    /**
     * Notify the user who initiated the release update (with graceful fallback).
     */
    private function notifyInitiator(Server $server, string $releaseVersion, bool $syncWarning): void
    {
        /** @var User|null $user */
        $user = null;

        if ($this->initiatedById !== null) {
            /** @var User|null $foundUser */
            $foundUser = User::query()->find($this->initiatedById);
            $user = $foundUser;
        }

        if (! $user) {
            $server->loadMissing(['updatedBy', 'createdBy']);
            $user = $server->updatedBy ?? $server->createdBy;
        }

        if ($user) {
            $user->notify(new NotificationServerReleasesUpdated($server, $releaseVersion, $syncWarning));
        }
    }
}
