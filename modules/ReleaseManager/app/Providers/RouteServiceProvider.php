<?php

namespace Modules\ReleaseManager\Providers;

use App\Modules\ModuleManager;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        // Manager checks if module is active
        if (! app(ModuleManager::class)->isEnabled('ReleaseManager')) {
            return;
        }

        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        $path = base_path('modules/ReleaseManager/routes/web.php');
        if (file_exists($path)) {
            Route::middleware('web')->group($path);
        }
    }

    protected function mapApiRoutes(): void
    {
        $path = base_path('modules/ReleaseManager/routes/api.php');
        if (file_exists($path)) {
            // New convention: /api/release-manager/v1/...
            Route::middleware('api')->prefix('api/release-manager')->name('api.')->group($path);

            // Legacy convention
            Route::middleware('api')->prefix('api')->group($path);
        }
    }
}
