<?php

namespace Modules\Platform\Tests\Unit;

use Modules\Platform\Libs\HestiaClient;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Tests\TestCase;

class HestiaClientLoggingTest extends TestCase
{
    public function test_it_logs_hestia_api_requests_in_local_environment(): void
    {
        config([
            'app.env' => 'local',
            'app.debug' => false,
        ]);

        $logPath = storage_path('logs/test-hestia-api-local.log');
        $this->configureHestiaLogChannel($logPath);

        $this->invokeLogRequest([
            'cmd' => 'a-exec',
            'arg1' => '--debug=1',
            'arg2' => 'v-list-users',
            'arg3' => 'admin',
            'hash' => 'access-key:secret-key',
        ]);

        $this->assertFileExists($logPath);

        $contents = (string) file_get_contents($logPath);

        $this->assertStringContainsString('HestiaCP API Call - v-list-users - admin', $contents);
        $this->assertStringContainsString('********:********', $contents);
        $this->assertStringNotContainsString('access-key:secret-key', $contents);
    }

    public function test_it_skips_hestia_api_request_logs_when_not_local_and_debug_disabled(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
        ]);

        $logPath = storage_path('logs/test-hestia-api-production.log');
        $this->configureHestiaLogChannel($logPath);

        $this->invokeLogRequest([
            'cmd' => 'a-exec',
            'arg1' => '--debug=0',
            'arg2' => 'v-list-users',
            'arg3' => 'admin',
            'hash' => 'access-key:secret-key',
        ]);

        $this->assertFileDoesNotExist($logPath);
    }

    private function configureHestiaLogChannel(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }

        config([
            'logging.channels.hestia_api' => [
                'driver' => 'single',
                'path' => $path,
                'level' => 'debug',
                'replace_placeholders' => true,
            ],
        ]);

        resolve(LoggerInterface::class)->forgetChannel(HestiaClient::DEBUG_LOG_CHANNEL);
    }

    private function invokeLogRequest(array $params): void
    {
        $method = new ReflectionMethod(HestiaClient::class, 'logRequest');
        $method->invoke(
            null,
            'https://example.com/api/',
            $params,
            '{"status":"success"}',
            'hestia_test_correlation'
        );
    }
}
