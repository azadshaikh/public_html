<?php

namespace Modules\Platform\Jobs;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use App\Traits\IsMonitored;
use DateTimeInterface;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerAcmeSetupService;
use Modules\Platform\Services\ServerService;
use Modules\Platform\Services\ServerSSHService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;
use ZipArchive;

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

    /**
     * Install HestiaCP on the server.
     */
    protected function installHestia(Server $server, ServerSSHService $sshService): array
    {
        $adminPassword = trim((string) $server->getSecretValue('hestiacp_temp_password'));
        if ($adminPassword === '') {
            $legacyPassword = trim((string) ($server->getMetadata('temp_admin_password') ?? ''));
            if ($legacyPassword !== '') {
                $adminPassword = $legacyPassword;
                $server->setSecret('hestiacp_temp_password', $adminPassword, 'password', 'adminxastero', [
                    'temporary' => true,
                ]);
                $server->setMetadata('temp_admin_password', null);
                $server->save();
            }
        }

        if ($adminPassword === '') {
            $adminPassword = bin2hex(random_bytes(16));
            $server->setSecret('hestiacp_temp_password', $adminPassword, 'password', 'adminxastero', [
                'temporary' => true,
            ]);
        }

        $adminUsername = 'adminxastero';
        $adminEmail = 'hestia@astero.net.in';

        // Get install options from metadata
        $options = $server->getMetadata('install_options') ?? [];
        $normalizedPhpVersions = $this->normalizePhpVersions((string) ($options['multiphp_versions'] ?? '8.4'));
        $options['multiphp_versions'] = implode(',', $normalizedPhpVersions);

        $flags = $this->buildHestiaInstallFlags($server, $options, $adminUsername, $adminEmail, $adminPassword);
        $provisionCommand = $this->buildHestiaProvisionCommand($flags);

        Log::info('ServerProvision: preparing HestiaCP install in screen session', [
            'server_id' => $server->id,
            'hostname' => $server->fqdn ?: $server->ip,
            'options' => $options,
            'screen_session' => self::HESTIA_INSTALL_SESSION,
            'reattach_cmd' => 'screen -r '.self::HESTIA_INSTALL_SESSION,
            'log_file' => self::HESTIA_INSTALL_LOG_PATH,
            'command' => preg_replace('/--password\s+\'[^\']+\'/', "--password '***'", $provisionCommand),
        ]);

        $this->ensureScreenInstalled($server, $sshService);
        $this->uploadHestiaProvisionHelperScript($server, $sshService);
        $this->abortIfProvisioningStopRequested($server);

        $bootstrapResult = $this->executeSshCommand(
            $server,
            $sshService,
            $provisionCommand,
            240,
            'bootstrap_hestia_installer'
        );

        throw_unless($bootstrapResult['success'] ?? false, RuntimeException::class, self::RETRYABLE_HESTIA_INSTALL_MARKER.': failed to bootstrap installer');

        $bootstrapOutput = (string) ($bootstrapResult['data']['output'] ?? '');
        $alreadyInstalledNow = str_contains($bootstrapOutput, 'STATE:INSTALLED');
        $installerActive = str_contains($bootstrapOutput, 'STATE:RUNNING') || str_contains($bootstrapOutput, 'STATE:STARTED');

        if ($alreadyInstalledNow && ! $installerActive) {
            Log::info('ServerProvision: Hestia already installed before installer launch; skipping installer start', [
                'server_id' => $server->id,
            ]);
        } else {
            if (str_contains($bootstrapOutput, 'STATE:STARTED')) {
                Log::info('ServerProvision: started Hestia install screen session', [
                    'server_id' => $server->id,
                    'screen_session' => self::HESTIA_INSTALL_SESSION,
                ]);
            } elseif ($alreadyInstalledNow) {
                Log::info('ServerProvision: detected Hestia install marker but installer is still active; waiting for completion', [
                    'server_id' => $server->id,
                    'screen_session' => self::HESTIA_INSTALL_SESSION,
                ]);
            } else {
                Log::info('ServerProvision: found running Hestia installer, resuming monitor', [
                    'server_id' => $server->id,
                    'screen_session' => self::HESTIA_INSTALL_SESSION,
                ]);
            }

            // Wait for install to complete by checking session/process state.
            $maxWait = self::HESTIA_INSTALL_MAX_WAIT_SECONDS;
            $interval = self::HESTIA_INSTALL_POLL_SECONDS;
            $elapsed = 0;

            while ($elapsed < $maxWait) {
                $this->abortIfProvisioningStopRequested($server);
                if (! $this->isHestiaInstallerActive($server, $sshService)) {
                    break;
                }

                Sleep::sleep($interval);
                $elapsed += $interval;

                if ($elapsed % self::HESTIA_INSTALL_PROGRESS_HEARTBEAT_SECONDS === 0) {
                    $this->updateStep($server, 'hestia_install', 'running', [
                        'elapsed_seconds' => $elapsed,
                        'log_file' => self::HESTIA_INSTALL_LOG_PATH,
                        'reattach_cmd' => 'screen -r '.self::HESTIA_INSTALL_SESSION,
                    ]);
                }
            }

            throw_if($this->isHestiaInstallerActive($server, $sshService), RuntimeException::class, self::RETRYABLE_HESTIA_INSTALL_MARKER.': HestiaCP installer is still running in '.self::HESTIA_INSTALL_SESSION);
        }

        // Verify HestiaCP was installed successfully
        $verifyResult = $this->executeSshCommand(
            $server,
            $sshService,
            'if [ -f /usr/local/hestia/bin/v-list-users ]; then echo "SUCCESS"; else echo "NOT_INSTALLED"; fi',
            30,
            'verify_hestia_install'
        );
        throw_unless(str_contains($verifyResult['data']['output'] ?? '', 'SUCCESS'), Exception::class, 'HestiaCP installation failed. SSH in and check: tail -100 '.self::HESTIA_INSTALL_LOG_PATH);

        $installLogTailResult = $this->executeSshCommand(
            $server,
            $sshService,
            'tail -n 120 '.self::HESTIA_INSTALL_LOG_PATH.' 2>/dev/null || true',
            20,
            'read_hestia_install_log_tail'
        );
        $installLogTail = $this->sanitizeSensitiveMessage((string) ($installLogTailResult['data']['output'] ?? ''));

        // Store admin credentials securely using HasSecrets trait
        $server->setSecret('hestiacp_password', $adminPassword, 'password', $adminUsername, [
            'email' => $adminEmail,
            'port' => $options['port'] ?? 8443,
        ]);

        return [
            'log_file' => self::HESTIA_INSTALL_LOG_PATH,
            'summary' => 'HestiaCP installed successfully.',
            'log_tail' => trim(substr($installLogTail, -4000)),
        ];
    }

    protected function buildHestiaInstallFlags(
        Server $server,
        array $options,
        string $adminUsername,
        string $adminEmail,
        string $adminPassword
    ): array {
        $flags = [
            '--interactive' => 'no',
            '--hostname' => $server->fqdn ?: $server->ip,
            '--email' => $adminEmail,
            '--username' => $adminUsername,
            '--password' => $adminPassword,
            '--port' => (string) ($options['port'] ?? 8443),
            '--lang' => $options['lang'] ?? 'en',
            '--apache' => $options['apache'] ?? false ? 'yes' : 'no',
            '--phpfpm' => $options['phpfpm'] ?? true ? 'yes' : 'no',
            // Prevent installer from adding every PHP version. We install selected versions in a-setup-astero.
            '--multiphp' => 'no',
            '--vsftpd' => $options['vsftpd'] ?? false ? 'yes' : 'no',
            '--proftpd' => $options['proftpd'] ?? false ? 'yes' : 'no',
            '--named' => $options['named'] ?? false ? 'yes' : 'no',
            '--mysql' => $options['mysql'] ?? false ? 'yes' : 'no',
            '--mysql8' => $options['mysql8'] ?? false ? 'yes' : 'no',
            '--postgresql' => $options['postgresql'] ?? true ? 'yes' : 'no',
            '--exim' => $options['exim'] ?? false ? 'yes' : 'no',
            '--dovecot' => $options['dovecot'] ?? false ? 'yes' : 'no',
            '--sieve' => $options['sieve'] ?? false ? 'yes' : 'no',
            '--clamav' => $options['clamav'] ?? false ? 'yes' : 'no',
            '--spamassassin' => $options['spamassassin'] ?? false ? 'yes' : 'no',
            '--iptables' => $options['iptables'] ?? true ? 'yes' : 'no',
            '--fail2ban' => $options['fail2ban'] ?? true ? 'yes' : 'no',
            '--quota' => $options['quota'] ?? false ? 'yes' : 'no',
            '--resourcelimit' => $options['resourcelimit'] ?? false ? 'yes' : 'no',
            '--webterminal' => $options['webterminal'] ?? true ? 'yes' : 'no',
            '--api' => $options['api'] ?? true ? 'yes' : 'no',
        ];

        if ($options['force'] ?? true) {
            $flags['--force'] = null;
        }

        return $flags;
    }

    protected function buildHestiaProvisionCommand(array $flags): string
    {
        $command = self::HESTIA_PROVISION_HELPER_REMOTE_PATH;

        foreach ($flags as $flag => $value) {
            if ($value === null) {
                $command .= ' '.$flag;
            } else {
                $command .= sprintf(' %s ', $flag).escapeshellarg((string) $value);
            }
        }

        $command .= ' --session '.escapeshellarg(self::HESTIA_INSTALL_SESSION);

        return $command.' --log-file '.escapeshellarg(self::HESTIA_INSTALL_LOG_PATH);
    }

    protected function uploadHestiaProvisionHelperScript(Server $server, ServerSSHService $sshService): void
    {
        $localPath = base_path(self::HESTIA_PROVISION_HELPER_LOCAL_PATH);
        throw_unless(is_file($localPath), RuntimeException::class, 'Missing provisioning helper script: '.self::HESTIA_PROVISION_HELPER_LOCAL_PATH);

        $uploadResult = $sshService->uploadFile(
            $server,
            $localPath,
            self::HESTIA_PROVISION_HELPER_REMOTE_PATH,
            0755
        );

        throw_unless($uploadResult['success'] ?? false, RuntimeException::class, 'Failed to upload Hestia provisioning helper script.');

        $prepareResult = $this->executeSshCommand(
            $server,
            $sshService,
            "sed -i 's/\\r$//' ".self::HESTIA_PROVISION_HELPER_REMOTE_PATH
                .' && chmod 755 '.self::HESTIA_PROVISION_HELPER_REMOTE_PATH,
            30,
            'prepare_hestia_provision_helper_script'
        );

        throw_unless($prepareResult['success'] ?? false, RuntimeException::class, 'Failed to prepare Hestia provisioning helper script.');
    }

    protected function ensureScreenInstalled(Server $server, ServerSSHService $sshService): void
    {
        $result = $this->executeSshCommand(
            $server,
            $sshService,
            'if ! command -v screen >/dev/null 2>&1; then export DEBIAN_FRONTEND=noninteractive && apt-get update -qq && apt-get install -y -qq screen; fi',
            180,
            'ensure_screen_installed'
        );

        throw_unless($result['success'] ?? false, RuntimeException::class, 'Failed to install required package: screen');
    }

    /**
     * Fix SFTP subsystem configuration.
     *
     * HestiaCP modifies sshd_config with a non-standard SFTP subsystem line
     * that breaks standard SFTP clients (including phpseclib). This fixes it.
     */
    protected function fixSftpSubsystem(Server $server, ServerSSHService $sshService): void
    {
        Log::info('ServerProvision: Fixing SFTP subsystem', ['server_id' => $server->id]);

        $sftpFixCmd = "sed -i 's/Subsystem sftp internal-sftp-.*/Subsystem sftp internal-sftp/' /etc/ssh/sshd_config && service ssh restart";
        $result = $sshService->executeCommand($server, $sftpFixCmd, 60);

        if (! $result['success']) {
            Log::warning('ServerProvision: Failed to fix SFTP subsystem', [
                'server_id' => $server->id,
                'output' => $result['data']['output'] ?? '',
            ]);
        }
    }

    /**
     * Run the consolidated server setup script.
     *
     * Executes a-provision-server which handles:
     * - HestiaCP configuration (timezone, API settings, etc.)
     * - PHP configuration (Composer, enable functions)
     * - Astero directory setup
     * - Supervisor configuration
     */
    protected function runServerSetup(Server $server, ServerSSHService $sshService): array
    {
        Log::info('ServerProvision: Running server setup script', ['server_id' => $server->id]);

        // Get timezone from metadata
        $options = $server->getMetadata('hestia_config') ?? [];
        $installOptions = $server->getMetadata('install_options') ?? [];
        $timezone = $options['timezone'] ?? 'Asia/Kolkata';
        $apiAllowedIp = $this->resolveApiAllowedIp($server);
        $multiphpEnabled = (bool) ($installOptions['multiphp'] ?? false);
        $multiphpVersions = implode(',', $this->normalizePhpVersions((string) ($installOptions['multiphp_versions'] ?? '8.4')));

        $setupCmd = '/usr/local/hestia/bin/a-provision-server'
            .' --timezone '.escapeshellarg((string) $timezone)
            .' --api-allowed-ip '.escapeshellarg($apiAllowedIp)
            .' --multiphp '.escapeshellarg($multiphpEnabled ? 'yes' : 'no')
            .' --php-versions '.escapeshellarg($multiphpVersions);
        $this->ensureScreenInstalled($server, $sshService);

        $helperBaseCommand = self::SERVER_SETUP_SCREEN_HELPER_PATH
            .' --session '.escapeshellarg(self::SERVER_SETUP_SESSION)
            .' --log-file '.escapeshellarg(self::SERVER_SETUP_LOG_PATH)
            .' --exit-file '.escapeshellarg(self::SERVER_SETUP_EXIT_CODE_PATH);

        $launchResult = $this->executeSshCommand(
            $server,
            $sshService,
            $helperBaseCommand.' start --command '.escapeshellarg($setupCmd),
            30,
            'start_server_setup_screen'
        );
        if (! ($launchResult['success'] ?? false)) {
            throw new Exception('Server setup failed to start: '.($launchResult['data']['output'] ?? $launchResult['message']));
        }

        $waitTimeout = self::SERVER_SETUP_MAX_WAIT_SECONDS + 120;
        $waitResult = $this->executeSshCommand(
            $server,
            $sshService,
            $helperBaseCommand.' wait --timeout '.self::SERVER_SETUP_MAX_WAIT_SECONDS.' --poll '.self::SERVER_SETUP_POLL_SECONDS,
            $waitTimeout,
            'wait_server_setup_screen'
        );
        if (! ($waitResult['success'] ?? false)) {
            $waitOutput = (string) ($waitResult['data']['output'] ?? '');
            throw_if(str_contains($waitOutput, 'WAIT_TIMEOUT'), RuntimeException::class, 'Server setup is still running in '.self::SERVER_SETUP_SESSION.' after '.self::SERVER_SETUP_MAX_WAIT_SECONDS.' seconds.');

            throw new Exception('Server setup monitor failed: '.($waitOutput !== '' ? $waitOutput : ($waitResult['message'] ?? 'Unknown error')));
        }

        $exitCodeResult = $this->executeSshCommand(
            $server,
            $sshService,
            $helperBaseCommand.' result',
            20,
            'read_server_setup_exit_code'
        );
        $exitCode = (int) trim((string) ($exitCodeResult['data']['output'] ?? '1'));

        $outputResult = $this->executeSshCommand(
            $server,
            $sshService,
            $helperBaseCommand.' log-tail --lines 120',
            20,
            'read_server_setup_log_tail'
        );
        $output = (string) ($outputResult['data']['output'] ?? '');
        $outputTail = $this->sanitizeSensitiveMessage(substr($output, -2000));

        throw_if($exitCode !== 0, Exception::class, 'Server setup failed: '.($outputTail !== '' ? $outputTail : 'unknown error'));

        Log::info('ServerProvision: Server setup completed', [
            'server_id' => $server->id,
            'output' => substr($output, -500), // Last 500 chars
        ]);

        return [
            'summary' => 'Server setup script completed successfully.',
            'output_tail' => $outputTail,
            'log_file' => self::SERVER_SETUP_LOG_PATH,
            'reattach_cmd' => 'screen -r '.self::SERVER_SETUP_SESSION,
        ];
    }

    /**
     * Persist release API key on provisioned server for a-sync-releases.
     */
    protected function configureReleaseApiKey(Server $server, ServerSSHService $sshService): array
    {
        $releaseApiKey = trim((string) ($server->getSecretValue('release_api_key') ?? $server->getMetadata('release_api_key') ?? ''));

        if ($releaseApiKey === '') {
            $releaseApiKey = $this->resolveDefaultReleaseApiKey();
        }

        throw_if($releaseApiKey === '', Exception::class, 'Release API key is missing. Set RELEASE_API_KEY in application environment or provide release_api_key in server form.');

        $isInsecureSync = $server->isLocalhostType() ? '1' : '0';

        $writeKeyCmd = sprintf(
            'mkdir -p /usr/local/hestia/data/astero'
            .' && printf %%s %s > /usr/local/hestia/data/astero/release_api_key'
            .' && printf %%s %s > /usr/local/hestia/data/astero/release_api_insecure'
            .' && chmod 600 /usr/local/hestia/data/astero/release_api_key /usr/local/hestia/data/astero/release_api_insecure'
            .' && chown root:root /usr/local/hestia/data/astero/release_api_key /usr/local/hestia/data/astero/release_api_insecure',
            escapeshellarg($releaseApiKey),
            escapeshellarg($isInsecureSync)
        );

        $result = $this->executeSshCommand($server, $sshService, $writeKeyCmd, 30, 'write_release_api_key_files');
        if (! $result['success']) {
            throw new Exception('Failed to configure release API key on server: '.($result['data']['output'] ?? $result['message']));
        }

        if ($server->getMetadata('release_api_key') !== null) {
            $server->setMetadata('release_api_key', null);
            $server->save();
        }

        return [
            'summary' => 'Release API key configured on target server.',
        ];
    }

    protected function resolveDefaultReleaseApiKey(): string
    {
        $fromConfig = trim((string) config('platform.release_api_key', ''));
        if ($fromConfig !== '') {
            return $fromConfig;
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

    /**
     * Upload Astero scripts to the server using zip-based approach.
     *
     * Instead of uploading files one-by-one via SFTP (slow and error-prone),
     * we zip the entire hestia folder, upload the single zip, extract on server,
     * and distribute files to their correct locations.
     */
    protected function uploadScripts(Server $server, ServerSSHService $sshService): array
    {
        $hestiaDir = base_path('hestia');
        $localZipPath = storage_path('app/temp/astero-scripts-'.uniqid().'.zip');
        $remoteZipPath = '/tmp/astero-scripts.zip';
        $remoteTempDir = '/tmp/astero-scripts';

        try {
            // Step 1: Create local zip of hestia folder (excluding .md files)
            Log::info('ServerProvision: Creating local zip of hestia folder', ['server_id' => $server->id]);
            $this->createHestiaZip($hestiaDir, $localZipPath);

            // Step 2: Upload zip file to server
            Log::info('ServerProvision: Uploading zip to server', ['server_id' => $server->id]);
            $result = $sshService->uploadFile($server, $localZipPath, $remoteZipPath);
            if (! $result['success']) {
                throw new Exception('Failed to upload scripts zip: '.$result['message']);
            }

            // Step 3: Extract and distribute files on server
            Log::info('ServerProvision: Extracting and distributing scripts', ['server_id' => $server->id]);
            $extractCmd = <<<BASH
# Clean up any previous temp files
rm -rf {$remoteTempDir}

echo "[upload] Preparing script deployment..."

# Unzip quietly to temp directory (hide per-file inflate noise)
echo "[upload] Extracting package..."
unzip -oq {$remoteZipPath} -d {$remoteTempDir}

# Copy bin scripts to hestia bin directory
if [ -d "{$remoteTempDir}/bin" ]; then
    echo "[upload] Installing executable scripts..."
    cp -f {$remoteTempDir}/bin/* /usr/local/hestia/bin/
    chmod 755 /usr/local/hestia/bin/a-*
    # Fix line endings (Windows -> Unix)
    for f in /usr/local/hestia/bin/a-*; do
        sed -i 's/\r$//' "\$f" 2>/dev/null || true
    done
fi

# Copy nginx templates (web templates for different website states)
if [ -d "{$remoteTempDir}/data/templates/web/nginx" ]; then
    echo "[upload] Updating nginx templates..."
    mkdir -p /usr/local/hestia/data/templates/web/nginx/php-fpm
    cp -rf {$remoteTempDir}/data/templates/web/nginx/* /usr/local/hestia/data/templates/web/nginx/
fi

# Copy PHP-FPM backend templates (pool configs with open_basedir for master installation)
if [ -d "{$remoteTempDir}/data/templates/web/php-fpm" ]; then
    echo "[upload] Updating PHP-FPM templates..."
    mkdir -p /usr/local/hestia/data/templates/web/php-fpm
    cp -rf {$remoteTempDir}/data/templates/web/php-fpm/* /usr/local/hestia/data/templates/web/php-fpm/
fi

# Symlink aliases to /etc/profile.d/ for all users
if [ -f /usr/local/hestia/bin/a-astero-aliases.sh ]; then
    echo "[upload] Symlinking aliases for all users..."
    ln -sf /usr/local/hestia/bin/a-astero-aliases.sh /etc/profile.d/astero-aliases.sh
fi

# Clean up temp files
rm -rf {$remoteTempDir}
rm -f {$remoteZipPath}

echo "[upload] Script deployment completed."
echo "SUCCESS"
BASH;

            $result = $sshService->executeCommand($server, $extractCmd, 120);
            if (! $result['success'] || ! str_contains($result['data']['output'] ?? '', 'SUCCESS')) {
                throw new Exception('Failed to extract and distribute scripts: '.($result['data']['output'] ?? $result['message']));
            }

            $output = (string) ($result['data']['output'] ?? '');
            $outputTail = trim($this->sanitizeSensitiveMessage(substr($output, -2000)));

            Log::info('ServerProvision: Scripts uploaded successfully', ['server_id' => $server->id]);

            return [
                'summary' => 'Astero scripts uploaded and distributed successfully.',
                'output_tail' => $outputTail,
            ];
        } finally {
            // Always clean up local temp zip
            if (file_exists($localZipPath)) {
                unlink($localZipPath);
            }
        }
    }

    /**
     * Create a zip archive of the hestia folder, excluding documentation files.
     */
    protected function createHestiaZip(string $sourceDir, string $zipPath): void
    {
        // Ensure temp directory exists
        $tempDir = dirname($zipPath);
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zip = new ZipArchive;
        throw_if($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true, Exception::class, 'Failed to create zip archive');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr((string) $filePath, strlen($sourceDir) + 1);

            // Skip documentation files
            if (str_ends_with($relativePath, '.md')) {
                continue;
            }

            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();

        throw_unless(file_exists($zipPath), Exception::class, 'Zip archive was not created');
    }

    /**
     * Create HestiaCP admin access key.
     */
    protected function createAccessKey(Server $server, ServerSSHService $sshService): array
    {
        // Create access key for API access
        $result = $sshService->executeCommand(
            $server,
            "/usr/local/hestia/bin/v-add-access-key adminxastero '*' astero json 2>&1",
            60
        );

        if (! $result['success']) {
            $output = $result['data']['output'] ?? '';
            throw new Exception('Failed to create access key: '.$this->sanitizeSensitiveMessage($result['message']."\n".$output));
        }

        $output = $result['data']['output'] ?? '';

        // Parse JSON response
        $credentials = json_decode($output, true);

        if (! $credentials || empty($credentials['ACCESS_KEY_ID']) || empty($credentials['SECRET_ACCESS_KEY'])) {
            // Try alternative parsing for different output formats
            preg_match('/ACCESS_KEY_ID[:\s]+([^\s\n]+)/i', $output, $keyMatch);
            preg_match('/SECRET_ACCESS_KEY[:\s]+([^\s\n]+)/i', $output, $secretMatch);

            if (isset($keyMatch[1]) && isset($secretMatch[1])) {
                return [
                    'access_key_id' => trim($keyMatch[1]),
                    'access_key_secret' => trim($secretMatch[1]),
                ];
            }

            throw new Exception('Failed to parse access key from command response.');
        }

        return [
            'access_key_id' => $credentials['ACCESS_KEY_ID'],
            'access_key_secret' => $credentials['SECRET_ACCESS_KEY'],
        ];
    }

    /**
     * Verify the installation is working.
     */
    protected function verifyInstallation(Server $server, ServerSSHService $sshService): array
    {
        // Check HestiaCP is running
        $hestiaResult = $sshService->executeCommand(
            $server,
            'systemctl is-active hestia 2>/dev/null || service hestia status 2>/dev/null | grep -i running',
            30
        );

        throw_unless($hestiaResult['success'], Exception::class, 'HestiaCP service is not running.');

        // Check Astero scripts are accessible
        $result = $sshService->executeCommand(
            $server,
            'test -x /usr/local/hestia/bin/a-sync-releases && echo "SCRIPTS_OK"',
            30
        );

        if (! str_contains($result['data']['output'] ?? '', 'SCRIPTS_OK')) {
            Log::warning('ServerProvision: Astero scripts verification warning', [
                'server_id' => $server->id,
            ]);
        }

        // Clean up temp password
        $server->deleteSecret('hestiacp_temp_password');
        $server->setMetadata('temp_admin_password', null);
        $server->save();

        return [
            'summary' => 'Hestia service and Astero script verification completed.',
            'output_tail' => trim($this->sanitizeSensitiveMessage((string) ($result['data']['output'] ?? ''))),
        ];
    }

    /**
     * Execute an SSH command with standardized debug logging.
     */
    protected function executeSshCommand(
        Server $server,
        ServerSSHService $sshService,
        string $command,
        int $timeout,
        string $context
    ): array {
        $commandPreview = $this->summarizeCommandForLog($command);

        if (config('app.debug')) {
            Log::info('ServerProvision: ssh command start', [
                'server_id' => $server->id,
                'context' => $context,
                'timeout_seconds' => $timeout,
                'command_preview' => $commandPreview,
            ]);
        }

        $result = $sshService->executeCommand($server, $command, $timeout);

        if (config('app.debug')) {
            $output = (string) ($result['data']['output'] ?? '');
            Log::info('ServerProvision: ssh command completed', [
                'server_id' => $server->id,
                'context' => $context,
                'success' => (bool) ($result['success'] ?? false),
                'exit_code' => $result['data']['exit_code'] ?? null,
                'message' => $this->sanitizeSensitiveMessage((string) ($result['message'] ?? '')),
                'command_preview' => $commandPreview,
                'output_tail' => trim($this->sanitizeSensitiveMessage(substr($output, -1000))),
            ]);
        }

        return $result;
    }

    protected function summarizeCommandForLog(string $command): string
    {
        $singleLine = preg_replace('/\s+/', ' ', trim($command));
        $singleLine = is_string($singleLine) ? $singleLine : $command;

        $singleLine = (string) preg_replace('/\s--password\s+(\S+)/i', ' --password [REDACTED]', $singleLine);
        $singleLine = (string) preg_replace('/\s(X-Release-Key|release[_\s-]*api[_\s-]*key)\s*[:=]\s*(\S+)/i', ' $1=[REDACTED]', $singleLine);

        if (strlen($singleLine) <= 240) {
            return $singleLine;
        }

        return substr($singleLine, 0, 240).'...';
    }

    /**
     * Initialize provisioning steps in metadata (preserves existing step status).
     */
    protected function initSteps(Server $server): void
    {
        $existingSteps = $server->getMetadata('provisioning_steps') ?? [];

        // Only initialize steps that don't already exist
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

        // Only set started_at if not already set
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

        if (isset($steps[$step])) {
            $steps[$step]['status'] = $status;

            if ($status === 'running') {
                if (empty($steps[$step]['started_at'])) {
                    $steps[$step]['started_at'] = now()->toISOString();
                }
            } elseif (in_array($status, ['completed', 'failed', 'skipped'])) {
                $steps[$step]['completed_at'] = now()->toISOString();
            }

            if ($data !== null) {
                $steps[$step]['data'] = $data;
            }

            $server->setMetadata('provisioning_steps', $steps);
            $server->save();

            $this->debugStepTrace($server, $step, $status, $data);
        }
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
     * Update server releases.
     */
    protected function updateReleases(Server $server, ServerSSHService $sshService, ServerService $serverService): array
    {
        Log::info('ServerProvision: Updating server releases', ['server_id' => $server->id]);

        $releaseZipUrl = trim((string) ($server->getMetadata('release_zip_url') ?? ''));
        if ($releaseZipUrl !== '') {
            return $this->setupReleaseFromZipUrl($server, $sshService, $releaseZipUrl);
        }

        // Backward compatibility path for older servers that don't have a release zip URL.
        // Ensure key files exist for release sync even if legacy runs marked release_api_key as completed earlier.
        $this->configureReleaseApiKey($server, $sshService);

        $result = $serverService->updateLocalReleases($server);

        if (! ($result['success'] ?? false)) {
            throw new Exception('Failed to update releases: '.($result['message'] ?? 'Unknown error'));
        }

        $version = (string) ($result['data']['version'] ?? '');
        $outputTail = trim((string) ($result['data']['output_tail'] ?? ''));
        $executionPath = (string) ($result['data']['execution_path'] ?? '');

        $payload = [
            'summary' => $result['message'] ?? 'Releases updated successfully.',
            'version' => $version,
        ];

        if ($outputTail !== '') {
            $payload['output_tail'] = $this->sanitizeSensitiveMessage(substr($outputTail, -2000));
        }

        if ($executionPath !== '') {
            $payload['execution_path'] = $executionPath;
        }

        return $payload;
    }

    protected function setupReleaseFromZipUrl(Server $server, ServerSSHService $sshService, string $releaseZipUrl): array
    {
        $path = parse_url($releaseZipUrl, PHP_URL_PATH);
        $fileName = basename((string) $path);

        throw_if(in_array($fileName, ['', '.', '/'], true), Exception::class, 'Failed to update releases: Invalid release zip URL.');

        $version = $this->extractVersionFromReleaseFileName($fileName);
        throw_if($version === '', Exception::class, 'Failed to update releases: Could not determine version from release zip URL.');

        $escapedUrl = escapeshellarg($releaseZipUrl);
        $escapedVersion = escapeshellarg($version);

        $command = <<<'BASH'
set -euo pipefail
ASTERO_DATA_DIR="/usr/local/hestia/data/astero"
PACKAGE_DIR="$ASTERO_DATA_DIR/releases/application/main"
TMP_FILE="/tmp/astero-release-${RANDOM}.zip"

mkdir -p "$PACKAGE_DIR"

if command -v curl >/dev/null 2>&1; then
  curl -fL --connect-timeout 15 --max-time 1800 __URL__ -o "$TMP_FILE"
elif command -v wget >/dev/null 2>&1; then
  wget --timeout=1800 -O "$TMP_FILE" __URL__
else
  echo "Neither curl nor wget is available"
  exit 1
fi

test -s "$TMP_FILE"
mv "$TMP_FILE" "$PACKAGE_DIR/v__VERSION__.zip"
ln -sfn "v__VERSION__.zip" "$PACKAGE_DIR/current"

if [ -x "/usr/local/hestia/bin/a-install-master" ]; then
  /usr/local/hestia/bin/a-install-master "__VERSION__" "application" "main" || true
fi

echo "ASTERO_RELEASE_VERSION=__VERSION__"
BASH;

        $command = str_replace('__URL__', $escapedUrl, $command);
        $command = str_replace('__VERSION__', trim($escapedVersion, "'"), $command);

        $result = $sshService->executeCommand($server, $command, 2100);
        if (! ($result['success'] ?? false)) {
            $message = (string) ($result['message'] ?? 'Unknown SSH error');
            $output = (string) ($result['data']['output'] ?? '' ?: '');
            throw new Exception('Failed to update releases: '.$message.($output !== '' ? ' | '.$output : ''));
        }

        $output = (string) ($result['data']['output'] ?? '');

        return [
            'summary' => 'Release setup completed from release zip URL.',
            'version' => $version,
            'execution_path' => 'ssh-zip-url',
            'output_tail' => $this->sanitizeSensitiveMessage(substr(trim($output), -2000)),
        ];
    }

    protected function extractVersionFromReleaseFileName(string $fileName): string
    {
        if (preg_match('/_v(\d+\.\d+\.\d+)_release\.zip$/', $fileName, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^v(\d+\.\d+\.\d+)\.zip$/', $fileName, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Sync server information.
     */
    protected function syncServer(Server $server, ServerService $serverService): array
    {
        Log::info('ServerProvision: Syncing server info', ['server_id' => $server->id]);

        $result = $serverService->syncServerInfo($server);

        if (! ($result['success'] ?? false)) {
            throw new Exception('Failed to sync server info: '.($result['message'] ?? 'Unknown error'));
        }

        return [
            'summary' => $result['message'] ?? 'Server information synced successfully.',
            'updated_fields' => array_keys((array) ($result['data'] ?? [])),
        ];
    }

    /**
     * Apply PostgreSQL optimizations based on server hardware.
     *
     * Calculates recommended PG settings using server RAM/CPU info
     * and applies them via HestiaClient (a-apply-pg-config script).
     * Non-fatal: provisioning continues even if optimization fails.
     */
    protected function applyPgOptimizations(Server $server): array
    {
        Log::info('ServerProvision: Applying PostgreSQL optimizations', ['server_id' => $server->id]);

        // Refresh server to get latest metadata from sync step
        $server->refresh();

        $ramMb = (int) ($server->server_ram ?? 0);
        $cpuCores = (int) ($server->server_ccore ?? 1);

        if ($ramMb < 256) {
            Log::warning('ServerProvision: Skipping PG optimization — insufficient RAM info', [
                'server_id' => $server->id,
                'ram_mb' => $ramMb,
            ]);

            return [
                'summary' => 'Skipped — server RAM info not available.',
                'skipped' => true,
            ];
        }

        // Calculate recommendations using same logic as ServerController::calculatePgRecommendations
        $recommendations = $this->calculatePgSettings($ramMb, $cpuCores);

        // Apply via HestiaClient
        $result = HestiaClient::execute(
            'a-apply-pg-config',
            $server,
            [json_encode($recommendations)],
            120
        );

        if (! $result['success'] || empty($result['data'])) {
            $errorMsg = $result['message'] ?? 'HestiaClient execution failed';
            Log::warning('ServerProvision: PG optimization failed (non-fatal)', [
                'server_id' => $server->id,
                'error' => $errorMsg,
            ]);

            return [
                'summary' => 'PG optimization failed: '.$errorMsg,
                'success' => false,
            ];
        }

        $data = $result['data'];
        $applied = array_values(array_filter($data['applied'] ?? [], fn ($v): bool => $v !== ''));
        $failed = array_values(array_filter($data['failed'] ?? [], fn ($v): bool => $v !== ''));

        Log::info('ServerProvision: PG optimization completed', [
            'server_id' => $server->id,
            'applied' => count($applied),
            'failed' => count($failed),
            'restarted' => $data['restarted'] ?? false,
        ]);

        return [
            'summary' => sprintf(
                'Applied %d settings. %s',
                count($applied),
                $data['restarted'] ?? false ? 'PostgreSQL restarted.' : 'PostgreSQL reloaded.'
            ),
            'applied' => $applied,
            'failed' => $failed,
            'restarted' => $data['restarted'] ?? false,
        ];
    }

    /**
     * Calculate flat PG settings map for applying via a-apply-pg-config.
     *
     * Mirrors ServerController::calculatePgRecommendations() but returns
     * a flat array of setting_name => value (no descriptions).
     *
     * @return array<string, string>
     */
    protected function calculatePgSettings(int $ramMb, int $cpuCores): array
    {
        $ramGb = $ramMb / 1024;

        $sharedBuffersMb = min((int) ($ramMb * 0.25), 8192);
        $sharedBuffers = $sharedBuffersMb >= 1024
            ? round($sharedBuffersMb / 1024, 1).'GB'
            : $sharedBuffersMb.'MB';

        $effectiveCacheMb = (int) ($ramMb * 0.75);
        $effectiveCache = $effectiveCacheMb >= 1024
            ? round($effectiveCacheMb / 1024, 1).'GB'
            : $effectiveCacheMb.'MB';

        $maxConn = max(4 * $cpuCores, 100);

        $workMemMb = max(4, (int) (($ramMb - $sharedBuffersMb) / ($maxConn * 3)));

        $maintenanceWorkMemMb = min((int) ($ramMb / 16), 2048);
        $maintenanceWorkMem = $maintenanceWorkMemMb >= 1024
            ? round($maintenanceWorkMemMb / 1024, 1).'GB'
            : $maintenanceWorkMemMb.'MB';

        $walBuffersMb = max(1, min(64, (int) ($sharedBuffersMb * 0.03)));

        return [
            'max_connections' => (string) $maxConn,
            'shared_buffers' => $sharedBuffers,
            'effective_cache_size' => $effectiveCache,
            'maintenance_work_mem' => $maintenanceWorkMem,
            'checkpoint_completion_target' => '0.9',
            'wal_buffers' => $walBuffersMb.'MB',
            'default_statistics_target' => '100',
            'random_page_cost' => '1.1',
            'effective_io_concurrency' => '200',
            'work_mem' => $workMemMb.'MB',
            'huge_pages' => $ramGb >= 32 ? 'on' : 'off',
            'min_wal_size' => $ramGb >= 4 ? '1GB' : '512MB',
            'max_wal_size' => $ramGb >= 8 ? '4GB' : ($ramGb >= 4 ? '2GB' : '1GB'),
            'max_worker_processes' => (string) $cpuCores,
            'max_parallel_workers_per_gather' => (string) max(2, (int) ($cpuCores / 2)),
            'max_parallel_workers' => (string) $cpuCores,
            'max_parallel_maintenance_workers' => (string) max(2, (int) ($cpuCores / 4)),
            'wal_compression' => 'pglz',
            'wal_log_hints' => 'on',
            'checkpoint_timeout' => '15min',
            'log_checkpoints' => 'on',
            'log_temp_files' => '0',
            'log_lock_waits' => 'on',
            'idle_in_transaction_session_timeout' => '10min',
            'shared_preload_libraries' => 'pg_stat_statements',
        ];
    }

    /**
     * Detect retryable errors where releasing the job is safer than failing.
     */
    protected function shouldReleaseForRetry(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        // Update releases should fail fast instead of requeueing the entire provisioning job.
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
     * Check whether the detached installer screen session is still running.
     */
    protected function isScreenSessionRunning(Server $server, ServerSSHService $sshService, string $sessionName): bool
    {
        $sessionPattern = preg_quote($sessionName, '/');
        $result = $sshService->executeCommand(
            $server,
            sprintf("screen -ls | grep -q '[.]%s\\s' && echo 'RUNNING' || echo 'DONE'", $sessionPattern),
            10
        );

        return str_contains($result['data']['output'] ?? '', 'RUNNING');
    }

    /**
     * Check whether the detached installer screen session is still running.
     */
    protected function isInstallSessionRunning(Server $server, ServerSSHService $sshService): bool
    {
        return $this->isScreenSessionRunning($server, $sshService, self::HESTIA_INSTALL_SESSION);
    }

    /**
     * Check whether Hestia installer process exists even outside the managed screen session.
     */
    protected function isInstallProcessRunning(Server $server, ServerSSHService $sshService): bool
    {
        $result = $sshService->executeCommand(
            $server,
            "command -v pgrep >/dev/null 2>&1 && pgrep -fa '/tmp/hst-install\\.sh' | grep -v 'pgrep -fa' | grep -q . && echo 'RUNNING' || echo 'DONE'",
            10
        );

        return str_contains($result['data']['output'] ?? '', 'RUNNING');
    }

    /**
     * Determine if any installer execution is currently active on the server.
     */
    protected function isHestiaInstallerActive(Server $server, ServerSSHService $sshService): bool
    {
        if ($this->isInstallSessionRunning($server, $sshService)) {
            return true;
        }

        return $this->isInstallProcessRunning($server, $sshService);
    }

    /**
     * Normalize user-provided PHP versions to a safe comma-separated list.
     *
     * @return array<int, string>
     */
    protected function normalizePhpVersions(string $rawVersions): array
    {
        $versions = [];

        foreach (explode(',', $rawVersions) as $version) {
            $trimmed = trim($version);
            if ($trimmed === '') {
                continue;
            }

            if (! preg_match('/^\d+\.\d+$/', $trimmed)) {
                continue;
            }

            if (! in_array($trimmed, $versions, true)) {
                $versions[] = $trimmed;
            }
        }

        if ($versions === []) {
            return ['8.4'];
        }

        return $versions;
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
