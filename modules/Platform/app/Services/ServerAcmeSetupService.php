<?php

namespace Modules\Platform\Services;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Str;
use Modules\Platform\Models\Server;

class ServerAcmeSetupService
{
    use ActivityTrait;

    public function __construct(
        private readonly ServerSSHService $sshService
    ) {}

    /**
     * @return array{
     *     summary: string,
     *     acme_email: string|null,
     *     already_configured?: bool,
     *     reuploaded_scripts?: bool
     * }
     */
    public function setup(Server $server, bool $force = false): array
    {
        throw_unless($server->hasSshCredentials(), Exception::class, 'SSH credentials not configured.');

        if ($server->acme_configured && ! $force) {
            return [
                'summary' => 'ACME is already configured on this server.',
                'acme_email' => $server->acme_email,
                'already_configured' => true,
            ];
        }

        if ($server->acme_configured && $force) {
            $this->uploadHelperScripts($server);

            $message = sprintf('ACME helper scripts re-uploaded on server "%s".', $server->name);
            $this->logActivity($server, ActivityAction::UPDATE, $message);

            return [
                'summary' => $message,
                'acme_email' => $server->acme_email,
                'reuploaded_scripts' => true,
            ];
        }

        $acmeEmail = $this->resolveAcmeEmail($server);

        $this->executeOrFail(
            $server,
            'id asterossl &>/dev/null || useradd --system --shell /bin/bash --create-home --home-dir /home/asterossl asterossl',
            120,
            'Failed to create asterossl user'
        );

        $this->executeOrFail(
            $server,
            sprintf(
                'sudo -u asterossl -H bash -c "cd /tmp && curl -s https://get.acme.sh | sh -s -- email=%s" 2>&1',
                escapeshellarg($acmeEmail)
            ),
            180,
            'Failed to install acme.sh'
        );

        $this->executeOrFail(
            $server,
            'sudo -u asterossl -H /home/asterossl/.acme.sh/acme.sh --set-default-ca --server letsencrypt 2>&1',
            30,
            'Failed to set default ACME CA'
        );

        $this->executeOrFail(
            $server,
            sprintf(
                'sudo -u asterossl -H /home/asterossl/.acme.sh/acme.sh --register-account --server letsencrypt -m %s 2>&1',
                escapeshellarg($acmeEmail)
            ),
            60,
            'Failed to register Let\'s Encrypt account'
        );

        $this->executeOrFail(
            $server,
            'sudo -u asterossl -H mkdir -p /home/asterossl/.ssl-store',
            30,
            'Failed to create ACME SSL store'
        );

        $this->uploadHelperScripts($server);

        $server->acme_configured = true;
        $server->acme_email = $acmeEmail;
        $server->save();

        $message = sprintf('ACME setup completed on server "%s" (email: %s).', $server->name, $acmeEmail);
        $this->logActivity($server, ActivityAction::UPDATE, $message);

        return [
            'summary' => $message,
            'acme_email' => $acmeEmail,
        ];
    }

    public function resolveAcmeEmail(Server $server): string
    {
        return Str::slug($server->name).'@astero.net.in';
    }

    private function executeOrFail(Server $server, string $command, int $timeout, string $message): void
    {
        $result = $this->sshService->executeCommand($server, $command, $timeout);

        throw_unless(
            $result['success'] ?? false,
            Exception::class,
            $message.': '.($result['message'] ?? 'Unknown error')
        );
    }

    private function uploadHelperScripts(Server $server): void
    {
        $scriptsDir = '/usr/local/hestia/data/astero/bin';
        $this->executeOrFail($server, sprintf('mkdir -p %s', $scriptsDir), 30, 'Failed to create ACME scripts directory');

        $localBinDir = base_path('hestia/bin');
        $scripts = ['a-issue-wildcard-ssl', 'a-check-wildcard-ssl', 'a-renew-wildcard-ssl'];

        foreach ($scripts as $script) {
            $localPath = $localBinDir.'/'.$script;
            $remotePath = $scriptsDir.'/'.$script;

            throw_unless(file_exists($localPath), Exception::class, sprintf('Local script not found: %s', $localPath));

            $base64Content = base64_encode((string) file_get_contents($localPath));
            $this->executeOrFail(
                $server,
                sprintf('echo %s | base64 -d > %s && chmod 755 %s', escapeshellarg($base64Content), $remotePath, $remotePath),
                30,
                sprintf('Failed to upload %s', $script)
            );
        }
    }
}
