<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Postmark, Resend, AWS and more. This provides a central location
    | for this type of information, allowing packages to have a single
    | file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'enabled' => env('GOOGLE_AUTH_ENABLED', false),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT'),
    ],

    'github' => [
        'enabled' => env('GITHUB_AUTH_ENABLED', false),
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Authentication Master Toggle
    |--------------------------------------------------------------------------
    |
    | Enable or disable social authentication globally. When disabled, social
    | login buttons won't appear and the settings section will be hidden.
    | Useful for simple websites that don't need OAuth integration.
    |
    */
    'social_auth' => [
        'enabled' => env('SOCIAL_AUTH_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the OpenAI API used by the Astero Builder AI assistant.
    | Set OPENAI_API_KEY in your .env file to enable the AI features.
    |
    */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 1000),
    ],
];
