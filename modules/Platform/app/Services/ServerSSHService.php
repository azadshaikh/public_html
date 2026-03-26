<?php

namespace Modules\Platform\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Modules\Platform\Models\Server;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * SSH Service for remote server operations.
 *
 * Provides SSH and SFTP functionality for:
 * - Remote command execution with output streaming
 * - File and directory uploads via SFTP
 * - Connection testing
 *
 * Uses phpseclib3 for SSH operations with key-based authentication.
 */
class ServerSSHService
{
    /**
     * Default SSH timeout in seconds.
     */
    public const DEFAULT_TIMEOUT = 300;

    /**
     * Default connection timeout in seconds.
     */
    public const DEFAULT_CONNECT_TIMEOUT = 30;

    /**
     * Test SSH connection to a server.
     */
    public function testConnection(Server $server): array
    {
        if (! $server->hasSshCredentials()) {
            return $this->errorResponse('SSH credentials not configured. IP, SSH key, and username required.');
        }

        $ssh = null;
        try {
            $ssh = $this->connect($server);

            // Run a simple command to verify connection
            $output = $ssh->exec('echo "SSH_CONNECTION_OK" && uname -a');

            if (str_contains($output, 'SSH_CONNECTION_OK')) {
                // Extract OS info
                $osInfo = trim(str_replace('SSH_CONNECTION_OK', '', $output));

                return $this->successResponse('SSH connection successful', [
                    'os_info' => $osInfo,
                    'connected' => true,
                ]);
            }

            return $this->errorResponse('SSH connection failed: unexpected response');
        } catch (Exception $exception) {
            return $this->errorResponse('SSH connection failed: '.$exception->getMessage());
        } finally {
            if ($ssh instanceof SSH2) {
                $ssh->disconnect();
            }
        }
    }

