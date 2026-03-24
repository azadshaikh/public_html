<?php

namespace Modules\AIRegistry\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'AIRegistry';

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
        if (! Module::find('AIRegistry')?->isEnabled()) {
            return;
        }

        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    protected function mapApiRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api/ai-registry')
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
