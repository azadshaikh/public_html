<?php

namespace Modules\Platform\Database\Seeders;

use Modules\Platform\Models\Tld;

class TldSeeder extends PlatformSeeder
{
    public function run(): void
    {
        foreach ($this->tldDefinitions() as $definition) {
            Tld::query()->updateOrCreate(
                ['tld' => $definition['tld']],
                $definition,
            );
        }

        $this->writeInfo('Seeded Platform TLDs.');
    }

    /**
     * @return array<int, array<string, bool|int|string|null>>
     */
    private function tldDefinitions(): array
    {
        return [
            [
                'tld' => '.com',
                'whois_server' => 'whois.crsnic.net',
                'pattern' => 'no match for',
                'is_main' => true,
                'is_suggested' => true,
                'price' => '13.99',
                'sale_price' => '11.99',
                'affiliate_link' => 'https://www.namesilo.com/domain/search-domains?query={{domain_name}}',
                'status' => true,
                'tld_order' => 1,
            ],
            [
                'tld' => '.org',
                'whois_server' => 'whois.pir.org',
                'pattern' => 'not found',
                'is_main' => true,
                'is_suggested' => true,
                'price' => '9.99',
                'sale_price' => '9.99',
                'affiliate_link' => 'https://www.namesilo.com/domain/search-domains?query={{domain_name}}',
                'status' => true,
                'tld_order' => 2,
            ],
            [
                'tld' => '.net',
                'whois_server' => 'whois.crsnic.net',
                'pattern' => 'no match for',
                'is_main' => true,
                'is_suggested' => true,
                'price' => '15.99',
                'sale_price' => '14.99',
                'affiliate_link' => 'https://www.namesilo.com/domain/search-domains?query={{domain_name}}',
                'status' => true,
                'tld_order' => 3,
            ],
            [
                'tld' => '.io',
                'whois_server' => 'whois.nic.io',
                'pattern' => 'is available for purchase',
                'is_main' => false,
                'is_suggested' => true,
                'price' => '39.99',
                'sale_price' => '39.99',
                'affiliate_link' => 'https://www.namesilo.com/domain/search-domains?query={{domain_name}}',
                'status' => true,
                'tld_order' => 4,
            ],
            [
                'tld' => '.app',
                'whois_server' => 'whois.nic.google',
                'pattern' => 'domain not found',
                'is_main' => false,
                'is_suggested' => true,
                'price' => '16.99',
                'sale_price' => '16.99',
                'affiliate_link' => 'https://www.namesilo.com/domain/search-domains?query={{domain_name}}',
                'status' => true,
                'tld_order' => 5,
            ],
            [
                'tld' => '.dev',
                'whois_server' => 'whois.nic.google',
                'pattern' => 'domain not found',
                'is_main' => false,
                'is_suggested' => true,
                'price' => '14.99',
                'sale_price' => '14.99',
                'affiliate_link' => 'https://www.namesilo.com/domain/search-domains?query={{domain_name}}',
                'status' => true,
                'tld_order' => 6,
            ],
            [
                'tld' => '.co',
                'whois_server' => 'whois.nic.co',
                'pattern' => 'no data found',
                'is_main' => false,
                'is_suggested' => true,
                'price' => '24.99',
                'sale_price' => '19.99',
                'affiliate_link' => 'https://www.namesilo.com/domain/search-domains?query={{domain_name}}',
                'status' => true,
                'tld_order' => 7,
            ],
            [
                'tld' => '.in',
                'whois_server' => 'whois.registry.in',
                'pattern' => 'not found',
                'is_main' => false,
                'is_suggested' => false,
                'price' => '9.99',
                'sale_price' => '8.99',
                'affiliate_link' => 'https://www.namesilo.com/domain/search-domains?query={{domain_name}}',
                'status' => true,
                'tld_order' => 8,
            ],
            [
                'tld' => '.co.uk',
                'whois_server' => 'whois.nic.uk',
                'pattern' => 'no match for',
                'is_main' => false,
                'is_suggested' => false,
                'price' => '11.99',
                'sale_price' => '10.99',
                'affiliate_link' => 'https://www.namesilo.com/domain/search-domains?query={{domain_name}}',
                'status' => true,
                'tld_order' => 9,
            ],
            [
                'tld' => '.ai',
                'whois_server' => 'whois.nic.ai',
                'pattern' => 'no match',
                'is_main' => false,
                'is_suggested' => true,
                'price' => '89.99',
                'sale_price' => '79.99',
                'affiliate_link' => 'https://www.namesilo.com/domain/search-domains?query={{domain_name}}',
                'status' => true,
                'tld_order' => 10,
            ],
        ];
    }
}
