<?php

namespace Modules\Platform\Jobs;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use App\Traits\IsMonitored;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Jobs\Concerns\InteractsWithServerProvisionInstall;
use Modules\Platform\Jobs\Concerns\InteractsWithServerProvisionReleaseSync;
use Modules\Platform\Jobs\Concerns\InteractsWithServerProvisionState;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerAcmeSetupService;
use Modules\Platform\Services\ServerService;
use Modules\Platform\Services\ServerSSHService;
use RuntimeException;
use Throwable;

/**
 * Provisions a fresh VPS with HestiaCP and Astero scripts.
 *
 * Steps:
 * 1. Test SSH connectivity
 * 2. Install HestiaCP (if not installed)
 * 3. Upload Astero scripts
 * 4. Prepare server for Astero provisioning
 * 5. Configure ACME SSL automation
 * 6. Create admin access key
 * 7. Verify installation
 * 8. Update server with credentials and mark as ready
 *
 * Progress is tracked in server metadata['provisioning_steps'].
 */
class ServerProvision implements ShouldQueue
{
    use ActivityTrait;
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithServerProvisionInstall;
    use InteractsWithServerProvisionReleaseSync;
    use InteractsWithServerProvisionState;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    /**
     * Hestia install screen session details.
     */
    protected const HESTIA_INSTALL_SESSION = 'hestia_install';

    protected const HESTIA_INSTALL_POLL_SECONDS = 15;

    protected const HESTIA_INSTALL_PROGRESS_HEARTBEAT_SECONDS = 60;

    protected const HESTIA_INSTALL_MAX_WAIT_SECONDS = 5400;

    protected const HESTIA_INSTALL_LOG_PATH = '/tmp/hestia_install.log';

    protected const HESTIA_PROVISION_HELPER_LOCAL_PATH = 'hestia/bin/a-provision-hestia';

    protected const HESTIA_PROVISION_HELPER_REMOTE_PATH = '/tmp/a-provision-hestia';

    protected const SERVER_SETUP_SESSION = 'astero_server_setup';

    protected const SERVER_SETUP_POLL_SECONDS = 10;

    protected const SERVER_SETUP_MAX_WAIT_SECONDS = 1800;

    protected const SERVER_SETUP_LOG_PATH = '/tmp/astero_server_setup.log';

    protected const SERVER_SETUP_EXIT_CODE_PATH = '/tmp/astero_server_setup.exit';

    protected const SERVER_SETUP_SCREEN_HELPER_PATH = '/usr/local/hestia/bin/a-run-server-setup-screen';

    protected const RETRYABLE_HESTIA_INSTALL_MARKER = 'HESTIA_INSTALL_STILL_RUNNING';

    protected const STOP_REQUESTED_MARKER = 'PROVISIONING_STOP_REQUESTED';

    /**
     * Provisioning step names for tracking.
     */
    protected const STEPS = [
        'ssh_connection' => 'Testing SSH connection',
        'hestia_check' => 'Checking HestiaCP installation',
        'hestia_install' => 'Installing HestiaCP',
        'server_reboot' => 'Rebooting server',
        'scripts_upload' => 'Uploading Astero scripts',
        'server_setup' => 'Setting up server',
        'acme_setup' => 'Setting up ACME SSL',
        'release_api_key' => 'Configuring release API key',
        'access_key' => 'Creating access key',
        'verification' => 'Verifying installation',
        'update_releases' => 'Updating releases',
        'server_sync' => 'Syncing server info',
        'pg_optimize' => 'Optimizing PostgreSQL',
    ];

    /**
     * Step execution order (used for documentation and iteration).
     */
    protected const STEP_ORDER = [
        'ssh_connection',
        'hestia_check',
        'hestia_install',
        'server_reboot',
        'scripts_upload',
        'server_setup',
        'acme_setup',
        'release_api_key',
        'access_key',
        'verification',
        'update_releases',
        'server_sync',
        'pg_optimize',
    ];

