<?php

namespace App\Providers;

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LimitLoginAttempts;
use App\Models\Settings;
use App\Observers\SettingsObserver;
use App\Services\GeoDataService;
use App\Services\GeoIpService;
use App\Services\SettingsCacheService;
use App\Support\Storage\SslTolerantFtpAdapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpConnectionOptions;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(fn (): GeoDataService => new GeoDataService);

        $this->app->singleton(fn (): GeoIpService => new GeoIpService);

        // Register SettingsCacheService as singleton for consistent caching
        $this->app->singleton(fn (): SettingsCacheService => new SettingsCacheService);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ?ExceptionResponse {
            if ($response->request->expectsJson()) {
                return null;
            }

            $status = $response->statusCode();

            if (! in_array($status, [401, 402, 403, 404, 419, 429, 500, 503], true)) {
                return null;
            }

            $message = trim($response->exception->getMessage());

            return $response
                ->usingMiddleware(HandleInertiaRequests::class)
                ->render("errors/{$status}", [
                    'status' => $status,
                    'message' => $status >= 500 || $message === '' ? null : $message,
                ])
                ->withSharedData();
        });

        // Register SSL-tolerant FTP driver to handle BunnyCDN's unclean SSL shutdown
        Storage::extend('ftp', function ($app, array $config): FilesystemAdapter {
            if (! isset($config['root'])) {
                $config['root'] = '';
            }

            $adapter = new SslTolerantFtpAdapter(FtpConnectionOptions::fromArray($config));

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config,
            );
        });

        // Register the LimitLoginAttempts middleware
        Route::aliasMiddleware('limit.login.attempts', LimitLoginAttempts::class);

        RateLimiter::for('api', fn (Request $request) => Limit::perSecond(1)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('geoip', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Register Settings model observer for automatic cache invalidation
        Settings::observe(SettingsObserver::class);
    }
}
