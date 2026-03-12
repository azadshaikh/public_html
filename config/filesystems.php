<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('STORAGE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        // Private disk is used to store private files which are not accessible to public.
        /*
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'url' => env('APP_URL').'/storage/private',
            'visibility' => env('FILESYSTEM_PRIVATE_VISIBILITY', 'private'),
            'throw' => env('FILESYSTEM_PRIVATE_THROW', false),
        ],
        */

        // Public disk is used to store public files which are accessible to public.
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => env('FILESYSTEM_PUBLIC_VISIBILITY', 'public'),
            'throw' => env('FILESYSTEM_PUBLIC_THROW', false),
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('STORAGE_CDN_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => env('AWS_S3_VISIBILITY', 'public'),
            'throw' => env('AWS_S3_THROW', false),
            'report' => env('AWS_S3_REPORT', false),
        ],
        'ftp' => [
            'driver' => 'ftp',
            'host' => env('FTP_HOST', 'storage.bunnycdn.com'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'url' => env('STORAGE_CDN_URL'),
            'root' => env('FTP_ROOT', ''),
            'port' => (int) env('FTP_PORT', 21),
            'passive' => (bool) env('FTP_PASSIVE', true),
            'timeout' => (int) env('FTP_TIMEOUT', 30),
            'ssl' => (bool) env('FTP_SSL', true),
            'ssl_mode' => env('FTP_SSL_MODE', 'explicit'),
            'ignorePassiveAddress' => (bool) env('FTP_IGNORE_PASSIVE_ADDRESS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
        // public_path('assets') => resource_path('assets'),
        // public_path('js') => resource_path('js'),
        // public_path('css') => resource_path('css'),
        // public_path('vendors') => resource_path('vendors'),
    ],

];
