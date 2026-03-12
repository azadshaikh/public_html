<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Geo Data API Configuration
    |--------------------------------------------------------------------------
    */
    'api_base_url' => 'https://cdn.jsdelivr.net/npm/geo-data-api@latest/dist/api/v1',

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache_enabled' => true,
    'cache_ttl' => 7776000, // 90 days - geo data rarely changes
    'cache_prefix' => 'geodata',

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'countries' => '/countries.json',
        'country' => '/countries/{code}.json',
        'states_by_country' => '/states/country/{code}.json',
        'state' => '/states/{code}.json',
        'cities_by_country' => '/cities/country/{code}.json',
        'cities_by_state' => '/cities/state/{code}.json',
        'city' => '/cities/{id}.json',
        'search' => '/search/combined.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'timeout' => 10, // seconds
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | MaxMind GeoIP Configuration
    |--------------------------------------------------------------------------
    | Database is downloaded via a-update-geoip script on server.
    | Shared across all websites at /usr/local/hestia/data/astero/geoip/
    */
    'maxmind' => [
        'enabled' => true,
        'database_path' => '/usr/local/hestia/data/astero/geoip/GeoLite2-City.mmdb',
        'edition' => 'GeoLite2-City',
    ],
];
