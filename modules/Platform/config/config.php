<?php

return [
    'name' => 'Platform',
    'release_api_key' => config('astero.release_api_key', ''),
    'provisioner_ip' => config('astero.provisioner_ip'),
    'provisioner_ips' => config('astero.provisioner_ips', []),
    'acme_challenge' => [
        'alias_domain' => env('ACME_CHALLENGE_ALIAS_DOMAIN'),
        'bunny_api_key' => env('ACME_CHALLENGE_BUNNY_API_KEY'),
    ],

    'server_statuses' => [
        'active' => ['label' => 'Active',     'value' => 'active',     'color' => 'success'],
        'provisioning' => ['label' => 'Provisioning', 'value' => 'provisioning', 'color' => 'info'],
        'failed' => ['label' => 'Failed',     'value' => 'failed',     'color' => 'danger'],
        'inactive' => ['label' => 'Inactive',   'value' => 'inactive',   'color' => 'danger'],
        'maintenance' => ['label' => 'Maintenance', 'value' => 'maintenance', 'color' => 'warning'],
        'trash' => ['label' => 'Trash',      'value' => 'trash',      'color' => 'danger'],
    ],

    'server_types' => [
        // Recommended operational types.
        'localhost' => ['label' => 'Localhost', 'value' => 'localhost', 'color' => 'warning'],
        'development' => ['label' => 'Development', 'value' => 'development', 'color' => 'secondary'],
        'staging' => ['label' => 'Staging', 'value' => 'staging', 'color' => 'info'],
        'production' => ['label' => 'Production', 'value' => 'production', 'color' => 'success'],
    ],

    'agency_statuses' => [
        'active' => ['label' => 'Active',   'value' => 'active',   'color' => 'success'],
        'inactive' => ['label' => 'Inactive', 'value' => 'inactive', 'color' => 'danger'],
        'trash' => ['label' => 'Trash',    'value' => 'trash',    'color' => 'danger'],
    ],

    'agency_types' => [
        'default' => ['label' => 'Default', 'value' => 'default', 'color' => 'secondary'],
        'premium' => ['label' => 'Premium', 'value' => 'premium', 'color' => 'primary'],
        'vip' => ['label' => 'VIP', 'value' => 'vip', 'color' => 'warning'],
    ],

    'secret_types' => [
        'password' => ['label' => 'Password', 'value' => 'password', 'color' => 'primary'],
        'api_key' => ['label' => 'API Key', 'value' => 'api_key', 'color' => 'info'],
        'ssh_key' => ['label' => 'SSH Key', 'value' => 'ssh_key', 'color' => 'warning'],
        'token' => ['label' => 'Token', 'value' => 'token', 'color' => 'success'],
        'ssl_certificate' => ['label' => 'SSL Certificate', 'value' => 'ssl_certificate', 'color' => 'secondary'],
        'json' => ['label' => 'JSON', 'value' => 'json', 'color' => 'info'],
        'other' => ['label' => 'Other', 'value' => 'other', 'color' => 'secondary'],
    ],
];
