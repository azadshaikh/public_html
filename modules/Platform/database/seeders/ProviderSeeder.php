<?php

namespace Modules\Platform\Database\Seeders;

use Modules\Platform\Models\Provider;

class ProviderSeeder extends PlatformSeeder
{
    public function run(): void
    {
        $auditColumns = $this->auditColumns();

        foreach ($this->providerDefinitions() as $definition) {
            Provider::query()->updateOrCreate(
                [
                    'type' => $definition['type'],
                    'vendor' => $definition['vendor'],
                ],
                [
                    'name' => $definition['name'],
                    'status' => 'active',
                    ...$auditColumns,
                ],
            );
        }

        $this->writeInfo('Seeded Platform providers.');
    }

    /**
     * @return array<int, array{name: string, type: string, vendor: string}>
     */
    private function providerDefinitions(): array
    {
        return [
            ['name' => 'Cloudflare DNS', 'type' => Provider::TYPE_DNS, 'vendor' => 'cloudflare'],
            ['name' => 'Bunny.net DNS', 'type' => Provider::TYPE_DNS, 'vendor' => 'bunny'],
            ['name' => 'Route53', 'type' => Provider::TYPE_DNS, 'vendor' => 'route53'],
            ['name' => 'DigitalOcean DNS', 'type' => Provider::TYPE_DNS, 'vendor' => 'digitalocean'],
            ['name' => 'Cloudflare CDN', 'type' => Provider::TYPE_CDN, 'vendor' => 'cloudflare'],
            ['name' => 'Bunny.net CDN', 'type' => Provider::TYPE_CDN, 'vendor' => 'bunny'],
            ['name' => 'AWS CloudFront', 'type' => Provider::TYPE_CDN, 'vendor' => 'cloudfront'],
            ['name' => 'Hetzner Cloud', 'type' => Provider::TYPE_SERVER, 'vendor' => 'hetzner'],
            ['name' => 'DigitalOcean', 'type' => Provider::TYPE_SERVER, 'vendor' => 'digitalocean'],
            ['name' => 'Local Server', 'type' => Provider::TYPE_SERVER, 'vendor' => 'local'],
            ['name' => 'Namecheap', 'type' => Provider::TYPE_DOMAIN_REGISTRAR, 'vendor' => 'namecheap'],
            ['name' => 'Cloudflare Registrar', 'type' => Provider::TYPE_DOMAIN_REGISTRAR, 'vendor' => 'cloudflare'],
            ['name' => 'GoDaddy', 'type' => Provider::TYPE_DOMAIN_REGISTRAR, 'vendor' => 'godaddy'],
        ];
    }
}
