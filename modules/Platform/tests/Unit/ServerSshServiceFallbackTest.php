<?php

namespace Modules\Platform\Tests\Unit;

use Mockery;
use Mockery\MockInterface;
use Modules\Platform\Models\Server;
use Modules\Platform\Services\ServerSSHService;
use phpseclib3\Net\SFTP;
use RuntimeException;
use Tests\TestCase;

class ServerSshServiceFallbackTest extends TestCase
{
    public function test_upload_file_falls_back_to_ssh_when_sftp_transport_is_broken(): void
    {
        $localPath = tempnam(sys_get_temp_dir(), 'ssh-fallback-file-');
        $this->assertNotFalse($localPath);
        file_put_contents($localPath, "#!/bin/bash\necho ok\n");

        $server = new Server([
            'id' => 1,
            'ip' => '192.0.2.10',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'ssh_private_key' => 'dummy-key',
        ]);

        /** @var ServerSSHService&MockInterface $service */
        $service = Mockery::mock(ServerSSHService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('connectSftp')
            ->once()
            ->andThrow(new RuntimeException('Expected NET_SFTP_VERSION. Got packet type: '));
        $service->shouldReceive('executeCommand')
            ->once()
            ->withArgs(function (Server $passedServer, string $command, ?int $timeout): bool {
                $this->assertSame('192.0.2.10', $passedServer->ip);
                $this->assertStringContainsString("base64 -d > '/usr/local/hestia/bin/a-debug-test'", $command);
                $this->assertStringContainsString("chmod '755' '/usr/local/hestia/bin/a-debug-test'", $command);
                $this->assertSame(60, $timeout);

                return true;
            })
            ->andReturn([
                'success' => true,
                'message' => 'Command executed successfully',
                'data' => [],
            ]);

        $result = $service->uploadFile($server, $localPath, '/usr/local/hestia/bin/a-debug-test', 0755);

        $this->assertTrue($result['success']);
        $this->assertSame('File uploaded successfully to /usr/local/hestia/bin/a-debug-test', $result['message']);

        unlink($localPath);
    }

    public function test_upload_directory_falls_back_to_ssh_when_sftp_put_throws_transport_error(): void
    {
        $localDir = sys_get_temp_dir().'/ssh-fallback-dir-'.uniqid();
        mkdir($localDir, 0777, true);
        mkdir($localDir.'/nested', 0777, true);
        file_put_contents($localDir.'/a-debug-test', "#!/bin/bash\necho ok\n");
        file_put_contents($localDir.'/nested/astero-active.tpl', "server {\n}\n");

        $server = new Server([
            'id' => 2,
            'ip' => '192.0.2.20',
            'ssh_user' => 'root',
            'ssh_port' => 22,
            'ssh_private_key' => 'dummy-key',
        ]);

        /** @var SFTP&MockInterface $sftp */
        $sftp = Mockery::mock(SFTP::class);
        $sftp->shouldReceive('is_dir')->andReturn(true);
        $sftp->shouldReceive('put')
            ->once()
            ->andThrow(new RuntimeException('Expected NET_SFTP_VERSION. Got packet type: '));
        $sftp->shouldReceive('disconnect')->once();

        /** @var ServerSSHService&MockInterface $service */
        $service = Mockery::mock(ServerSSHService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('connectSftp')->once()->andReturn($sftp);
        $service->shouldReceive('executeCommand')
            ->times(3)
            ->andReturn([
                'success' => true,
                'message' => 'Command executed successfully',
                'data' => [],
            ]);

        $result = $service->uploadDirectory($server, $localDir, '/usr/local/hestia/bin');

        sort($result['data']['uploaded']);

        $this->assertTrue($result['success']);
        $this->assertSame([
            'a-debug-test',
            'nested/astero-active.tpl',
        ], $result['data']['uploaded']);
        $this->assertSame([], $result['data']['failed']);

        unlink($localDir.'/nested/astero-active.tpl');
        unlink($localDir.'/a-debug-test');
        rmdir($localDir.'/nested');
        rmdir($localDir);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
