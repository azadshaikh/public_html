<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Command;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerAcmeSetupService;

/**
 * ACME setup entrypoint for a Hestia server.
 *
 * Creates the 'asterossl' user, installs acme.sh, registers a Let's Encrypt
 * account, and uploads the SSL helper scripts. This command remains available
 * for manual repair runs even though standard server provisioning now performs
 * the same setup automatically.
 */
class ServerSetupAcmeCommand extends Command
{
    protected $signature = 'platform:server:setup-acme
                            {server_id : The ID of the server to configure}
                            {--force : Re-upload scripts even if server is already configured}';

    protected $description = 'Install acme.sh and configure SSL tooling on a Hestia server.';

    public function handle(ServerAcmeSetupService $acmeSetupService): int
    {
        $serverId = $this->argument('server_id');
        $server = Server::query()->findOrFail($serverId);

        if ($server->acme_configured && ! $this->option('force')) {
            $this->info(sprintf('Server "%s" (#%d) already has acme.sh configured. Use --force to re-upload scripts.', $server->name, $server->id));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s server "%s" (#%d)...',
            $this->option('force') ? 'Refreshing ACME scripts on' : 'Setting up ACME on',
            $server->name,
            $server->id
        ));

        $result = $acmeSetupService->setup($server, (bool) $this->option('force'));
        $this->info($result['summary']);

        return self::SUCCESS;
    }
}
