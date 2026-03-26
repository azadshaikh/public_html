<?php

namespace Modules\Platform\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Modules\Platform\Console\ProvisionWebsiteCommand;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Jobs\WebsiteProvision;
use Modules\Platform\Models\Website;
use Tests\TestCase;

class WebsiteProvisionWaitingRetryTest extends TestCase
{
    public function test_job_requeues_itself_when_a_non_dns_step_is_waiting(): void
    {
        $website = new class extends Website
        {
            public function ensureProvisioningRunStarted(): void {}

            public function refresh(): static
            {
                return $this;
            }

            public function getProvisioningSteps(): array
            {
                return [
                    'configure_cdn_ssl' => [
                        'status' => 'waiting',
                        'message' => 'Waiting for Bunny hostname SSL.',
                    ],
                ];
            }
        };

        $website->id = 21;
        $website->site_id = 'WS00021';
        $website->domain = 'astero.in';
        $website->status = WebsiteStatus::Provisioning;

        Artisan::shouldReceive('call')
            ->once()
            ->with('platform:provision-website', ['website_id' => 21])
            ->andReturn(0);

        $job = new class($website) extends WebsiteProvision
        {
            public bool $scheduledRetry = false;

            public bool $successCalled = false;

            public function __construct(private readonly Website $fakeWebsite)
            {
                parent::__construct($fakeWebsite);
            }

            protected function findWebsite(): ?Website
            {
                return $this->fakeWebsite;
            }

            protected function scheduleWaitingRetry(Website $website): void
            {
                $this->scheduledRetry = true;
            }

            public function onSuccess(Website $website): void
            {
                $this->successCalled = true;
            }
        };

        $job->handle();

        $this->assertTrue($job->scheduledRetry);
        $this->assertFalse($job->successCalled);
    }

    public function test_provision_command_only_uses_waiting_for_dns_for_verify_dns_step(): void
    {
        $website = new class extends Website
        {
            public bool $saved = false;

            public function save(array $options = []): bool
            {
                $this->saved = true;

                return true;
            }
        };

        $command = new class extends ProvisionWebsiteCommand
        {
            public array $messages = [];

            public function pauseForWaitingStepPublic(Website $website, string $stepKey): void
            {
                $this->pauseForWaitingStep($website, $stepKey);
            }

            public function info($string, $verbosity = null): void
            {
                $this->messages[] = (string) $string;
            }
        };

        $command->pauseForWaitingStepPublic($website, 'configure_cdn_ssl');

        $this->assertSame(WebsiteStatus::Provisioning, $website->status);
        $this->assertTrue($website->saved);
        $this->assertStringContainsString('waiting for step readiness', $command->messages[0]);
    }
}
