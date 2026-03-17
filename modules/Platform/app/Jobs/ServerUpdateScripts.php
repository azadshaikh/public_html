<?php

namespace Modules\Platform\Jobs;

use App\Enums\ActivityAction;
use App\Models\User;
use App\Traits\ActivityTrait;
use App\Traits\IsMonitored;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\Server;
use Modules\Platform\Notifications\ServerScriptsUpdated as NotificationServerScriptsUpdated;
use Modules\Platform\Services\ServerSSHService;
use Throwable;

/**
 * Updates Astero scripts and templates on a server via SSH/SFTP.
 *
 * Uploads:
 * - hestia/bin/* scripts to /usr/local/hestia/bin/
 * - hestia/data/templates/* to /usr/local/hestia/data/templates/
 *
 * Updates script version tracking in server model.
 */
class ServerUpdateScripts implements ShouldQueue
{
    use ActivityTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    /**
     * The server ID to update.
     */
    public int $serverId;

    /**
     * Number of retry attempts.
     */
    public int $tries = 3;

    /**
     * Timeout for the job in seconds.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(Server $server, /**
     * User ID that initiated the script update.
     */
        public ?int $initiatedById = null)
    {
        $this->serverId = $server->id;
    }

    /**
     * Execute the job.
     */
    public function handle(ServerSSHService $sshService): void
    {
        $this->queueMonitorLabel('Server #'.$this->serverId);
        /** @var Server|null $server */
        $server = Server::query()->find($this->serverId);

        if (! $server) {
            Log::error('ServerUpdateScripts: Server not found', ['server_id' => $this->serverId]);

            return;
        }

        if (! $server->hasSshCredentials()) {
            $this->logActivity($server, ActivityAction::UPDATE, 'Script update failed: SSH credentials not configured');
            Log::error('ServerUpdateScripts: SSH credentials not configured', ['server_id' => $this->serverId]);

            return;
        }

        try {
            Log::info('ServerUpdateScripts: Starting script update', [
                'server_id' => $server->id,
                'server_ip' => $server->ip,
            ]);

            // Get the hestia directory path
            $hestiaDir = base_path('hestia');

            throw_unless(is_dir($hestiaDir), Exception::class, 'Hestia directory not found: '.$hestiaDir);

            $uploadedCount = 0;
            $failedCount = 0;

            // 1. Upload bin scripts
            $binDir = $hestiaDir.'/bin';
            if (is_dir($binDir)) {
                $result = $sshService->uploadDirectory($server, $binDir, '/usr/local/hestia/bin');

                if (! ($result['success'] ?? false)) {
                    throw new Exception('Failed to upload bin scripts: '.($result['message'] ?? 'Unknown error'));
                }

                $uploadedCount += count($result['data']['uploaded'] ?? []);
                $failedBinScripts = array_values($result['data']['failed'] ?? []);
                $failedCount += count($failedBinScripts);

                Log::debug('ServerUpdateScripts: Bin scripts uploaded', [
                    'server_id' => $server->id,
                    'uploaded' => count($result['data']['uploaded'] ?? []),
                    'failed' => count($result['data']['failed'] ?? []),
                ]);

                if ($failedBinScripts !== []) {
                    $preview = implode(', ', array_slice($failedBinScripts, 0, 5));

                    throw new Exception(
                        sprintf(
                            'Failed to upload %d bin script file(s): %s',
                            count($failedBinScripts),
                            $preview
                        )
                    );
                }
            }

            // 2. Upload data/templates
            $templatesDir = $hestiaDir.'/data/templates';
            if (is_dir($templatesDir)) {
                $result = $sshService->uploadDirectory($server, $templatesDir, '/usr/local/hestia/data/templates');

                if (! ($result['success'] ?? false)) {
                    throw new Exception('Failed to upload templates: '.($result['message'] ?? 'Unknown error'));
                }

                $uploadedCount += count($result['data']['uploaded'] ?? []);
                $failedTemplates = array_values($result['data']['failed'] ?? []);
                $failedCount += count($failedTemplates);

                Log::debug('ServerUpdateScripts: Templates uploaded', [
                    'server_id' => $server->id,
                    'uploaded' => count($result['data']['uploaded'] ?? []),
                    'failed' => count($result['data']['failed'] ?? []),
                ]);

                if ($failedTemplates !== []) {
                    $preview = implode(', ', array_slice($failedTemplates, 0, 5));

                    throw new Exception(
                        sprintf(
                            'Failed to upload %d template file(s): %s',
                            count($failedTemplates),
                            $preview
                        )
                    );
                }
            }

            // 3. Fix line endings for all a-* scripts and symlink aliases
            $fixLineEndingsCmd = 'cd /usr/local/hestia/bin && for f in a-*; do sed -i "s/\\r$//" "$f" 2>/dev/null; done && chmod 755 a-* 2>/dev/null && if [ -f /usr/local/hestia/bin/a-astero-aliases.sh ]; then ln -sf /usr/local/hestia/bin/a-astero-aliases.sh /etc/profile.d/astero-aliases.sh; fi';
            $sshService->executeCommand($server, $fixLineEndingsCmd, 60);

            // 4. Update version tracking
            $version = config('app.version', date('Y.m.d'));
            $server->update([
                'scripts_version' => $version,
                'scripts_updated_at' => now(),
            ]);

            $message = sprintf(
                'Scripts updated successfully: %d files uploaded (%d failed)',
                $uploadedCount,
                $failedCount
            );

            $this->logActivity($server, ActivityAction::UPDATE, $message);
            $this->notifyInitiator($server, $uploadedCount, $failedCount);

            Log::info('ServerUpdateScripts: Completed', [
                'server_id' => $server->id,
                'scripts_version' => $version,
                'uploaded' => $uploadedCount,
                'failed' => $failedCount,
            ]);
        } catch (Throwable $throwable) {
            $this->logActivity($server, ActivityAction::UPDATE, 'Script update failed: '.$throwable->getMessage());

            Log::error('ServerUpdateScripts: Failed', [
                'server_id' => $server->id,
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
        $server = Server::query()->find($this->serverId);

        if ($server) {
            Log::error('ServerUpdateScripts failed for server #'.$server->id, [
                'message' => $exception?->getMessage(),
            ]);
        }
    }

    /**
     * Notify the user who initiated the update (with graceful fallback).
     */
    private function notifyInitiator(Server $server, int $uploadedCount, int $failedCount): void
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
            $user->notify(new NotificationServerScriptsUpdated($server, $uploadedCount, $failedCount));
        }
    }
}
