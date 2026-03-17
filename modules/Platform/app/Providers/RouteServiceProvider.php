<?php

declare(strict_types=1);

namespace Modules\Platform\Providers;

use App\Modules\ModuleManager;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        if (! app(ModuleManager::class)->isEnabled('Platform')) {
            return;
        }

        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        $path = base_path('modules/Platform/routes/web.php');

        if (file_exists($path)) {
            Route::middleware('web')->group($path);
        }
    }

    protected function mapApiRoutes(): void
    {
        $path = base_path('modules/Platform/routes/api.php');

        if (file_exists($path)) {
            Route::middleware('api')->prefix('api/platform')->name('api.')->group($path);
        }
    }
}
