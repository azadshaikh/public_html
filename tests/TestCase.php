<?php

namespace Tests;

use App\Modules\ModuleManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.manifest', base_path('modules.json'));

        app()->forgetInstance(ModuleManager::class);
        app()->singleton(ModuleManager::class, fn ($app): ModuleManager => new ModuleManager(
            files: $app['files'],
            config: $app['config'],
        ));
    }

    protected function skipUnlessFortifyFeature(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
