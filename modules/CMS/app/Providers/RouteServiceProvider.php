<?php

declare(strict_types=1);

namespace Modules\CMS\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $basePath = dirname(__DIR__, 2);

        $this->mapApiRoutes($basePath);
        $this->mapWebRoutes($basePath);
        $this->mapPermalinkRoutes($basePath);
    }

    protected function mapWebRoutes(string $basePath): void
    {
        Route::middleware('web')->group($basePath.'/routes/web.php');
    }

    protected function mapApiRoutes(string $basePath): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group($basePath.'/routes/api.php');
    }

    /**
     * Permalink routes are loaded LAST to ensure lowest priority (catch-all).
     */
    protected function mapPermalinkRoutes(string $basePath): void
    {
        Route::group([], $basePath.'/routes/permalink.php');
    }
}
