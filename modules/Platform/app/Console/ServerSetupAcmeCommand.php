<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerSSHService;

/**
 * One-time acme.sh setup on a Hestia server.
 *
 * Creates the 'asterossl' user, installs acme.sh, registers a Let's Encrypt
 * account, and uploads the SSL helper scripts. This is a standalone command
 * (not a provisioning step) — run it once per server before provisioning websites.
 */
class ServerSetupAcmeCommand extends Command
{
    use ActivityTrait;

    protected $signature = 'platform:server:setup-acme
                            {server_id : The ID of the server to configure}
                            {--force : Re-upload scripts even if server is already configured}';

    protected $description = 'Install acme.sh and configure SSL tooling on a Hestia server.';

    public function handle(ServerSSHService $sshService): int
    {
        $serverId = $this->argument('server_id');
        $server = Server::query()->findOrFail($serverId);

        if ($server->acme_configured && ! $this->option('force')) {
            $this->info(sprintf('Server "%s" (#%d) already has acme.sh configured. Use --force to re-upload scripts.', $server->name, $server->id));

            return self::SUCCESS;
        }

        // If already configured but --force is set, skip setup steps and only re-upload scripts
        if ($server->acme_configured && $this->option('force')) {
            $this->info(sprintf('Re-uploading SSL scripts to server "%s" (#%d)...', $server->name, $server->id));

            $this->uploadHelperScripts($sshService, $server);

            $this->info('✅ Scripts re-uploaded successfully.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Setting up acme.sh on server "%s" (#%d)...', $server->name, $server->id));

        // Derive email from server name: "Hestia SG1" → "hestia-sg1@astero.net.in"
        $acmeEmail = Str::slug($server->name).'@astero.net.in';

        // Step 1: Create asterossl user (idempotent — ignores if already exists)
        $this->line('Creating asterossl user...');
        $result = $sshService->executeCommand(
            $server,
            'id asterossl &>/dev/null || useradd --system --shell /bin/bash --create-home --home-dir /home/asterossl asterossl',
            120
        );
        throw_unless($result['success'], Exception::class, 'Failed to create asterossl user: '.($result['message'] ?? 'Unknown error'));

        // Step 2: Install acme.sh under asterossl user
        $this->line('Installing acme.sh...');
        $result = $sshService->executeCommand(
            $server,
            sprintf(
                'sudo -u asterossl -H bash -c "cd /tmp && curl -s https://get.acme.sh | sh -s -- email=%s" 2>&1',
                escapeshellarg($acmeEmail)
            ),
            180
        );
        throw_unless($result['success'], Exception::class, 'Failed to install acme.sh: '.($result['message'] ?? 'Unknown error'));

        // Step 3: Set default CA to Let's Encrypt and register account
        $this->line('Setting default CA to Let\'s Encrypt...');
        $result = $sshService->executeCommand(
            $server,
            'sudo -u asterossl -H /home/asterossl/.acme.sh/acme.sh --set-default-ca --server letsencrypt 2>&1',
            30
        );
        throw_unless($result['success'], Exception::class, 'Failed to set default CA: '.($result['message'] ?? 'Unknown error'));

        $this->line('Registering Let\'s Encrypt account...');
        $result = $sshService->executeCommand(
            $server,
            sprintf(
                'sudo -u asterossl -H /home/asterossl/.acme.sh/acme.sh --register-account --server letsencrypt -m %s 2>&1',
                escapeshellarg($acmeEmail)
            ),
            60
        );
        throw_unless($result['success'], Exception::class, 'Failed to register LE account: '.($result['message'] ?? 'Unknown error'));

        // Step 5: Create cert storage directory
        $this->line('Creating SSL store directory...');
        $result = $sshService->executeCommand($server, 'sudo -u asterossl -H mkdir -p /home/asterossl/.ssl-store', 30);
        throw_unless($result['success'], Exception::class, 'Failed to create .ssl-store: '.($result['message'] ?? 'Unknown error'));

        // Step 6: Upload helper scripts via SSH (base64 encoding — SFTP is unavailable on Hestia)
        $this->line('Uploading SSL helper scripts...');
        $this->uploadHelperScripts($sshService, $server);

        // Mark server as acme-configured
        $server->acme_configured = true;
        $server->acme_email = $acmeEmail;
        $server->save();

        $successMessage = sprintf('acme.sh setup completed on server "%s" (email: %s)', $server->name, $acmeEmail);
        $this->logActivity($server, ActivityAction::UPDATE, $successMessage);
        $this->info('✅ '.$successMessage);

        return self::SUCCESS;
    }

    /**
     * Upload SSL helper scripts to the server via SSH (base64 encoded).
     */
    private function uploadHelperScripts(ServerSSHService $sshService, Server $server): void
    {
        $scriptsDir = '/usr/local/hestia/data/astero/bin';
        $result = $sshService->executeCommand($server, sprintf('mkdir -p %s', $scriptsDir), 30);
        throw_unless($result['success'], Exception::class, 'Failed to create scripts directory: '.($result['message'] ?? 'Unknown error'));

        $localBinDir = base_path('hestia/bin');
        $scripts = ['a-issue-wildcard-ssl', 'a-check-wildcard-ssl', 'a-renew-wildcard-ssl'];

        foreach ($scripts as $script) {
            $localPath = $localBinDir.'/'.$script;
            $remotePath = $scriptsDir.'/'.$script;

            throw_unless(file_exists($localPath), Exception::class, sprintf('Local script not found: %s', $localPath));

            $base64Content = base64_encode(file_get_contents($localPath));
            $uploadResult = $sshService->executeCommand(
                $server,
                sprintf('echo %s | base64 -d > %s && chmod 755 %s', escapeshellarg($base64Content), $remotePath, $remotePath),
                30
            );
            throw_unless($uploadResult['success'], Exception::class, sprintf('Failed to upload %s: %s', $script, $uploadResult['message'] ?? 'Unknown error'));
        }
    }
}
