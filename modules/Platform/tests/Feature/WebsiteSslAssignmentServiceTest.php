<?php

declare(strict_types=1);

namespace Modules\Platform\Tests\Feature;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Website;
use Modules\Platform\Providers\PlatformServiceProvider;
use Modules\Platform\Services\WebsiteSslAssignmentService;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class WebsiteSslAssignmentServiceTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->setUpModuleManifest('platform-website-ssl-assignment.json', [
            'Platform' => 'enabled',
        ]);

        $this->ensurePlatformModuleBooted();
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_it_reuses_root_domain_certificate_for_sibling_subdomain_websites(): void
    {
        $domain = Domain::query()->create([
            'name' => 'astero.in',
            'status' => 'active',
        ]);

        $website = Website::query()->create([
            'name' => 'Web 2',
            'domain' => 'web2.astero.in',
            'domain_id' => $domain->id,
            'status' => 'provisioning',
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

        $service = resolve(WebsiteSslAssignmentService::class);

        $reusableCertificate = $service->findReusableCertificateForWebsite($website);

        $this->assertNotNull($reusableCertificate);
        $this->assertSame($certificate->id, $reusableCertificate->id);

        $service->assignCertificateToWebsite($website, $certificate);

        $website->refresh();
        $this->assertSame($certificate->id, $website->ssl_secret_id);
    }

    public function test_it_lists_all_websites_covered_by_same_root_domain_certificate(): void
    {
        $domain = Domain::query()->create([
            'name' => 'astero.in',
            'status' => 'active',
        ]);

        $rootWebsite = Website::query()->create([
            'name' => 'Root',
            'domain' => 'astero.in',
            'domain_id' => $domain->id,
            'status' => 'active',
        ]);

        $subWebsiteOne = Website::query()->create([
            'name' => 'Web 2',
            'domain' => 'web2.astero.in',
            'domain_id' => $domain->id,
            'status' => 'active',
        ]);

        $subWebsiteTwo = Website::query()->create([
            'name' => 'Web 3',
            'domain' => 'web3.astero.in',
            'domain_id' => $domain->id,
            'status' => 'suspended',
        ]);

        $excludedWebsite = Website::query()->create([
            'name' => 'External',
            'domain' => 'other.com',
            'status' => 'active',
        ]);

        $renewedCertificate = $domain->secrets()->create([
            'key' => 'domain_ssl_certificate',
            'username' => 'wildcard.astero.in',
            'type' => 'ssl_certificate',
            'value' => encrypt('renewed-private-key'),
            'metadata' => [
                'certificate' => 'renewed-cert-pem',
                'domains' => ['astero.in', '*.astero.in'],
                'is_wildcard' => true,
            ],
            'is_active' => true,
            'expires_at' => now()->addDays(90),
        ]);

        $service = resolve(WebsiteSslAssignmentService::class);

        $updatedWebsites = $service->websitesCoveredByCertificate($domain, $renewedCertificate);

        $this->assertCount(3, $updatedWebsites);
        $this->assertEqualsCanonicalizing(
            [$rootWebsite->id, $subWebsiteOne->id, $subWebsiteTwo->id],
            $updatedWebsites->pluck('id')->all()
        );

        $this->assertSame(0, $renewedCertificate->websites()->count());
    }

    private function ensurePlatformModuleBooted(): void
    {
        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());

        if (! Route::has('platform.agencies.create')) {
            app()->register(PlatformServiceProvider::class);
        }
    }
}
