<?php

namespace Modules\Platform\Jobs\Concerns;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerSSHService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

trait InteractsWithServerProvisionInstall
{
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
     * Executes a-provision-server which handles:
     * - HestiaCP configuration (timezone, API settings, etc.)
     * - PHP configuration (Composer, enable functions)
     * - Astero directory setup
     * - Supervisor configuration
     */
    protected function runServerSetup(Server $server, ServerSSHService $sshService): array
    {
        Log::info('ServerProvision: Running server setup script', ['server_id' => $server->id]);

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
            'output' => substr($output, -500),
        ]);

        return [
            'summary' => 'Server setup script completed successfully.',
            'output_tail' => $outputTail,
            'log_file' => self::SERVER_SETUP_LOG_PATH,
            'reattach_cmd' => 'screen -r '.self::SERVER_SETUP_SESSION,
        ];
    }

    /**
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
            Log::info('ServerProvision: Creating local zip of hestia folder', ['server_id' => $server->id]);
            $this->createHestiaZip($hestiaDir, $localZipPath);

            Log::info('ServerProvision: Uploading zip to server', ['server_id' => $server->id]);
            $result = $sshService->uploadFile($server, $localZipPath, $remoteZipPath);
            if (! $result['success']) {
                throw new Exception('Failed to upload scripts zip: '.$result['message']);
            }

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
        $credentials = json_decode($output, true);

        if (! $credentials || empty($credentials['ACCESS_KEY_ID']) || empty($credentials['SECRET_ACCESS_KEY'])) {
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
        $hestiaResult = $sshService->executeCommand(
            $server,
            'systemctl is-active hestia 2>/dev/null || service hestia status 2>/dev/null | grep -i running',
            30
        );

        throw_unless($hestiaResult['success'], Exception::class, 'HestiaCP service is not running.');

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
}
