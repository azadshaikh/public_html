<?php

return [
    'statuses' => [
        'active' => [
            'value' => 'active',
            'label' => 'Active',
            'color' => 'success',
        ],
        'inactive' => [
            'value' => 'inactive',
            'label' => 'Inactive',
            'color' => 'secondary',
        ],
        'expired' => [
            'value' => 'expired',
            'label' => 'Expired',
            'color' => 'danger',
        ],
        'pending' => [
            'value' => 'pending',
            'label' => 'Pending',
            'color' => 'warning',
        ],
    ],
    'domain_providers' => [
        'cloudflare' => ['value' => 'cloudflare', 'label' => 'Cloudflare'],
        'bunnycdn' => ['value' => 'bunnycdn', 'label' => 'Bunny CDN'],
    ],
    'record_types' => [
        0 => ['value' => 0, 'label' => 'A'],
        1 => ['value' => 1, 'label' => 'AAAA'],
        2 => ['value' => 2, 'label' => 'CNAME'],
        3 => ['value' => 3, 'label' => 'TXT'],
        4 => ['value' => 4, 'label' => 'MX'],
        5 => ['value' => 5, 'label' => 'Redirect'],
        6 => ['value' => 6, 'label' => 'Flatten'],
        7 => ['value' => 7, 'label' => 'PullZone'],
        8 => ['value' => 8, 'label' => 'SRV'],
        9 => ['value' => 9, 'label' => 'CAA'],
        10 => ['value' => 10, 'label' => 'PTR'],
        11 => ['value' => 11, 'label' => 'Script'],
        12 => ['value' => 12, 'label' => 'NS'],
    ],
    'dns_ttls' => [
        15 => ['value' => 15, 'label' => '15s'],
        30 => ['value' => 30, 'label' => '30s'],
        60 => ['value' => 60, 'label' => '1m'],
        300 => ['value' => 300, 'label' => '5m'],
        900 => ['value' => 900, 'label' => '15m'],
        1800 => ['value' => 1800, 'label' => '30m'],
        3600 => ['value' => 3600, 'label' => '1h'],
        18000 => ['value' => 18000, 'label' => '5h'],
        43200 => ['value' => 43200, 'label' => '12h'],
        86400 => ['value' => 86400, 'label' => '1d'],
    ],
    'dns_monitor_types' => [
        0 => ['value' => 0, 'label' => 'None'],
        1 => ['value' => 1, 'label' => 'HTTP'],
        2 => ['value' => 2, 'label' => 'TCP'],
        3 => ['value' => 3, 'label' => 'Ping'],
    ],
    'smart_routing_types' => [
        0 => ['value' => 0, 'label' => 'None'],
        1 => ['value' => 1, 'label' => 'Latency'],
        2 => ['value' => 2, 'label' => 'Geolocation'],
    ],
    'ssl_types' => [
        1 => ['value' => 1, 'label' => 'Self Signed'],
        2 => ['value' => 2, 'label' => "Let's Encrypt"],
    ],

    'types' => [
        'default' => ['label' => 'Default', 'value' => 'default', 'color' => 'secondary'],
        'premium' => ['label' => 'Premium', 'value' => 'premium', 'color' => 'primary'],
        'corporate' => ['label' => 'Corporate', 'value' => 'corporate', 'color' => 'info'],
    ],

    'dns_providers' => [
        'bunny' => ['label' => 'Bunny',      'value' => 'bunny',      'color' => 'primary'],
        'cloudflare' => ['label' => 'Cloudflare', 'value' => 'cloudflare', 'color' => 'primary'],
    ],
];