    /**
     * The server ID to provision.
     */
    public int $serverId;

    /**
     * Number of retry attempts.
     */
    public int $tries = 2;

    /**
     * Timeout for the job in seconds.
     */
    public int $timeout = 7200;

    /**
     * Create a new job instance.
     */
    public function __construct(Server $server)
    {
        $this->serverId = $server->id;
    }

    /**
     * Prevent concurrent provisioning jobs for the same server.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('server-provision:'.$this->serverId))
                ->releaseAfter(120)
                ->expireAfter($this->timeout + 600),
        ];
    }

    /**
     * Backoff schedule for transient failures.
     */
    public function backoff(): array
    {
        return [180];
    }

    /**
     * Stop retrying after a fixed time budget.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(8);
    }

    /**
     * Execute the job.
     */
    public function handle(
        ServerSSHService $sshService,
        ServerService $serverService,
        ServerAcmeSetupService $acmeSetupService
    ): void {
        $this->queueMonitorLabel('Server #'.$this->serverId);
        /** @var Server|null $server */
        $server = Server::query()->find($this->serverId);

        if (! $server) {
            Log::error('ServerProvision: Server not found', ['server_id' => $this->serverId]);

            return;
        }

        if ((bool) $server->getMetadata('provisioning_control.stop_requested', false)) {
            Log::warning('ServerProvision: stop requested before execution; skipping run', [
                'server_id' => $server->id,
            ]);

            return;
        }

        // Mark as provisioning
        $server->update([
            'provisioning_status' => Server::PROVISIONING_STATUS_PROVISIONING,
            'status' => 'provisioning',
        ]);
        $this->initSteps($server);

        try {
            Log::info('ServerProvision: Starting provisioning', [
                'server_id' => $server->id,
                'server_ip' => $server->ip,
            ]);

            $this->abortIfProvisioningStopRequested($server);

            // Step 1: Test SSH connection
            if (! $this->isStepDone($server, 'ssh_connection')) {
                $this->abortIfProvisioningStopRequested($server);
                $this->updateStep($server, 'ssh_connection', 'running');
                $result = $sshService->testConnection($server);

                if (! $result['success']) {
                    throw new Exception('SSH connection failed: '.$result['message']);
                }

                $this->updateStep($server, 'ssh_connection', 'completed', [
                    'summary' => 'SSH connection established successfully.',
                    'os_info' => $this->sanitizeSensitiveMessage((string) ($result['data']['os_info'] ?? '')),
                ]);
            }

            // Step 2: Check if HestiaCP is installed
            $hestiaInstalled = false;
            if (! $this->isStepDone($server, 'hestia_check')) {
                $this->abortIfProvisioningStopRequested($server);
                $this->updateStep($server, 'hestia_check', 'running');
                $hestiaResult = $sshService->isHestiaInstalled($server);
                $hestiaInstalled = $hestiaResult['data']['installed'] ?? false;
                $this->updateStep($server, 'hestia_check', 'completed', ['installed' => $hestiaInstalled]);
            } else {
                // Get the stored result if already completed
                $steps = $server->getMetadata('provisioning_steps') ?? [];
                $hestiaInstalled = $steps['hestia_check']['data']['installed'] ?? false;
            }

            // Step 3: Install HestiaCP if not installed
            $hestiaJustInstalled = false;
            if (! $this->isStepDone($server, 'hestia_install')) {
                if (! $hestiaInstalled) {
                    $this->abortIfProvisioningStopRequested($server);
                    $this->updateStep($server, 'hestia_install', 'running');
                    $hestiaInstallResult = $this->installHestia($server, $sshService);
                    $this->updateStep($server, 'hestia_install', 'completed', $hestiaInstallResult);
                    $hestiaJustInstalled = true;
                } else {
                    $this->updateStep($server, 'hestia_install', 'skipped', ['reason' => 'Already installed']);
                }
            }

            // Step 4: Reboot server (only if HestiaCP was just installed)
            if (! $this->isStepDone($server, 'server_reboot')) {
                if ($hestiaJustInstalled) {
                    $this->abortIfProvisioningStopRequested($server);
                    $this->updateStep($server, 'server_reboot', 'running');
                    $result = $sshService->rebootServer($server, 15, 300);
                    if (! $result['success']) {
                        throw new Exception('Server reboot failed: '.$result['message']);
                    }

                    $this->updateStep($server, 'server_reboot', 'completed', $result['data']);
                } else {
                    $this->updateStep($server, 'server_reboot', 'skipped', ['reason' => 'Fresh install not required']);
                }
            }

            // Fix SFTP subsystem (HestiaCP breaks it, must fix before we can upload via SFTP)
            // This runs via SSH which still works
            $this->abortIfProvisioningStopRequested($server);
            $this->fixSftpSubsystem($server, $sshService);

            // Step 5: Upload Astero scripts (zip-based)
            if (! $this->isStepDone($server, 'scripts_upload')) {
                $this->abortIfProvisioningStopRequested($server);
                $this->updateStep($server, 'scripts_upload', 'running');
                $scriptsUploadResult = $this->uploadScripts($server, $sshService);
                $this->updateStep($server, 'scripts_upload', 'completed', $scriptsUploadResult);
            }

            // Step 6: Run server setup (configures HestiaCP, PHP, directories, supervisor)
            if (! $this->isStepDone($server, 'server_setup')) {
                $this->abortIfProvisioningStopRequested($server);
                $this->updateStep($server, 'server_setup', 'running');
                $serverSetupResult = $this->runServerSetup($server, $sshService);
                $this->updateStep($server, 'server_setup', 'completed', $serverSetupResult);
            }

            // Step 7: Install ACME tooling and wildcard SSL helper scripts
            if (! $this->isStepDone($server, 'acme_setup')) {
                if ((bool) $server->acme_configured) {
                    $this->updateStep($server, 'acme_setup', 'skipped', ['reason' => 'Already configured']);
                } else {
                    $this->abortIfProvisioningStopRequested($server);
                    $this->updateStep($server, 'acme_setup', 'running');
                    $acmeSetupResult = $acmeSetupService->setup($server);
                    $this->updateStep($server, 'acme_setup', 'completed', $acmeSetupResult);
                }
            }

            // Step 8: Configure release API key for secured release sync
            if (! $this->isStepDone($server, 'release_api_key')) {
                $this->abortIfProvisioningStopRequested($server);
                $this->updateStep($server, 'release_api_key', 'running');
                $releaseKeyResult = $this->configureReleaseApiKey($server, $sshService);
                $this->updateStep($server, 'release_api_key', 'completed', $releaseKeyResult);
            }

            // Step 9: Create access key
            $credentials = null;
            if (! $this->isStepDone($server, 'access_key')) {
                $this->abortIfProvisioningStopRequested($server);
                $this->updateStep($server, 'access_key', 'running');
                $credentials = $this->createAccessKey($server, $sshService);
                $this->updateStep($server, 'access_key', 'completed', [
                    'summary' => 'HestiaCP API access key created.',
                ]);
                $server->update([
                    'access_key_id' => $credentials['access_key_id'],
                    'access_key_secret' => $credentials['access_key_secret'],
                ]);
            }

            // Step 10: Verify installation
            if (! $this->isStepDone($server, 'verification')) {
                $this->abortIfProvisioningStopRequested($server);
                $this->updateStep($server, 'verification', 'running');
                $verificationResult = $this->verifyInstallation($server, $sshService);
                $this->updateStep($server, 'verification', 'completed', $verificationResult);
            }

            // Step 11: Update releases
            if (! $this->isStepDone($server, 'update_releases')) {
                $this->abortIfProvisioningStopRequested($server);
                $this->updateStep($server, 'update_releases', 'running');
                $updateReleaseResult = $this->updateReleases($server, $sshService, $serverService);
                $this->updateStep($server, 'update_releases', 'completed', $updateReleaseResult);
            }

            // Step 12: Sync server info
            if (! $this->isStepDone($server, 'server_sync')) {
                $this->abortIfProvisioningStopRequested($server);
                $this->updateStep($server, 'server_sync', 'running');
                $syncServerResult = $this->syncServer($server, $serverService);
                $this->updateStep($server, 'server_sync', 'completed', $syncServerResult);
            }

            // Step 13: Optimize PostgreSQL settings
            if (! $this->isStepDone($server, 'pg_optimize')) {
                $this->abortIfProvisioningStopRequested($server);
                $this->updateStep($server, 'pg_optimize', 'running');
                $pgOptimizeResult = $this->applyPgOptimizations($server);
                $this->updateStep($server, 'pg_optimize', 'completed', $pgOptimizeResult);
            }

            // Update server and mark as ready
            $server->setMetadata('provisioning_control.stop_requested', false);
            $server->setMetadata('provisioning_completed_at', now()->toISOString());
            $updateData = [
                'provisioning_status' => Server::PROVISIONING_STATUS_READY,
                'status' => 'active',
                'scripts_version' => config('app.version', date('Y.m.d')),
                'scripts_updated_at' => now(),
            ];

            $server->update($updateData);

            $this->logActivity($server, ActivityAction::CREATE, 'Server provisioned successfully');

            Log::info('ServerProvision: Completed successfully', [
                'server_id' => $server->id,
            ]);
        } catch (Throwable $throwable) {
            $sanitizedError = $this->sanitizeSensitiveMessage($throwable->getMessage());

            if ($this->isProvisioningStopException($throwable)) {
                $this->markCurrentStepAsFailed($server, 'Provisioning stopped manually by user.');
                $server->update([
                    'provisioning_status' => Server::PROVISIONING_STATUS_FAILED,
                    'status' => 'failed',
                ]);
                Log::warning('ServerProvision: execution stopped by user request', [
                    'server_id' => $server->id,
                ]);

                return;
            }

            if ($this->shouldReleaseForRetry($throwable) && $this->attempts() < $this->tries) {
                $delay = $this->backoffSeconds();
                $this->recordRetryMetadata($server, $sanitizedError, $delay);

                Log::warning('ServerProvision: transient error, releasing for retry', [
                    'server_id' => $server->id,
                    'attempt' => $this->attempts(),
                    'max_attempts' => $this->tries,
                    'delay_seconds' => $delay,
                    'error' => $sanitizedError,
                ]);

                $this->release($delay);

                return;
            }

            // Mark the current running step as failed with error message
            $this->markCurrentStepAsFailed($server, $sanitizedError);

            $server->update([
                'provisioning_status' => Server::PROVISIONING_STATUS_FAILED,
                'status' => 'failed',
            ]);
            $this->logActivity($server, ActivityAction::CREATE, 'Server provisioning failed: '.$sanitizedError);

            Log::error('ServerProvision: Failed', [
                'server_id' => $server->id,
                'error' => $sanitizedError,
                'trace' => $throwable->getTraceAsString(),
            ]);

            throw new RuntimeException($sanitizedError, 0, $throwable);
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
            $sanitizedError = $this->sanitizeSensitiveMessage($exception?->getMessage() ?? 'Unknown error');

            // Mark the running step as failed
            $this->markCurrentStepAsFailed($server, $sanitizedError);

            $server->update([
                'provisioning_status' => Server::PROVISIONING_STATUS_FAILED,
                'status' => 'failed',
            ]);

            Log::error('ServerProvision failed for server #'.$server->id, [
                'message' => $sanitizedError,
            ]);
        }
    }
}
