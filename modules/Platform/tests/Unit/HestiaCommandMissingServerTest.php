<?php

namespace Modules\Platform\Tests\Unit;

use App\Enums\ActivityAction;
use Modules\Platform\Console\HestiaDeleteWebsiteCommand;
use Modules\Platform\Console\HestiaManageQueueWorkerCommand;
use Modules\Platform\Console\HestiaRecacheApplicationCommand;
use Modules\Platform\Models\Website;
use Tests\TestCase;

class TestableHestiaManageQueueWorkerCommand extends HestiaManageQueueWorkerCommand
{
    public string $testAction = 'remove';

    public array $warnings = [];

    public array $infos = [];

    public function runHandleCommand(Website $website): void
    {
        $this->handleCommand($website);
    }

    public function argument($key = null)
    {
        if ($key === 'action') {
            return $this->testAction;
        }

        return null;
    }

    public function warn($string, $verbosity = null): void
    {
        $this->warnings[] = (string) $string;
    }

    public function info($string, $verbosity = null): void
    {
        $this->infos[] = (string) $string;
    }

    public function logActivity(
        $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        bool $queue = false
    ): void {}
}

class TestableHestiaDeleteWebsiteCommand extends HestiaDeleteWebsiteCommand
{
    public array $warnings = [];

    public array $infos = [];

    public function runHandleCommand(Website $website): void
    {
        $this->handleCommand($website);
    }

    public function warn($string, $verbosity = null): void
    {
        $this->warnings[] = (string) $string;
    }

    public function info($string, $verbosity = null): void
    {
        $this->infos[] = (string) $string;
    }

    public function logActivity(
        $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        bool $queue = false
    ): void {}
}

class TestableHestiaRecacheApplicationCommand extends HestiaRecacheApplicationCommand
{
    public array $warnings = [];

    public array $infos = [];

    public function runHandleCommand(Website $website): void
    {
        $this->handleCommand($website);
    }

    public function warn($string, $verbosity = null): void
    {
        $this->warnings[] = (string) $string;
    }

    public function info($string, $verbosity = null): void
    {
        $this->infos[] = (string) $string;
    }

    public function logActivity(
        $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        bool $queue = false
    ): void {}
}

class HestiaCommandMissingServerTest extends TestCase
{
    public function test_manage_queue_worker_skips_when_server_relation_is_missing(): void
    {
        $website = new Website;
        $website->id = 3;
        $website->domain = 'example.test';
        $website->website_username = 'WS00003';
        $website->setRelation('server', null);

        $command = new TestableHestiaManageQueueWorkerCommand;
        $command->testAction = 'remove';

        $command->runHandleCommand($website);

        $this->assertNotEmpty($command->warnings);
        $this->assertStringContainsString("No server found for website 'example.test'", $command->warnings[0]);
    }

    public function test_delete_website_skips_when_server_relation_is_missing(): void
    {
        $website = new Website;
        $website->id = 4;
        $website->domain = 'missing-server.test';
        $website->website_username = 'WS00004';
        $website->setRelation('server', null);

        $command = new TestableHestiaDeleteWebsiteCommand;

        $command->runHandleCommand($website);

        $this->assertNotEmpty($command->warnings);
        $this->assertStringContainsString("No server found for website 'missing-server.test'", $command->warnings[0]);
    }

    public function test_recache_application_skips_when_server_relation_is_missing(): void
    {
        $website = new Website;
        $website->id = 5;
        $website->domain = 'recache-missing-server.test';
        $website->website_username = 'WS00005';
        $website->setRelation('server', null);

        $command = new TestableHestiaRecacheApplicationCommand;

        $command->runHandleCommand($website);

        $this->assertNotEmpty($command->warnings);
        $this->assertStringContainsString("No server found for website 'recache-missing-server.test'", $command->warnings[0]);
    }
}
