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
            ['name' => 'Cloudflare', 'type' => Provider::TYPE_DNS, 'vendor' => 'cloudflare'],
            ['name' => 'Bunny.net DNS', 'type' => Provider::TYPE_DNS, 'vendor' => 'bunny'],
            ['name' => 'Route53', 'type' => Provider::TYPE_DNS, 'vendor' => 'route53'],
            ['name' => 'DigitalOcean DNS', 'type' => Provider::TYPE_DNS, 'vendor' => 'digitalocean'],
            ['name' => 'Linode DNS', 'type' => Provider::TYPE_DNS, 'vendor' => 'linode'],
            ['name' => 'Hetzner DNS', 'type' => Provider::TYPE_DNS, 'vendor' => 'hetzner'],
            ['name' => 'Vultr DNS', 'type' => Provider::TYPE_DNS, 'vendor' => 'vultr'],
            ['name' => 'Custom DNS', 'type' => Provider::TYPE_DNS, 'vendor' => 'custom'],
            ['name' => 'Cloudflare CDN', 'type' => Provider::TYPE_CDN, 'vendor' => 'cloudflare'],
            ['name' => 'Bunny.net CDN', 'type' => Provider::TYPE_CDN, 'vendor' => 'bunny'],
            ['name' => 'AWS CloudFront', 'type' => Provider::TYPE_CDN, 'vendor' => 'cloudfront'],
            ['name' => 'KeyCDN', 'type' => Provider::TYPE_CDN, 'vendor' => 'keycdn'],
            ['name' => 'Hetzner Cloud', 'type' => Provider::TYPE_SERVER, 'vendor' => 'hetzner'],
            ['name' => 'OVH', 'type' => Provider::TYPE_SERVER, 'vendor' => 'ovh'],
            ['name' => 'Netcup', 'type' => Provider::TYPE_SERVER, 'vendor' => 'netcup'],
            ['name' => 'Microsoft Azure', 'type' => Provider::TYPE_SERVER, 'vendor' => 'azure'],
            ['name' => 'Amazon Web Services', 'type' => Provider::TYPE_SERVER, 'vendor' => 'aws'],
            ['name' => 'DigitalOcean', 'type' => Provider::TYPE_SERVER, 'vendor' => 'digitalocean'],
            ['name' => 'Linode (Akamai)', 'type' => Provider::TYPE_SERVER, 'vendor' => 'linode'],
            ['name' => 'Google Cloud Platform', 'type' => Provider::TYPE_SERVER, 'vendor' => 'gcp'],
            ['name' => 'Vultr', 'type' => Provider::TYPE_SERVER, 'vendor' => 'vultr'],
            ['name' => 'Local Server', 'type' => Provider::TYPE_SERVER, 'vendor' => 'local'],
            ['name' => 'Other', 'type' => Provider::TYPE_SERVER, 'vendor' => 'other'],
            ['name' => 'Namecheap', 'type' => Provider::TYPE_DOMAIN_REGISTRAR, 'vendor' => 'namecheap'],
            ['name' => 'Cloudflare Registrar', 'type' => Provider::TYPE_DOMAIN_REGISTRAR, 'vendor' => 'cloudflare'],
            ['name' => 'GoDaddy', 'type' => Provider::TYPE_DOMAIN_REGISTRAR, 'vendor' => 'godaddy'],
            ['name' => 'Google Domains', 'type' => Provider::TYPE_DOMAIN_REGISTRAR, 'vendor' => 'google'],
            ['name' => 'OVH Registrar', 'type' => Provider::TYPE_DOMAIN_REGISTRAR, 'vendor' => 'ovh'],
            ['name' => 'IONOS', 'type' => Provider::TYPE_DOMAIN_REGISTRAR, 'vendor' => 'ionos'],
            ['name' => 'Other', 'type' => Provider::TYPE_DOMAIN_REGISTRAR, 'vendor' => 'other'],
        ];
    }
}
