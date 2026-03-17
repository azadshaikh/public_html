<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Provider Types
    |--------------------------------------------------------------------------
    |
    | Define the different types of providers that can be stored in the
    | platform_providers table. Each type represents a service category.
    |
    */
    'types' => [
        'dns' => [
            'label' => 'DNS Provider',
            'value' => 'dns',
            'color' => 'info',
            'icon' => 'ri-global-line',
        ],
        'cdn' => [
            'label' => 'CDN Provider',
            'value' => 'cdn',
            'color' => 'primary',
            'icon' => 'ri-cloud-line',
        ],
        'server' => [
            'label' => 'Server Provider',
            'value' => 'server',
            'color' => 'success',
            'icon' => 'ri-server-line',
        ],
        'domain_registrar' => [
            'label' => 'Domain Registrar',
            'value' => 'domain_registrar',
            'color' => 'warning',
            'icon' => 'ri-earth-line',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Statuses
    |--------------------------------------------------------------------------
    |
    | Define the different statuses a provider can have.
    |
    */
    'statuses' => [
        'active' => [
            'label' => 'Active',
            'value' => 'active',
            'color' => 'success',
        ],
        'inactive' => [
            'label' => 'Inactive',
            'value' => 'inactive',
            'color' => 'secondary',
        ],
        'suspended' => [
            'label' => 'Suspended',
            'value' => 'suspended',
            'color' => 'danger',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Vendors
    |--------------------------------------------------------------------------
    |
    | Define the supported vendors/integrations for providers. These represent
    | the actual service providers (e.g., Bunny, Cloudflare, Hetzner).
    | Vendors can be used across multiple provider types.
    |
    */
    'vendors' => [
        // DNS/CDN Vendors
        'bunny' => [
            'label' => 'Bunny',
            'value' => 'bunny',
            'color' => 'warning',
            'icon' => 'ri-cloud-line',
            'types' => ['dns', 'cdn'],
        ],
        'cloudflare' => [
            'label' => 'Cloudflare',
            'value' => 'cloudflare',
            'color' => 'warning',
            'icon' => 'ri-cloud-line',
            'types' => ['dns', 'cdn'],
        ],

        // Server Providers
        'hetzner' => [
            'label' => 'Hetzner',
            'value' => 'hetzner',
            'color' => 'danger',
            'icon' => 'ri-server-line',
            'types' => ['server'],
        ],
        'digitalocean' => [
            'label' => 'DigitalOcean',
            'value' => 'digitalocean',
            'color' => 'info',
            'icon' => 'ri-server-line',
            'types' => ['server'],
        ],
        'linode' => [
            'label' => 'Linode',
            'value' => 'linode',
            'color' => 'success',
            'icon' => 'ri-server-line',
            'types' => ['server'],
        ],
        'vultr' => [
            'label' => 'Vultr',
            'value' => 'vultr',
            'color' => 'info',
            'icon' => 'ri-server-line',
            'types' => ['server'],
        ],
        'aws' => [
            'label' => 'AWS',
            'value' => 'aws',
            'color' => 'warning',
            'icon' => 'ri-amazon-line',
            'types' => ['server', 'cdn', 'dns'],
        ],

        // Domain Registrars
        'namecheap' => [
            'label' => 'Namecheap',
            'value' => 'namecheap',
            'color' => 'warning',
            'icon' => 'ri-earth-line',
            'types' => ['domain_registrar'],
        ],
        'godaddy' => [
            'label' => 'GoDaddy',
            'value' => 'godaddy',
            'color' => 'success',
            'icon' => 'ri-earth-line',
            'types' => ['domain_registrar'],
        ],
        'namesilo' => [
            'label' => 'NameSilo',
            'value' => 'namesilo',
            'color' => 'info',
            'icon' => 'ri-earth-line',
            'types' => ['domain_registrar'],
        ],
        'porkbun' => [
            'label' => 'Porkbun',
            'value' => 'porkbun',
            'color' => 'danger',
            'icon' => 'ri-earth-line',
            'types' => ['domain_registrar'],
        ],
        'google_domains' => [
            'label' => 'Google Domains',
            'value' => 'google_domains',
            'color' => 'primary',
            'icon' => 'ri-google-line',
            'types' => ['domain_registrar'],
        ],

        // Manual/Other
        'manual' => [
            'label' => 'Manual',
            'value' => 'manual',
            'color' => 'secondary',
            'icon' => 'ri-settings-3-line',
            'types' => ['dns', 'cdn', 'server', 'domain_registrar'],
        ],
        'other' => [
            'label' => 'Other',
            'value' => 'other',
            'color' => 'secondary',
            'icon' => 'ri-more-line',
            'types' => ['dns', 'cdn', 'server', 'domain_registrar'],
        ],
    ],
];
