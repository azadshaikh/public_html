<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiter Cache Store
    |--------------------------------------------------------------------------
    |
    | The rate limiter typically uses your default cache store; however, you
    | may specify an alternate store here to isolate rate limiting keys.
    |
    */

    'limiter' => env('CACHE_LIMITER', env('CACHE_STORE', 'database')),

    /*
    |--------------------------------------------------------------------------
    | Cache Serializable Classes
    |--------------------------------------------------------------------------
    |
    | Laravel 13 restricts cache unserialization by default. This application
    | stores arrays and scalars in cache, so object unserialization remains
    | disabled at the framework level.
    |
    */

    'serializable_classes' => false,

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file",
    |                    "dynamodb", "octane", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE', 'cache_locks'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing database and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('WEBSITE_ID', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | Memory Cache (Request-Scoped)
    |--------------------------------------------------------------------------
    |
    | Enable in-memory caching for the current request. This prevents redundant
    | calls to the cache driver within the same request.
    | Disable this for debugging to see all cache hits in debugbar.
    |
    | Performance: Even with Redis (~0.1-0.5ms/call), memory cache saves
    | significant time when cache keys are accessed multiple times per request.
    |
    */

    'use_memory_cache' => env('CACHE_USE_MEMORY', true),

];
