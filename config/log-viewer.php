<?php

use App\Http\Middleware\EnsureSuperUserAccess;
use Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer;
use Opcodes\LogViewer\Http\Middleware\EnsureFrontendRequestsAreStateful;

return [

    /*
    |--------------------------------------------------------------------------
    | Log Viewer
    |--------------------------------------------------------------------------
    | Log Viewer can be disabled, so it's no longer accessible via browser.
    |
    */

    'enabled' => env('LOG_VIEWER_ENABLED', true),

    'api_only' => env('LOG_VIEWER_API_ONLY', false),

    'require_auth_in_production' => env('LOG_VIEWER_REQUIRE_AUTH_IN_PRODUCTION', true),

    /*
    |--------------------------------------------------------------------------
    | Log Viewer Domain
    |--------------------------------------------------------------------------
    | You may change the domain where Log Viewer should be active.
    | If the domain is empty, all domains will be valid.
    |
    */

    'route_domain' => env('LOG_VIEWER_ROUTE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Log Viewer Route
    |--------------------------------------------------------------------------
    | Log Viewer will be available under this URL.
    |
    */

    'route_path' => config('app.admin_slug').'/masters/logs/log-viewer',

    /*
    |--------------------------------------------------------------------------
    | Back to system URL
    |--------------------------------------------------------------------------
    | When set, displays a link to easily get back to this URL.
    | Set to `null` to hide this link.
    |
    | Optional label to display for the above URL.
    |
    */

    'back_to_system_url' => config('app.url').'/'.config('app.admin_slug').'/dashboard',

    'back_to_system_label' => env('LOG_VIEWER_BACK_TO_SYSTEM_LABEL'), // Displayed by default: "Back to {{ app.name }}"

    /*
    |--------------------------------------------------------------------------
    | Log Viewer time zone.
    |--------------------------------------------------------------------------
    | The time zone in which to display the times in the UI. Defaults to
    | the application's timezone defined in config/app.php.
    |
    */

    'timezone' => env('LOG_VIEWER_TIMEZONE'),

    /*
    |--------------------------------------------------------------------------
    | Log Viewer route middleware.
    |--------------------------------------------------------------------------
    | Optional middleware to use when loading the initial Log Viewer page.
    |
    */

    'middleware' => [
        'web',
        'auth',
        EnsureSuperUserAccess::class,
        AuthorizeLogViewer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Viewer API middleware.
    |--------------------------------------------------------------------------
    | Optional middleware to use on every API request. The same API is also
    | used from within the Log Viewer user interface.
    |
    */

    'api_middleware' => [
        EnsureFrontendRequestsAreStateful::class,
        EnsureSuperUserAccess::class,
        AuthorizeLogViewer::class,
    ],

    'api_stateful_domains' => env('LOG_VIEWER_API_STATEFUL_DOMAINS') ? explode(',', (string) env('LOG_VIEWER_API_STATEFUL_DOMAINS')) : null,

    /*
    |--------------------------------------------------------------------------
    | Log Viewer Remote hosts.
    |--------------------------------------------------------------------------
    | Log Viewer supports viewing Laravel logs from remote hosts. They must
    | be running Log Viewer as well. Below you can define the hosts you
    | would like to show in this Log Viewer instance.
    |
    */

    'hosts' => [
        'local' => [
            'name' => ucfirst((string) env('APP_ENV', 'local')),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Include file patterns
    |--------------------------------------------------------------------------
    |
    */

    'include_files' => [
        '*.log',
        '**/*.log',

        // You can include paths to other log types as well, such as apache, nginx, and more.
        // This key => value pair can be used to rename and group multiple paths into one folder in the UI.
        '/var/log/httpd/*' => 'Apache',
        '/var/log/nginx/*' => 'Nginx',

        // MacOS Apple Silicon logs
        '/opt/homebrew/var/log/nginx/*',
        '/opt/homebrew/var/log/httpd/*',
        '/opt/homebrew/var/log/php-fpm.log',
        '/opt/homebrew/var/log/postgres*log',
        '/opt/homebrew/var/log/supervisor*log',

        // '/absolute/paths/supported',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclude file patterns.
    |--------------------------------------------------------------------------
    | This will take precedence over included files.
    |
    */

    'exclude_files' => array_filter(explode(',', (string) env('LOG_VIEWER_EXCLUDE_FILES', ''))),

    /*
    |--------------------------------------------------------------------------
    | Hide unknown files.
    |--------------------------------------------------------------------------
    | The include/exclude options above might catch files which are not
    | logs supported by Log Viewer. In that case, you can hide them
    | from the UI and API calls by setting this to true.
    |
    */

    'hide_unknown_files' => env('LOG_VIEWER_HIDE_UNKNOWN_FILES', true),

    /*
    |--------------------------------------------------------------------------
    |  Shorter stack trace filters.
    |--------------------------------------------------------------------------
    | Lines containing any of these strings will be excluded from the full log.
    | This setting is only active when the function is enabled via the user interface.
    |
    */

    'shorter_stack_trace_excludes' => explode(',', (string) env('LOG_VIEWER_SHORTER_STACK_TRACE_EXCLUDES', '/vendor/symfony/,/vendor/laravel/framework/,/vendor/fruitcake/laravel-debugbar/,/vendor/barryvdh/laravel-debugbar/')),

    /*
    |--------------------------------------------------------------------------
    | Cache driver
    |--------------------------------------------------------------------------
    | Cache driver to use for storing the log indices. Indices are used to speed up
    | log navigation. Defaults to your application's default cache driver.
    |
    */

    'cache_driver' => env('LOG_VIEWER_CACHE_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | Cache key prefix
    |--------------------------------------------------------------------------
    | Log Viewer prefixes all the cache keys created with this value. If for
    | some reason you would like to change this prefix, you can do so here.
    | The format of Log Viewer cache keys is:
    | {prefix}:{version}:{rest-of-the-key}
    |
    */

    'cache_key_prefix' => env('LOG_VIEWER_CACHE_KEY_PREFIX', 'lv'),

    /*
    |--------------------------------------------------------------------------
    | Chunk size when scanning log files lazily
    |--------------------------------------------------------------------------
    | The size in MB of files to scan before updating the progress bar when searching across all files.
    |
    */

    'lazy_scan_chunk_size_in_mb' => env('LOG_VIEWER_LAZY_SCAN_CHUNK_SIZE_IN_MB', 50),

    'strip_extracted_context' => env('LOG_VIEWER_STRIP_EXTRACTED_CONTEXT', true),

    /*
    |--------------------------------------------------------------------------
    | Per page options
    |--------------------------------------------------------------------------
    | Define the available options for number of results per page
    |
    */

    'per_page_options' => array_map(intval(...), explode(',', (string) env('LOG_VIEWER_PER_PAGE_OPTIONS', '10,25,50,100,250,500'))),
];
