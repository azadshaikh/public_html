<?php

declare(strict_types=1);

namespace Modules\ChatBot\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'ChatBot';

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
        if (! Module::find($this->name)?->isEnabled()) {
            return;
        }

        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     */
    protected function mapWebRoutes(): void
    {
        $webRoutes = module_path($this->name, 'routes/web.php');

        if (file_exists($webRoutes)) {
            Route::middleware('web')->group($webRoutes);
        }
    }
}
