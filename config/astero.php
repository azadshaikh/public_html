<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Provisioning & Release
    |--------------------------------------------------------------------------
    |
    | Keep env-backed provisioning values in core config so they are captured
    | reliably by config:cache and available to module configs.
    |
    */
    'release_api_key' => env('RELEASE_API_KEY', ''),
    'provisioner_ip' => env('PROVISIONER_IP'),
    'provisioner_ips' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('PROVISIONER_IPS', (string) env('PROVISIONER_IP', '')))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Branding Configuration
    |--------------------------------------------------------------------------
    |
    | These values define the branding for your application. Always use
    | config('astero.branding.xxx') instead of env('BRANDING_XXX') in views
    | to ensure values work correctly after config:cache.
    |
    */
    'branding' => [
        'name' => env('BRANDING_NAME', 'Astero'),
        'website' => env('BRANDING_WEBSITE', '#'),
        'logo' => env('BRANDING_LOGO', ''),
        'icon' => env('BRANDING_ICON', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Website Plans
    |--------------------------------------------------------------------------
    */
    'website_plans' => [
        'basic' => [
            'label' => 'Basic',
            'backend_template' => 'astero-basic',
        ],
        'premium' => [
            'label' => 'Premium',
            'backend_template' => 'astero-premium',
        ],
        'business' => [
            'label' => 'Business',
            'backend_template' => 'astero-business',
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'backend_template' => 'astero-enterprise',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agency Plans
    |--------------------------------------------------------------------------
    */
    'agency_plans' => [
        'starter' => ['label' => 'Starter', 'whitelabel' => false],
        'growth' => ['label' => 'Growth', 'whitelabel' => false],
        'reseller' => ['label' => 'Reseller', 'whitelabel' => true],
        'custom' => ['label' => 'Custom', 'whitelabel' => true],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Autologin (Development)
    |--------------------------------------------------------------------------
    |
    | When APP_ENV=local and this is set to a valid user id, the app will
    | automatically authenticate as that user on every web request.
    |
    | This is intended for local development and automated agents only.
    |
    */
    'autologin_user_id' => env('ASTERO_AUTOLOGIN_USER_ID'),

    /*
    |--------------------------------------------------------------------------
    | 404 Logs
    |--------------------------------------------------------------------------
    |
    | Configure the application-level 404 logging system.
    |
    */
    'not_found_logs' => [
        // Max number of 404 logs per IP per minute.
        'rate_limit_per_minute' => (int) env('NOT_FOUND_LOG_RATE_LIMIT_PER_MINUTE', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTML Minification
    |--------------------------------------------------------------------------
    |
    | Enable or disable HTML minification for frontend responses.
    |
    */
    'html_minification_enabled' => env('HTML_MINIFICATION_ENABLED', true),
];
