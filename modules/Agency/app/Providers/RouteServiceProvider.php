<?php

namespace Modules\Agency\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Agency';

    /**
     * Called before routes are registered.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        // @phpstan-ignore-next-line nullsafe.neverNull
        if (! Module::find('Agency')?->isEnabled()) {
            return;
        }

        $this->mapWebRoutes();
        $this->mapApiRoutes();
    }

    /**
     * Define the "web" routes for the application.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api/agency')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
