<?php

namespace Modules\Platform\Tests\Feature;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Queue;
use Modules\Platform\Jobs\WebsiteProvision;
use Modules\Platform\Models\Website;
use Tests\TestCase;

class WebsiteProvisioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Module view namespaces are not always registered in the base TestCase.
        // Register the Platform namespace so we can render module views in isolation.
        $this->app->make(Factory::class)->addNamespace('platform', base_path('modules/Platform/resources/views'));
        // Since we are testing module code without full app context, we might need some mocks.
        // But for Feature tests, we usually rely on DB.
        // Assuming we can use in-memory sqlite or similar if configured, or just run against dev db if acceptable.
        // For safety, we will mock the artisan calls to avoid actual provisioning side effects.
    }

    public function test_website_provision_job_is_queueable(): void
    {
        Queue::fake();

        $website = new Website;
        $website->id = 1;

        dispatch(new WebsiteProvision($website));

        Queue::assertPushed(WebsiteProvision::class, fn ($job): bool => $job->websiteId === $website->id);
    }

    public function test_env_template_defaults_to_database_drivers(): void
    {
        $env = view('platform::websites.partials.env-template', [
            'app_name' => 'Demo Site',
            'app_url' => 'https://demo.test',
            'domain' => 'demo.test',
            'agency_uid' => 'AGY00001',
            'website_id' => 'WS00001',
            'website_plan' => 'trial',
            'agency_plan' => 'agency_trial',
            'secret_key' => 'secret',
            'agency_secret_key' => null,
            'site_id' => 'WS00001',
            'admin_slug' => 'admin',
            'media_slug' => 'media',
            'theme_uid' => 1000,
            'database_name' => 'ws00001_db',
            'database_username' => 'ws00001_user',
            'database_password' => 'pass',
            'website_provider' => null,
            'branding_name' => '',
            'branding_website' => '',
            'branding_logo' => '',
            'branding_icon' => '',
        ])->render();

        $this->assertStringContainsString('SESSION_DRIVER=database', $env);
        $this->assertStringContainsString('SESSION_CONNECTION=', $env);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $env);
        $this->assertStringContainsString('CACHE_STORE=database', $env);
        $this->assertStringContainsString('CACHE_LIMITER=database', $env);
        $this->assertStringContainsString('CACHE_PREFIX=', $env);
    }

    public function test_env_template_normalizes_pgsql_database_identifiers_to_lowercase(): void
    {
        $env = view('platform::websites.partials.env-template', [
            'app_name' => 'Demo Site',
            'app_url' => 'https://demo.test',
            'domain' => 'demo.test',
            'agency_uid' => 'AGY00001',
            'website_id' => 'WS00001',
            'website_plan' => 'trial',
            'agency_plan' => 'agency_trial',
            'secret_key' => 'secret',
            'agency_secret_key' => null,
            'site_id' => 'WS00001',
            'admin_slug' => 'admin',
            'media_slug' => 'media',
            'theme_uid' => 1000,
            'db_connection' => 'pgsql',
            'database_name' => 'WS00001_db',
            'database_username' => 'WS00001_db_user',
            'database_password' => 'pass',
            'website_provider' => null,
            'branding_name' => '',
            'branding_website' => '',
            'branding_logo' => '',
            'branding_icon' => '',
        ])->render();

        $this->assertStringContainsString('DB_DATABASE=ws00001_db', $env);
        $this->assertStringContainsString('DB_USERNAME=ws00001_db_user', $env);
    }
}
