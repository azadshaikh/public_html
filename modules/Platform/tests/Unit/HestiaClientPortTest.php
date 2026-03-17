<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Server;
use ReflectionMethod;
use Tests\TestCase;

class HestiaClientPortTest extends TestCase
{
    public function test_default_port_is_8443(): void
    {
        $this->assertSame(8443, HestiaClient::DEFAULT_PORT);
    }

    public function test_hestia_client_disables_tls_verification_for_localhost_server_type(): void
    {
        $method = new ReflectionMethod(HestiaClient::class, 'shouldVerifyTls');

        $localhostServer = new Server([
            'type' => Server::TYPE_LOCALHOST,
        ]);

        $remoteServer = new Server([
            'type' => 'production',
        ]);

        $this->assertFalse($method->invoke(null, $localhostServer, '127.0.0.1:8443'));
        $this->assertTrue($method->invoke(null, $remoteServer, '198.51.100.10:8443'));
        $this->assertFalse($method->invoke(null, ['host' => 'localhost:8443'], 'localhost:8443'));
    }

    public function test_hestia_client_prefers_fqdn_for_api_host_resolution(): void
    {
        $server = new Server([
            'fqdn' => 'prod-eu-hetzner-app-01.astero.net.in',
            'ip' => '46.225.140.214',
            'port' => 8443,
        ]);

        $this->assertSame(
            'https://prod-eu-hetzner-app-01.astero.net.in:8443/api/',
            HestiaClient::buildApiUrl($server)
        );

        $method = new ReflectionMethod(HestiaClient::class, 'resolveCredentials');

        $credentials = $method->invoke(null, $server);

        $this->assertSame('prod-eu-hetzner-app-01.astero.net.in:8443', $credentials['host']);
    }
}
