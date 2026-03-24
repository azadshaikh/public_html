<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials and URL for communicating with the Platform (console)
    | instance. The agency secret key is issued during provisioning and
    | injected into the .env file automatically by HestiaConfigureEnvCommand.
    |
    */

    // @phpstan-ignore-next-line larastan.noEnvCallsOutsideOfConfig
    'platform_api_url' => env('PLATFORM_API_URL', 'https://platform.astero.net.in'),
    // @phpstan-ignore-next-line larastan.noEnvCallsOutsideOfConfig
    'agency_secret_key' => env('AGENCY_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Free Subdomain Configuration
    |--------------------------------------------------------------------------
    |
    | The base domain used for free customer subdomains. Customer websites
    | created without a custom domain will be hosted under this domain.
    | Example: if set to "sites.example.com", a customer site "acme" becomes
    | "acme.sites.example.com".
    |
    */

    // @phpstan-ignore-next-line larastan.noEnvCallsOutsideOfConfig
    'free_subdomain' => env('AGENCY_FREE_SUBDOMAIN', 'astero.site'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | URL that Platform will POST webhook events to. Leave null to use
    | the auto-detected APP_URL + /api/agency/v1/webhooks/platform.
    |
    */

    // @phpstan-ignore-next-line larastan.noEnvCallsOutsideOfConfig
    'webhook_url' => env('AGENCY_WEBHOOK_URL'),
];
