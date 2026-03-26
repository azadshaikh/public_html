<?php

namespace Modules\Platform\Tests\Unit;

use App\Enums\ActivityAction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Console\HestiaRevertInstallationStepCommand;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteProvisioningService;
use ReflectionMethod;
use Tests\TestCase;

class TestableHestiaRevertInstallationStepCommand extends HestiaRevertInstallationStepCommand
{
    public function runProcessReversion(Website $website, string $step): void
    {
        $method = new ReflectionMethod(HestiaRevertInstallationStepCommand::class, 'processReversion');
        $method->setAccessible(true);
        $method->invoke($this, $website, $step);
    }

    public function line($string, $style = null, $verbosity = null): void {}

    public function info($string, $verbosity = null): void {}

    public function error($string, $verbosity = null): void {}

    public function logActivity(
        $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        bool $queue = false
    ): void {}
}

class TestableWebsiteForRevertCommand extends Website
{
    public bool $revertedAllProvisioningSteps = false;

    /** @var array<string, string> */
    public array $revertedSteps = [];

    /** @var array<string, Provider|null> */
    public array $providersByType = [];

    public function revertAllProvisioningSteps(): void
    {
        $this->revertedAllProvisioningSteps = true;
    }

    public function markProvisioningStepReverted(string $stepKey, string $message = 'Reverted'): void
    {
        $this->revertedSteps[$stepKey] = $message;
    }

    public function getProvider(string $type): ?Provider
    {
        return $this->providersByType[$type] ?? null;
    }

    public function save(array $options = []): bool
    {
        return true;
    }
}

class TestableWebsiteProvisioningService extends WebsiteProvisioningService
{
    public int $loggedActivities = 0;

    public function logActivity(
        $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        bool $queue = false
    ): void {
        $this->loggedActivities++;
    }
}

class WebsiteRevertProvisioningFlowTest extends TestCase
{
    public function test_reverting_all_steps_deletes_bunny_pull_zone_before_deleting_server_user(): void
    {
        Http::fake([
            'https://api.bunny.net/*' => Http::response([], 200),
            'https://server.example.test:8443/api/' => Http::response([
                'status' => 'success',
                'message' => 'User deleted successfully.',
            ], 200),
        ]);

        $website = new TestableWebsiteForRevertCommand;
        $website->id = 42;
        $website->domain = 'example.test';
        $website->website_username = 'WS00042';
        $website->metadata = [
            'cdn' => [
                'Id' => 123,
            ],
        ];

        $provider = new Provider;
        $provider->vendor = 'bunny';
        $provider->credentials = ['api_key' => 'bunny-api-key'];

        $server = new Server;
        $server->fqdn = 'server.example.test';
        $server->port = 8443;
        $server->access_key_id = 'access-key';
        $server->access_key_secret = 'secret-key';

        $website->providersByType[Provider::TYPE_CDN] = $provider;
        $website->setRelation('server', $server);

        $command = new TestableHestiaRevertInstallationStepCommand;
        $command->runProcessReversion($website, 'all');

        $this->assertTrue($website->revertedAllProvisioningSteps);
        $this->assertSame('Bunny CDN (pull zone) deleted successfully.', $website->revertedSteps['setup_bunny_cdn'] ?? null);
        $this->assertNull($website->getMetadata('cdn'));

        Http::assertSentCount(2);

        $recorded = Http::recorded()->values();

        $this->assertSame('DELETE', $recorded[0][0]->method());
        $this->assertSame('https://api.bunny.net/pullzone/123', $recorded[0][0]->url());
        $this->assertSame('POST', $recorded[1][0]->method());
        $this->assertSame('https://server.example.test:8443/api/', $recorded[1][0]->url());
        $this->assertSame('v-delete-user', $recorded[1][0]->data()['arg2'] ?? null);
    }

    public function test_revert_step_returns_error_when_revert_command_fails(): void
    {
        $website = new Website;
        $website->id = 7;

        Artisan::shouldReceive('call')
            ->once()
            ->with('platform:hestia:revert-installation-step', [
                'website_id' => 7,
                '--step' => 'setup_bunny_cdn',
                '--force' => true,
            ])
            ->andReturn(1);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('Failed to delete pull zone.');

        $service = new TestableWebsiteProvisioningService;

        $result = $service->revertStep($website, 'setup_bunny_cdn');

        $this->assertSame('error', $result['status']);
        $this->assertSame('Failed to delete pull zone.', $result['message']);
        $this->assertSame(0, $service->loggedActivities);
    }
}
