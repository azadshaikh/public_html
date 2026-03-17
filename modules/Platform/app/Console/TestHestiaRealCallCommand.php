<?php

namespace Modules\Platform\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Server;

class TestHestiaRealCallCommand extends Command
{
    protected $signature = 'hestia:test-real-call {server_id?}';

    protected $description = 'Test an actual HestiaCP API call to find which server is failing';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Testing REAL HestiaCP API Calls');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        // Get all servers or specific server
        if ($serverId = $this->argument('server_id')) {
            $servers = Server::query()->where('id', $serverId)->get();
        } else {
            $servers = Server::all();
        }

        /** @var Collection<int, Server> $servers */
        if ($servers->isEmpty()) {
            $this->error('No servers found');

            return 1;
        }

        $this->line('Found '.$servers->count().' server(s) to test');
        $this->newLine();

        foreach ($servers as $server) {
            $this->testServer($server);
            $this->newLine();
        }

        return 0;
    }

    private function testServer(Server $server): void
    {
        $this->info(sprintf('Testing Server #%d: %s', $server->id, $server->name));
        $this->line('  IP: '.($server->ip ?: 'NOT SET'));
        $this->line('  FQDN: '.($server->fqdn ?: 'NOT SET'));
        $this->line('  Port: '.($server->port ?: '8443 (default)'));
        $this->line('  Access Key: '.($server->access_key_id ? 'SET (****)' : 'NOT SET'));
        $this->line('  Secret Key: '.($server->access_key_secret ? 'SET (****)' : 'NOT SET'));

        // Build URL
        $url = HestiaClient::buildApiUrl($server);
        $this->line('  URL: '.($url ?: 'CANNOT BUILD - Missing IP/FQDN'));
        $this->newLine();

        // Check if we can build URL
        if (! $url) {
            $this->error('  ✗ Cannot test - server missing IP or FQDN');

            return;
        }

        // Check if we have credentials
        if (empty($server->access_key_id) || empty($server->access_key_secret)) {
            $this->warn('  ⚠ Cannot test API call - missing access keys');
            $this->line('  Testing basic connectivity only...');

            try {
                $response = Http::withOptions([
                    'verify' => false,
                    'timeout' => 10,
                    'connect_timeout' => 5,
                ])->get($url);

                $this->info(sprintf('  ✓ Basic connection works (HTTP %d)', $response->status()));
            } catch (Exception $e) {
                $this->error('  ✗ Connection failed');
                $this->error('  Error: '.$e->getMessage());
            }

            return;
        }

        // Make actual API call
        $this->line('  Making API call: v-list-users admin json');

        try {
            $start = microtime(true);
            $response = HestiaClient::execute('v-list-users', $server, ['admin', 'json']);
            $duration = round((microtime(true) - $start) * 1000, 2);

            if ($response['success']) {
                $this->info(sprintf('  ✓ API call successful in %sms', $duration));
                $this->line('  Message: '.$response['message']);

                if (! empty($response['data'])) {
                    $dataPreview = json_encode($response['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    if (strlen($dataPreview) > 500) {
                        $dataPreview = substr($dataPreview, 0, 500).'...';
                    }

                    $this->line('  Data preview: '.$dataPreview);
                }
            } else {
                $this->error(sprintf('  ✗ API call failed in %sms', $duration));
                $this->error('  Message: '.$response['message']);
                $this->error('  Code: '.$response['code']);

                // Check if this is the connectivity error
                if (str_contains((string) $response['message'], 'Failed to connect')) {
                    $this->newLine();
                    $this->warn('  🔍 THIS IS THE FAILING SERVER!');
                    $this->warn('  The error message indicates network connectivity issue.');
                    $this->warn('  But our diagnostics show this IP/port is accessible...');
                    $this->newLine();
                    $this->line('  Possible issues:');
                    $this->line('  1. IP/FQDN in database is incorrect or outdated');
                    $this->line('  2. Port in database is incorrect');
                    $this->line('  3. Server configuration changed recently');
                    $this->line('  4. DNS issue if using FQDN');
                    $this->newLine();
                    $this->line('  To debug further, run:');
                    $this->line('  php artisan hestia:diagnose-network '.($server->ip ?: $server->fqdn).' --port='.($server->port ?: 8443));
                }
            }
        } catch (Exception $exception) {
            $this->error('  ✗ Exception occurred');
            $this->error('  '.$exception->getMessage());
            $this->line('  '.$exception->getFile().':'.$exception->getLine());
        }
    }
}
