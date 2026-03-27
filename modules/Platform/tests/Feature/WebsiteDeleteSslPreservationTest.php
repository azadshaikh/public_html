<?php

declare(strict_types=1);

namespace Modules\Platform\Tests\Feature;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Jobs\WebsiteDelete;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Website;
use Modules\Platform\Providers\PlatformServiceProvider;
use Modules\Platform\Services\WebsiteSslAssignmentService;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class WebsiteDeleteSslPreservationTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->setUpModuleManifest('platform-website-delete-ssl-preservation.json', [
            'Platform' => 'enabled',
        ]);

        $this->ensurePlatformModuleBooted();
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_permanent_delete_preserves_root_domain_and_ssl_for_reinstall_reuse(): void
    {
        $domain = Domain::query()->create([
            'name' => 'astero.in',
            'status' => 'active',
        ]);

        $certificate = $domain->secrets()->create([
            'key' => 'domain_ssl_certificate',
            'username' => 'wildcard.astero.in',
            'type' => 'ssl_certificate',
            'value' => encrypt('private-key'),
            'metadata' => [
                'certificate' => 'cert-pem',
                'domains' => ['astero.in', '*.astero.in'],
                'is_wildcard' => true,
            ],
            'is_active' => true,
            'expires_at' => now()->addDays(45),
        ]);

        $deletedWebsite = Website::query()->create([
            'name' => 'Astero',
            'domain' => 'astero.in',
            'domain_id' => $domain->id,
            'status' => WebsiteStatus::Deleted,
        ]);

        $deletedWebsite->delete();

        (new WebsiteDelete($deletedWebsite->id))->handle();

        $this->assertDatabaseHas('platform_domains', [
            'id' => $domain->id,
            'name' => 'astero.in',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('platform_secrets', [
            'id' => $certificate->id,
            'secretable_type' => Domain::class,
            'secretable_id' => $domain->id,
            'type' => 'ssl_certificate',
        ]);

        $replacementWebsite = Website::query()->create([
            'name' => 'Astero Reinstall',
            'domain' => 'astero.in',
            'status' => 'provisioning',
        ]);

        $replacementWebsite->domain_id = Domain::query()->where('name', 'astero.in')->value('id');
        $replacementWebsite->save();

        $service = resolve(WebsiteSslAssignmentService::class);
        $reusableCertificate = $service->findReusableCertificateForWebsite($replacementWebsite);

        $this->assertNotNull($reusableCertificate);
        $this->assertSame($certificate->id, $reusableCertificate->id);
    }

    private function ensurePlatformModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('platform.agencies.create')) {
            app()->register(PlatformServiceProvider::class);
        }
    }
}