    /**
     * Execute a command on the remote server.
     */
    public function executeCommand(Server $server, string $command, ?int $timeout = null): array
    {
        $timeout ??= self::DEFAULT_TIMEOUT;

        if (! $server->hasSshCredentials()) {
            return $this->errorResponse('SSH credentials not configured');
        }

        $ssh = null;
        try {
            $ssh = $this->connect($server, $timeout);
            $ssh->setTimeout($timeout);

            if (config('app.debug')) {
                Log::debug('SSH: Executing command', [
                    'server_id' => $server->id,
                    'server_ip' => $server->ip,
                    'command' => $this->maskSensitiveData($command),
                ]);
            }

            $output = $ssh->exec($command);
            $exitCode = $ssh->getExitStatus();

            if (config('app.debug')) {
                Log::debug('SSH: Command completed', [
                    'server_id' => $server->id,
                    'exit_code' => (int) $exitCode,
                    'output' => $this->maskSensitiveOutput($output),
                ]);
            }

            if ($exitCode === 0 || $exitCode === false) {
                return $this->successResponse('Command executed successfully', [
                    'output' => $output,
                    'exit_code' => (int) $exitCode,
                ]);
            }

            return $this->errorResponse('Command failed with exit code '.$exitCode, [
                'output' => $output,
                'exit_code' => $exitCode,
            ]);
        } catch (Exception $exception) {
            Log::error('SSH: Command execution failed', [
                'server_id' => $server->id,
                'command' => $this->maskSensitiveData($command),
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Command execution failed: '.$exception->getMessage());
        } finally {
            if ($ssh instanceof SSH2) {
                $ssh->disconnect();
            }
        }
    }

    /**
     * Upload a file to the remote server.
     */
    public function uploadFile(Server $server, string $localPath, string $remotePath, int $permissions = 0644): array
    {
        if (! $server->hasSshCredentials()) {
            return $this->errorResponse('SSH credentials not configured');
        }

        if (! file_exists($localPath)) {
            return $this->errorResponse('Local file not found: '.$localPath);
        }

        $sftp = null;
        try {
            $sftp = $this->connectSftp($server);

            Log::debug('SFTP: Uploading file', [
                'server_id' => $server->id,
                'local' => $localPath,
                'remote' => $remotePath,
            ]);

            $content = file_get_contents($localPath);
            $result = $this->withSuppressedPhpseclibWarnings(fn () => $sftp->put($remotePath, $content), [
                'server_id' => $server->id,
                'remote' => $remotePath,
            ]);

            if (! $result) {
                return $this->errorResponse('Failed to upload file to '.$remotePath);
            }

            // Set permissions
            $this->withSuppressedPhpseclibWarnings(function () use ($sftp, $permissions, $remotePath): void {
                $sftp->chmod($permissions, $remotePath);
            }, [
                'server_id' => $server->id,
                'remote' => $remotePath,
            ]);

            return $this->successResponse('File uploaded successfully to '.$remotePath);
        } catch (Throwable $throwable) {
            if ($this->shouldFallbackToSshUpload($throwable)) {
                Log::warning('SFTP: Falling back to SSH file upload', [
                    'server_id' => $server->id,
                    'local' => $localPath,
                    'remote' => $remotePath,
                    'error' => $throwable->getMessage(),
                ]);

                return $this->uploadFileViaSsh($server, $localPath, $remotePath, $permissions);
            }

            Log::error('SFTP: File upload failed', [
                'server_id' => $server->id,
                'local' => $localPath,
                'remote' => $remotePath,
                'error' => $throwable->getMessage(),
            ]);

            return $this->errorResponse('File upload failed: '.$throwable->getMessage());
        } finally {
            if ($sftp instanceof SFTP) {
                $sftp->disconnect();
            }
        }
    }

    /**
     * Upload a directory recursively to the remote server.
     */
    public function uploadDirectory(Server $server, string $localDir, string $remoteDir): array
    {
        if (! $server->hasSshCredentials()) {
            return $this->errorResponse('SSH credentials not configured');
        }

        if (! is_dir($localDir)) {
            return $this->errorResponse('Local directory not found: '.$localDir);
        }

        $sftp = null;
        try {
            $sftp = $this->connectSftp($server);

            // Ensure remote directory exists
            $this->createRemoteDirectory($sftp, $remoteDir);

            $uploadedFiles = [];
            $failedFiles = [];

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($localDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                $relativePath = substr((string) $file->getPathname(), strlen($localDir) + 1);
                $remoteFile = rtrim($remoteDir, '/').'/'.$relativePath;

                if ($file->isDir()) {
                    try {
                        $this->createRemoteDirectory($sftp, $remoteFile);
                    } catch (Throwable $e) {
                        if ($this->shouldFallbackToSshUpload($e)) {
                            Log::warning('SFTP: Falling back to SSH directory upload', [
                                'server_id' => $server->id,
                                'local' => $localDir,
                                'remote' => $remoteDir,
                                'error' => $e->getMessage(),
                            ]);

                            return $this->uploadDirectoryViaSsh($server, $localDir, $remoteDir);
                        }

                        // Treat directory creation errors as non-fatal (often warnings from remote stat)
                        Log::warning('SFTP: Directory create failed (ignored)', [
                            'server_id' => $server->id,
                            'dir' => $remoteFile,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    try {
                        $content = file_get_contents($file->getPathname());
                        $result = $this->withSuppressedPhpseclibWarnings(fn () => $sftp->put($remoteFile, $content), [
                            'server_id' => $server->id,
                            'remote' => $remoteFile,
                        ]);

                        if ($result) {
                            // Make scripts executable - wrap in try-catch to prevent breaking on errors
                            try {
                                if ($this->isExecutableFile($file->getFilename())) {
                                    $this->withSuppressedPhpseclibWarnings(function () use ($sftp, $remoteFile): void {
                                        $sftp->chmod(0755, $remoteFile);
                                    }, [
                                        'server_id' => $server->id,
                                        'remote' => $remoteFile,
                                    ]);
                                } else {
                                    $this->withSuppressedPhpseclibWarnings(function () use ($sftp, $remoteFile): void {
                                        $sftp->chmod(0644, $remoteFile);
                                    }, [
                                        'server_id' => $server->id,
                                        'remote' => $remoteFile,
                                    ]);
                                }
                            } catch (Throwable $e) {
                                // chmod failed - log but don't fail the upload
                                Log::warning('SFTP: chmod failed (ignored)', [
                                    'file' => $remoteFile,
                                    'error' => $e->getMessage(),
                                ]);
                            }

                            $uploadedFiles[] = $relativePath;
                        } else {
                            $failedFiles[] = $relativePath;
                        }
                    } catch (Throwable $e) {
                        if ($this->shouldFallbackToSshUpload($e)) {
                            Log::warning('SFTP: Falling back to SSH directory upload', [
                                'server_id' => $server->id,
                                'local' => $localDir,
                                'remote' => $remoteDir,
                                'error' => $e->getMessage(),
                            ]);

                            return $this->uploadDirectoryViaSsh($server, $localDir, $remoteDir);
                        }

                        // phpseclib can emit warnings (converted to ErrorException) on stat/put/chmod.
                        // Record failure but continue so reprovision isn't blocked by one file.
                        $failedFiles[] = $relativePath;

                        Log::warning('SFTP: File upload failed (ignored)', [
                            'server_id' => $server->id,
                            'local' => $file->getPathname(),
                            'remote' => $remoteFile,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $message = sprintf(
                'Uploaded %d files to %s',
                count($uploadedFiles),
                $remoteDir
            );

            if ($failedFiles !== []) {
                $message .= sprintf(' (%d failed)', count($failedFiles));
            }

            Log::debug('SFTP: Directory upload completed', [
                'server_id' => $server->id,
                'local' => $localDir,
                'remote' => $remoteDir,
                'uploaded' => count($uploadedFiles),
                'failed' => count($failedFiles),
            ]);

            return $this->successResponse($message, [
                'uploaded' => $uploadedFiles,
                'failed' => $failedFiles,
            ]);
        } catch (Throwable $throwable) {
            if ($this->shouldFallbackToSshUpload($throwable)) {
                Log::warning('SFTP: Falling back to SSH directory upload', [
                    'server_id' => $server->id,
                    'local' => $localDir,
                    'remote' => $remoteDir,
                    'error' => $throwable->getMessage(),
                ]);

                return $this->uploadDirectoryViaSsh($server, $localDir, $remoteDir);
            }

            Log::error('SFTP: Directory upload failed', [
                'server_id' => $server->id,
                'local' => $localDir,
                'remote' => $remoteDir,
                'error' => $throwable->getMessage(),
            ]);

            return $this->errorResponse('Directory upload failed: '.$throwable->getMessage());
        } finally {
            if ($sftp instanceof SFTP) {
                $sftp->disconnect();
            }
        }
    }

    protected function uploadFileViaSsh(Server $server, string $localPath, string $remotePath, int $permissions = 0644): array
    {
        $content = file_get_contents($localPath);

        if ($content === false) {
            return $this->errorResponse('Local file not found: '.$localPath);
        }

        $command = $this->buildSshUploadCommand($content, $remotePath, $permissions);
        $result = $this->executeCommand($server, $command, 60);

        if (! ($result['success'] ?? false)) {
            return $this->errorResponse('File upload failed: '.($result['message'] ?? 'Unknown error'));
        }

        return $this->successResponse('File uploaded successfully to '.$remotePath);
    }

    protected function uploadDirectoryViaSsh(Server $server, string $localDir, string $remoteDir): array
    {
        $createDirectoryResult = $this->executeCommand(
            $server,
            'mkdir -p '.escapeshellarg($remoteDir),
            30
        );

        if (! ($createDirectoryResult['success'] ?? false)) {
            return $this->errorResponse('Directory upload failed: unable to create remote directory '.$remoteDir);
        }

        $uploadedFiles = [];
        $failedFiles = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($localDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $relativePath = substr((string) $file->getPathname(), strlen($localDir) + 1);
            $remoteFile = rtrim($remoteDir, '/').'/'.$relativePath;
            $permissions = $this->isExecutableFile($file->getFilename()) ? 0755 : 0644;
            $result = $this->uploadFileViaSsh($server, (string) $file->getPathname(), $remoteFile, $permissions);

            if ($result['success'] ?? false) {
                $uploadedFiles[] = $relativePath;

                continue;
            }

            $failedFiles[] = $relativePath;

            Log::warning('SSH: Fallback file upload failed', [
                'server_id' => $server->id,
                'local' => $file->getPathname(),
                'remote' => $remoteFile,
                'error' => $result['message'] ?? 'Unknown error',
            ]);
        }

        $message = sprintf(
            'Uploaded %d files to %s',
            count($uploadedFiles),
            $remoteDir
        );

        if ($failedFiles !== []) {
            $message .= sprintf(' (%d failed)', count($failedFiles));
        }

        return $this->successResponse($message, [
            'uploaded' => $uploadedFiles,
            'failed' => $failedFiles,
        ]);
    }

    /**
     * Detect the operating system on remote server.
     */
    public function detectOS(Server $server): array
    {
        $localScript = base_path('hestia/os-verify.sh');
        if (is_file($localScript)) {
            $remoteScript = '/tmp/astero-os-verify.sh';

            $upload = $this->uploadFile($server, $localScript, $remoteScript, 0755);
            if (! $upload['success']) {
                return $this->errorResponse('OS detection failed: script upload failed', [
                    'error' => $upload['message'] ?? null,
                ]);
            }

            $result = $this->executeCommand($server, 'bash '.$remoteScript, 30);
            $output = $result['data']['output'] ?? '';
            $parsed = $this->parseOsVerifyOutput($output);

            if ($parsed !== null) {
                return $this->successResponse('OS detected', $parsed);
            }
        } else {
            $result = $this->executeCommand($server, 'cat /etc/os-release 2>/dev/null || cat /etc/lsb-release 2>/dev/null');
            if (! $result['success']) {
                return $result;
            }

            $output = $result['data']['output'] ?? '';
            $osInfo = [
                'distro' => 'unknown',
                'version' => 'unknown',
                'supported' => false,
            ];

            // Parse OS release info - use ^ anchor to match start of line for ID=
            if (preg_match('/^ID=([^\n]+)/m', $output, $matches)) {
                $osInfo['distro'] = trim($matches[1], '"');
            }

            if (preg_match('/^VERSION_ID=([^\n]+)/m', $output, $matches)) {
                $osInfo['version'] = trim($matches[1], '"');
            }

            // Check if supported (Debian 11/12, Ubuntu 22.04/24.04)
            $distro = strtolower($osInfo['distro']);
            $version = $osInfo['version'];

            if ($distro === 'ubuntu' && in_array($version, ['22.04', '24.04'], true)) {
                $osInfo['supported'] = true;
            } elseif ($distro === 'debian' && in_array($version, ['11', '12'], true)) {
                $osInfo['supported'] = true;
            }

            return $this->successResponse('OS detected', $osInfo);
        }

        return $this->errorResponse('OS detection failed: unexpected output', [
            'output' => $result['data']['output'] ?? null,
        ]);
    }

    /**
     * Check if HestiaCP is already installed.
     */
    public function isHestiaInstalled(Server $server): array
    {
        $result = $this->executeCommand(
            $server,
            'test -d /usr/local/hestia && test -f /usr/local/hestia/conf/hestia.conf && echo "INSTALLED" || echo "NOT_INSTALLED"'
        );

        if (! $result['success']) {
            return $result;
        }

        $installed = str_contains($result['data']['output'] ?? '', 'INSTALLED')
            && ! str_contains($result['data']['output'] ?? '', 'NOT_INSTALLED');

        return $this->successResponse(
            $installed ? 'HestiaCP is installed' : 'HestiaCP is not installed',
            ['installed' => $installed]
        );
    }

    /**
     * Check if Astero scripts are installed.
     */
    public function isAsteroInstalled(Server $server): array
    {
        $result = $this->executeCommand(
            $server,
            'test -f /usr/local/hestia/bin/a-sync-releases && echo "INSTALLED" || echo "NOT_INSTALLED"'
        );

        if (! $result['success']) {
            return $result;
        }

        $installed = str_contains($result['data']['output'] ?? '', 'INSTALLED')
            && ! str_contains($result['data']['output'] ?? '', 'NOT_INSTALLED');

        return $this->successResponse(
            $installed ? 'Astero scripts are installed' : 'Astero scripts are not installed',
            ['installed' => $installed]
        );
    }

    /**
     * Reboot the server and wait for it to come back online.
     *
     * @param  Server  $server  The server to reboot
     * @param  int  $pollInterval  Seconds between connection attempts (default: 15)
     * @param  int  $maxWait  Maximum seconds to wait for server to come back (default: 300 = 5 min)
     */
    public function rebootServer(Server $server, int $pollInterval = 15, int $maxWait = 300): array
    {
        Log::info('SSH: Rebooting server', [
            'server_id' => $server->id,
            'server_ip' => $server->ip,
        ]);

        // Send reboot command (nohup ensures it runs even after SSH disconnects)
        try {
            $ssh = $this->connect($server, 10);
            $ssh->setTimeout(10);

            // Use nohup and background the reboot to ensure it executes
            $ssh->exec('nohup /sbin/reboot &');
            $ssh->disconnect();
        } catch (Exception) {
            // Connection may drop during reboot - that's expected
            Log::debug('SSH: Connection dropped during reboot (expected)', [
                'server_id' => $server->id,
            ]);
        }

        // Wait a bit for server to actually go down
        Sleep::sleep(10);

        // Poll for server to come back online
        $startTime = time();
        $elapsed = 0;

        while ($elapsed < $maxWait) {
            Sleep::sleep($pollInterval);
            $elapsed = time() - $startTime;

            Log::debug('SSH: Polling for server after reboot', [
                'server_id' => $server->id,
                'elapsed_seconds' => $elapsed,
            ]);

            // Try to connect
            try {
                $result = $this->testConnection($server);
                if ($result['success']) {
                    Log::info('SSH: Server back online after reboot', [
                        'server_id' => $server->id,
                        'elapsed_seconds' => $elapsed,
                    ]);

                    return $this->successResponse('Server rebooted successfully', [
                        'downtime_seconds' => $elapsed,
                    ]);
                }
            } catch (Exception) {
                // Still not available, continue polling
            }
        }

        return $this->errorResponse(
            sprintf('Server did not come back online after %d seconds', $maxWait),
            ['timeout_seconds' => $maxWait]
        );
    }

    /**
     * Parse output from os-verify.sh.
     */
    protected function parseOsVerifyOutput(string $output): ?array
    {
        $lines = preg_split('/\r?\n/', trim($output));
        $parsed = [
            'distro' => null,
            'version' => null,
            'supported' => null,
        ];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'DISTRO=')) {
                $parsed['distro'] = substr($line, strlen('DISTRO='));
            } elseif (str_starts_with($line, 'VERSION=')) {
                $parsed['version'] = substr($line, strlen('VERSION='));
            } elseif (str_starts_with($line, 'SUPPORTED=')) {
                $value = substr($line, strlen('SUPPORTED='));
                $parsed['supported'] = $value === 'true';
            }
        }

        if ($parsed['distro'] === null || $parsed['version'] === null || $parsed['supported'] === null) {
            return null;
        }

        return $parsed;
    }

    /**
     * Establish SSH connection to server.
     */
    protected function connect(Server $server, ?int $timeout = null): SSH2
    {
        $timeout ??= self::DEFAULT_CONNECT_TIMEOUT;

        $ssh = new SSH2($server->ip, (int) $server->ssh_port);
        $ssh->setTimeout($timeout);

        $sshPrivateKey = $server->getSshPrivateKeyForConnection();
        throw_if($sshPrivateKey === null, Exception::class, 'SSH private key is missing for this server.');

        $key = PublicKeyLoader::load($sshPrivateKey);

        throw_unless($ssh->login($server->ssh_user, $key), Exception::class, 'SSH authentication failed. Check your SSH key and username.');

        return $ssh;
    }

    /**
     * Establish SFTP connection to server.
     */
    protected function connectSftp(Server $server): SFTP
    {
        $sftp = new SFTP($server->ip, (int) $server->ssh_port);
        $sftp->setTimeout(self::DEFAULT_CONNECT_TIMEOUT);

        $sshPrivateKey = $server->getSshPrivateKeyForConnection();
        throw_if($sshPrivateKey === null, Exception::class, 'SSH private key is missing for this server.');

        $key = PublicKeyLoader::load($sshPrivateKey);

        $loggedIn = $this->withSuppressedPhpseclibWarnings(fn () => $sftp->login($server->ssh_user, $key), [
            'server_id' => $server->id,
        ]);

        throw_unless($loggedIn, Exception::class, 'SFTP authentication failed. Check your SSH key and username.');

        return $sftp;
    }

    /**
     * Create remote directory recursively.
     */
    protected function createRemoteDirectory(SFTP $sftp, string $path): bool
    {
        try {
            // Check if directory exists - catch any errors from stat
            $isDir = false;
            try {
                $isDir = (bool) $this->withSuppressedPhpseclibWarnings(fn () => $sftp->is_dir($path), [
                    'path' => $path,
                ]);
            } catch (Throwable) {
                // is_dir failed - directory probably doesn't exist, continue
            }

            if ($isDir) {
                return true;
            }

            // Create parent directories first
            $parent = dirname($path);
            if ($parent !== '/' && $parent !== '.') {
                $parentIsDir = false;
                try {
                    $parentIsDir = (bool) $this->withSuppressedPhpseclibWarnings(fn () => $sftp->is_dir($parent), [
                        'path' => $parent,
                    ]);
                } catch (Throwable) {
                    // Ignore errors
                }

                if (! $parentIsDir) {
                    $this->createRemoteDirectory($sftp, $parent);
                }
            }

            // Try to create directory, ignore failure if it already exists
            try {
                return (bool) $this->withSuppressedPhpseclibWarnings(fn () => $sftp->mkdir($path, 0755, true), [
                    'path' => $path,
                ]);
            } catch (Throwable) {
                // mkdir may fail if directory already exists - that's OK
                return true;
            }
        } catch (Throwable $throwable) {
            // Catch-all - return true to not break the upload
            Log::warning('SFTP: createRemoteDirectory error (ignored)', [
                'path' => $path,
                'error' => $throwable->getMessage(),
            ]);

            return true;
        }
    }

    protected function shouldFallbackToSshUpload(Throwable|string $throwable): bool
    {
        $message = $throwable instanceof Throwable ? $throwable->getMessage() : $throwable;

        return str_contains($message, 'Expected NET_SFTP_VERSION')
            || str_contains($message, 'Unable to request a SFTP subsystem')
            || str_contains($message, 'subsystem request failed');
    }

    protected function buildSshUploadCommand(string $content, string $remotePath, int $permissions): string
    {
        return sprintf(
            'mkdir -p %s && printf %%s %s | base64 -d > %s && chmod %s %s',
            escapeshellarg(dirname($remotePath)),
            escapeshellarg(base64_encode($content)),
            escapeshellarg($remotePath),
            escapeshellarg(sprintf('%o', $permissions)),
            escapeshellarg($remotePath)
        );
    }

    /**
     * phpseclib can emit warnings (not exceptions) that Laravel converts into ErrorException.
     * We suppress the known noisy warning so uploads don't fail during reprovision.
     */
    protected function withSuppressedPhpseclibWarnings(callable $callback, array $context = [])
    {
        set_error_handler(
            // Silently suppress this known phpseclib/server issue (non-standard status code 256)
            // We don't log it to avoid cluttering logs and confusing users since it's handled.
            fn (int $severity, string $message, string $file, int $line): bool => str_contains($message, 'Undefined array key 256') && str_contains($file, 'phpseclib')
        );

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Check if a file should be executable.
     */
    protected function isExecutableFile(string $filename): bool
    {
        // Shell scripts and Astero bin scripts (a-* prefix)
        // Check shebang in content would be overkill here
        return str_ends_with($filename, '.sh') || str_starts_with($filename, 'a-');
    }

    /**
     * Mask sensitive data in commands for logging.
     */
    protected function maskSensitiveData(string $command): string
    {
        // Mask passwords and keys in common patterns
        $patterns = [
            '/password[=:]\s*[\'"]?([^\s\'"]+)/i' => 'password=********',
            '/secret[=:]\s*[\'"]?([^\s\'"]+)/i' => 'secret=********',
            '/key[=:]\s*[\'"]?([^\s\'"]+)/i' => 'key=********',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $command);
    }

    protected function maskSensitiveOutput(string $output): string
    {
        $patterns = [
            '/(SECRET_ACCESS_KEY[:=]\s*)([^\s]+)/i' => '$1[REDACTED]',
            '/(ACCESS_KEY_ID[:=]\s*)([^\s]+)/i' => '$1[REDACTED]',
            '/(password[:=]\s*)([^\s]+)/i' => '$1[REDACTED]',
            '/(release[_\s-]*api[_\s-]*key[:=]\s*)([^\s]+)/i' => '$1[REDACTED]',
        ];

        $sanitized = preg_replace(array_keys($patterns), array_values($patterns), $output);

        return substr((string) $sanitized, 0, 4000);
    }

    /**
     * Create success response array.
     */
    protected function successResponse(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Create error response array.
     */
    protected function errorResponse(string $message, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];
    }
}
