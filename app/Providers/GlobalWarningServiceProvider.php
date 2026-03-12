<?php

namespace App\Providers;

use App\Services\GlobalWarningService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Shares global warnings with views.
 *
 * Modules register their own warnings via GlobalWarningService::registerCollector()
 * in their own service providers.
 */
class GlobalWarningServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register core app warnings
        $this->registerGlobalWarnings();

        // Share warnings with topbar view using view composer
        // This runs lazily - collectors only execute when topbar is rendered
        View::composer('layouts.partials.app.topbar', function ($view): void {
            // Only run collectors if user is authenticated
            if (auth()->check()) {
                $view->with('globalWarnings', GlobalWarningService::getAll());
            }
        });
    }

    /**
     * Register global warnings for core app settings.
     */
    protected function registerGlobalWarnings(): void
    {
        // Development Mode Warning
        GlobalWarningService::registerCollector('development_mode', function (): ?array {
            // Only show to users who can manage settings
            if (! auth()->check() || ! auth()->user()->can('manage_system_settings')) {
                return null;
            }

            $devMode = setting('development_mode_enabled', 'false');
            $isEnabled = in_array($devMode, ['true', true, '1', 1], true);

            if ($isEnabled) {
                return [
                    'title' => __('Development Mode Enabled'),
                    'message' => __('Caching is disabled. Response cache, HTML minification, and CDN cache headers are all turned off.'),
                    'type' => 'warning',
                    'icon' => 'ri-code-box-line',
                    'action' => [
                        'label' => __('general.fix_now'),
                        'url' => route('app.settings.index', ['section' => 'development-settings-section']),
                    ],
                ];
            }

            return null;
        });
    }
}
