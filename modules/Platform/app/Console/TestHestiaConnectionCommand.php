<?php

namespace Modules\Platform\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Server;

class TestHestiaConnectionCommand extends Command
{
    protected $signature = 'hestia:test-connection {server_id?}';

    protected $description = 'Test HestiaCP API connection and diagnose issues';

    public function handle(): int
    {
        $serverId = $this->argument('server_id');

        if (! $serverId) {
            // List all servers
            $servers = Server::query()->select('id', 'name', 'ip', 'port', 'status')->get();
            /** @var Collection<int, Server> $servers */
            if ($servers->isEmpty()) {
                $this->error('No servers found in database');

                return 1;
            }

            $this->info('Available servers:');
            $this->table(
                ['ID', 'Name', 'IP', 'Port', 'Status'],
                $servers->map(fn (Server $s): array => [$s->id, $s->name, $s->ip, $s->port ?: '8443', $s->status])
            );

            $serverId = $this->ask('Enter server ID to test');
        }

        /** @var Server|null $server */
        $server = Server::query()->find($serverId);

        if (! $server) {
            $this->error(sprintf('Server with ID %s not found', $serverId));

            return 1;
        }

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  HestiaCP Connection Test');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        // Server details
        $this->info('Server Details:');
        $this->line('  Name: '.$server->name);
        $this->line('  IP: '.$server->ip);
        $this->line('  Port: '.($server->port ?: '8443'));
        $this->line('  FQDN: '.($server->fqdn ?: 'Not set'));
        $this->line('  Status: '.$server->status);
        $this->newLine();

        // Build API URL
        $url = HestiaClient::buildApiUrl($server);
        if (! $url) {
            $this->error('Cannot build API URL - server missing IP/FQDN');

            return 1;
        }

        $this->info('API URL: '.$url);
        $this->newLine();

        // Test 1: Basic HTTP connectivity
        $this->info('[Test 1] Basic HTTPS Connection...');
        try {
            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 10,
                'connect_timeout' => 5,
            ])->get($url);

            $this->info(sprintf('  ✓ HTTP connection successful (Status: %d)', $response->status()));
        } catch (Exception $exception) {
            $this->error('  ✗ HTTP connection failed');
            $this->error('  Error: '.$exception->getMessage());

            // Provide specific hints based on error
            if (str_contains($exception->getMessage(), 'Could not resolve host')) {
                $this->warn('  → Check DNS resolution or use IP address instead of FQDN');
            } elseif (str_contains($exception->getMessage(), 'Connection timed out')) {
                $this->warn('  → Check if HestiaCP server is running and accessible');
                $this->warn('  → Verify firewall allows connections on port '.($server->port ?: '8443'));
            } elseif (str_contains($exception->getMessage(), 'Connection refused')) {
                $this->warn('  → HestiaCP service may not be running');
                $this->warn('  → Check: sudo systemctl status hestia');
            } elseif (str_contains($exception->getMessage(), "Couldn't connect to server")) {
                $this->warn('  → Network connectivity issue');
                $this->warn('  → Run: nc -zv '.$server->ip.' '.($server->port ?: '8443'));
            }

            $this->newLine();
            $this->warn('Run the debug script for detailed diagnosis:');
            $this->line('  ./scripts/debug-hestia-connection.sh '.$server->ip);

            return 1;
        }

        $this->newLine();

        // Test 2: API Authentication
        $this->info('[Test 2] HestiaCP API Call (v-list-users)...');

        if (empty($server->access_key_id) || empty($server->access_key_secret)) {
            $this->warn('  ⚠ Access keys not configured for this server');
            $this->line('  Set access_key_id and access_key_secret in the servers table');

            return 1;
        }

        $apiResponse = HestiaClient::execute('v-list-users', $server, ['admin', 'json']);

        if ($apiResponse['success']) {
            $this->info('  ✓ API call successful!');
            $this->info('  Message: '.$apiResponse['message']);

            if (! empty($apiResponse['data'])) {
                $this->newLine();
                $this->info('  Response data:');
                $this->line('  '.json_encode($apiResponse['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        } else {
            $this->error('  ✗ API call failed');
            $this->error('  Message: '.$apiResponse['message']);
            $this->error('  Code: '.$apiResponse['code']);

            // Provide hints based on error code
            if ($apiResponse['code'] === 9) {
                $this->warn('  → Invalid access keys - verify access_key_id and access_key_secret');
            } elseif ($apiResponse['code'] === 10) {
                $this->warn('  → Access denied - check user permissions');
            } elseif ($apiResponse['code'] === 3) {
                $this->warn('  → Object does not exist (may be normal for test)');
            }

            return 1;
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  ✓ All tests passed! Connection is working.');
        $this->info('═══════════════════════════════════════════════════════');

        return 0;
    }
}
