<?php

namespace Modules\Platform\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;
use Tests\TestCase;

class RefactorVerificationTest extends TestCase
{
    public function test_provider_can_be_created(): void
    {
        // Create Provider
        $provider = Provider::query()->create([
            'name' => 'Test Bunny Provider',
            'type' => Provider::TYPE_DNS,
            'vendor' => 'bunny',
            'credentials' => ['api_key' => 'test-key'],
            'status' => 'active',
        ]);

        /** @var Provider $provider */
        $this->assertEquals('bunny', $provider->vendor);
        $this->assertEquals(Provider::TYPE_DNS, $provider->type);
        // Test encryption cast (accessing should be array)
        $this->assertEquals('test-key', $provider->credentials['api_key']);

        // Cleanup
        $provider->forceDelete();
    }

    public function test_website_can_be_linked_to_provider_via_polymorphic_relationship(): void
    {
        // Create a DNS Provider
        $provider = Provider::query()->create([
            'name' => 'Test DNS Provider',
            'type' => Provider::TYPE_DNS,
            'vendor' => 'cloudflare',
            'status' => 'active',
        ]);

        /** @var Provider $provider */

        // Get or create a user
        $user = User::query()->first();
        if (! $user) {
            $user = User::query()->create([
                'name' => 'Test User',
                'email' => 'test-'.uniqid().'@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        // Create a website
        $website = Website::query()->create([
            'uid' => 'test-site-'.uniqid(),
            'domain' => 'test-'.uniqid().'.com',
            'status' => WebsiteStatus::Provisioning,
            'owner_id' => $user->id,
        ]);

        /** @var Website $website */

        // Link provider via polymorphic relationship
        $website->assignProvider($provider->id, true);

        // Verify relationship
        $website->refresh();
        $this->assertEquals(1, $website->providers()->count());
        $this->assertEquals($provider->id, $website->dnsProvider?->id);

        // Cleanup
        $website->forceDelete();
        $provider->forceDelete();
    }

    public function test_platform_providers_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('platform_providers'));
        $this->assertTrue(Schema::hasColumn('platform_providers', 'type'));
        $this->assertTrue(Schema::hasColumn('platform_providers', 'vendor'));
        $this->assertTrue(Schema::hasColumn('platform_providers', 'credentials'));
    }

    public function test_platform_providerables_pivot_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('platform_providerables'));
        $this->assertTrue(Schema::hasColumn('platform_providerables', 'provider_id'));
        $this->assertTrue(Schema::hasColumn('platform_providerables', 'providerable_type'));
        $this->assertTrue(Schema::hasColumn('platform_providerables', 'providerable_id'));
        $this->assertTrue(Schema::hasColumn('platform_providerables', 'is_primary'));
    }

    public function test_website_no_longer_has_dns_provider_id_column(): void
    {
        $this->assertFalse(Schema::hasColumn('platform_websites', 'dns_provider_id'));
    }
}
