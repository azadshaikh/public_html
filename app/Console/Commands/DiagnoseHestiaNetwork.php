<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DiagnoseHestiaNetwork extends Command
{
    protected $signature = 'hestia:diagnose-network {ip} {--port=8443}';

    protected $description = 'Deep network diagnostics for HestiaCP connectivity';

    public function handle(): void
    {
        $ip = $this->argument('ip');
        $port = $this->option('port');
        $url = sprintf('https://%s:%s/api/', $ip, $port);

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  HestiaCP Network Diagnostics');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();
        $this->line('Target: '.$url);
        $this->line('PHP Version: '.phpversion());
        $this->line('cURL Version: '.curl_version()['version']);
        $this->newLine();

        // Test 1: Basic connectivity with verbose error handling
        $this->info('[1] Testing basic HTTPS connection...');
        $this->testBasicConnection($url);
        $this->newLine();

        // Test 2: Direct cURL test
        $this->info('[2] Testing with raw cURL...');
        $this->testRawCurl($ip, $port);
        $this->newLine();

        // Test 3: Different timeout values
        $this->info('[3] Testing with different timeout values...');
        $this->testTimeouts($url);
        $this->newLine();

        // Test 4: Check if HTTP (non-SSL) works
        $this->info('[4] Testing HTTP (non-SSL) connection...');
        $this->testNonSsl($ip, $port);
        $this->newLine();

        // Test 5: DNS resolution (if using domain)
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->info('[5] Testing DNS resolution...');
            $this->testDns($ip);
            $this->newLine();
        }

        // Test 6: Check PHP configuration
        $this->info('[6] PHP/cURL Configuration...');
        $this->checkPhpConfig();
        $this->newLine();

        // Test 7: Environment details
        $this->info('[7] Environment Details...');
        $this->checkEnvironment($ip, $port);
        $this->newLine();

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('Diagnosis complete. Check the output above for issues.');
        $this->info('═══════════════════════════════════════════════════════');
    }

    private function testBasicConnection(string $url): void
    {
        $start = microtime(true);

        try {
            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 10,
                'connect_timeout' => 5,
                'debug' => false,
            ])->get($url);

            $duration = round((microtime(true) - $start) * 1000, 2);

            $this->info(sprintf('  ✓ Connection successful in %sms', $duration));
            $this->line('  Status: '.$response->status());
            $this->line('  Headers: '.count($response->headers()).' headers received');
        } catch (ConnectionException $e) {
            $duration = round((microtime(true) - $start) * 1000, 2);
            $this->error(sprintf('  ✗ Connection failed after %sms', $duration));
            $this->error('  Error: '.$e->getMessage());

            // Parse cURL error code
            if (preg_match('/cURL error (\d+)/', $e->getMessage(), $matches)) {
                $curlCode = $matches[1];
                $this->warn('  cURL Error Code: '.$curlCode);
                $this->line('  Meaning: '.$this->getCurlErrorMeaning($curlCode));
            }
        } catch (Exception $e) {
            $this->error('  ✗ Unexpected error: '.$e->getMessage());
        }
    }

    private function testRawCurl($ip, $port): void
    {
        $url = sprintf('https://%s:%s/api/', $ip, $port);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
        ]);

        $start = microtime(true);
        curl_exec($ch);
        $duration = round((microtime(true) - $start) * 1000, 2);

        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($errno === 0) {
            $this->info(sprintf('  ✓ Raw cURL successful in %sms', $duration));
            $this->line('  HTTP Code: '.$info['http_code']);
            $this->line('  Total Time: '.round($info['total_time'] * 1000, 2).'ms');
            $this->line('  Connect Time: '.round($info['connect_time'] * 1000, 2).'ms');
        } else {
            $this->error('  ✗ Raw cURL failed');
            $this->error('  Error Code: '.$errno);
            $this->error('  Error: '.$error);
            $this->line('  '.$this->getCurlErrorMeaning($errno));
        }
    }

    private function testTimeouts(string $url): void
    {
        $timeouts = [1, 3, 5, 10];

        foreach ($timeouts as $timeout) {
            $start = microtime(true);

            try {
                Http::timeout($timeout)
                    ->withOptions(['verify' => false])
                    ->get($url);
                $duration = round((microtime(true) - $start) * 1000, 2);

                $this->info(sprintf('  ✓ %ss timeout: Success in %sms', $timeout, $duration));
                break;
            } catch (Exception) {
                $duration = round((microtime(true) - $start) * 1000, 2);
                $this->line(sprintf('  ✗ %ss timeout: Failed after %sms', $timeout, $duration));
            }
        }
    }

    private function testNonSsl(string $ip, $port): void
    {
        $httpUrl = sprintf('http://%s:%s/api/', $ip, $port);

        try {
            Http::timeout(5)->get($httpUrl);
            $this->info('  ✓ HTTP (non-SSL) works!');
            $this->warn('  → HestiaCP should use HTTPS, but HTTP connectivity confirms network path is OK');
        } catch (Exception $exception) {
            $this->line('  ✗ HTTP also fails: '.$exception->getMessage());
        }
    }

    private function testDns($hostname): void
    {
        $ip = gethostbyname($hostname);

        if ($ip === $hostname) {
            $this->error('  ✗ DNS resolution failed');
        } else {
            $this->info('  ✓ Resolved to: '.$ip);
        }
    }

    private function checkPhpConfig(): void
    {
        $checks = [
            'allow_url_fopen' => ini_get('allow_url_fopen'),
            'default_socket_timeout' => ini_get('default_socket_timeout'),
            'max_execution_time' => ini_get('max_execution_time'),
            'disable_functions' => ini_get('disable_functions') ?: 'none',
        ];

        foreach ($checks as $key => $value) {
            $this->line(sprintf('  %s: %s', $key, $value));
        }

        // Check if cURL is enabled
        if (function_exists('curl_version')) {
            $version = curl_version();
            $this->line('  cURL SSL: '.$version['ssl_version']);
            $this->line('  cURL Protocols: '.implode(', ', $version['protocols']));
        } else {
            $this->error('  ✗ cURL extension not available!');
        }
    }

    private function checkEnvironment($ip, $port): void
    {
        $this->line('  Environment: '.config('app.env'));
        $this->line('  Debug Mode: '.(config('app.debug') ? 'enabled' : 'disabled'));

        // Check if running in Docker
        if (file_exists('/.dockerenv')) {
            $this->warn('  ⚠ Running inside Docker container');
            $this->line(sprintf('  → Ensure container has network access to %s:%s', $ip, $port));
        }

        // Check system time (SSL certs can fail if time is wrong)
        $this->line('  System Time: '.now()->toDateTimeString());

        // Try system ping
        $this->line('  Testing system ping...');
        exec(sprintf('ping -c 1 -W 1 %s 2>&1', $ip), $output, $returnCode);
        if ($returnCode === 0) {
            $this->info('  ✓ ICMP ping successful');
        } else {
            $this->line('  ⚠ ICMP ping failed (may be normal if ICMP is blocked)');
        }

        // Try system port test with nc
        $this->line('  Testing port with netcat...');
        exec(sprintf('timeout 2 nc -zv %s %s 2>&1', $ip, $port), $output, $returnCode);
        if ($returnCode === 0) {
            $this->info(sprintf('  ✓ Port %s is open', $port));
        } else {
            $this->error(sprintf('  ✗ Port %s appears closed or filtered', $port));
            $this->line('  Output: '.implode("\n  ", $output));
        }
    }

    private function getCurlErrorMeaning(string|int $code): string
    {
        $errors = [
            6 => "Couldn't resolve host - DNS problem",
            7 => 'Failed to connect to host - Network/Firewall issue',
            28 => 'Timeout - Server too slow or unreachable',
            35 => 'SSL connect error - SSL/TLS negotiation failed',
            51 => 'SSL peer certificate verification failed',
            52 => 'Empty reply from server',
            56 => 'Connection reset by peer',
            60 => 'SSL certificate problem',
        ];

        return $errors[$code] ?? 'Unknown cURL error';
    }
}
