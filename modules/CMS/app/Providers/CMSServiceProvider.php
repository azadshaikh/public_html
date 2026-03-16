<?php

declare(strict_types=1);

namespace Modules\CMS\Providers;

use App\Modules\Support\ModuleServiceProvider;
use App\Services\GlobalWarningService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Modules\CMS\Http\Middleware\ThemeMiddleware;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Menu;
use Modules\CMS\Models\Redirection;
use Modules\CMS\Observers\CmsPostSitemapObserver;
use Modules\CMS\Observers\CmsPostTaxonomyObserver;
use Modules\CMS\Observers\MenuObserver;
use Modules\CMS\Observers\RedirectionObserver;
use Modules\CMS\Repositories\ThemeRepository;
use Modules\CMS\Services\Builder\ThemeBlockService;
use Modules\CMS\Services\CmsUrlService;
use Modules\CMS\Services\MenuCacheService;
use Modules\CMS\Services\RedirectionCacheService;
use Modules\CMS\Services\TaxonomyCacheService;
use Modules\CMS\Services\ThemeConfigService;
use Modules\CMS\Services\ThemeDataService;
use Modules\CMS\Services\ThemeOptionsCacheService;
use Modules\CMS\Services\ThemeValidationService;
use Modules\CMS\Services\TwigService;
use Modules\CMS\Transformers\ContentTransformer;
use Modules\CMS\View\Components\AdminBar;
use Modules\CMS\View\Components\ContentForm;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CMSServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'cms';
    }

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        $this->registerThemeServices();
        $this->registerCacheServices();
        $this->registerMiddlewareAliases();
        $this->registerAllConfigFiles();
    }

    public function boot(): void
    {
        $this->bootTranslations();
        $this->bootViews();
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        $this->registerHelpers();
        $this->registerObservers();
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerGlobalWarnings();
    }

    protected function bootTranslations(): void
    {
        $langPath = $this->modulePath('lang');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'cms');
            $this->loadJsonTranslationsFrom($langPath);
            $this->loadTranslationsFrom($langPath, 'seo');
        }
    }

    protected function bootViews(): void
    {
        $viewsPath = $this->modulePath('resources/views');

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, 'cms');
        }

        Blade::componentNamespace('Modules\\CMS\\View\\Components', 'cms');
        Blade::component('cms.content-form', ContentForm::class);
        Blade::component('cms::admin-bar', AdminBar::class);

        $seoViewsPath = $this->modulePath('resources/views/seo');

        if (is_dir($seoViewsPath)) {
            $this->loadViewsFrom($seoViewsPath, 'seo');
            Blade::componentNamespace('Modules\\CMS\\Seo\\View\\Components', 'seo');
        }
    }

    protected function registerMiddlewareAliases(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('theme', ThemeMiddleware::class);
    }

    protected function registerObservers(): void
    {
        CmsPost::observe(CmsPostSitemapObserver::class);
        CmsPost::observe(CmsPostTaxonomyObserver::class);
        Redirection::observe(RedirectionObserver::class);
        Menu::observe(MenuObserver::class);
    }

    protected function registerCacheServices(): void
    {
        $this->app->singleton(RedirectionCacheService::class);
        $this->app->singleton(MenuCacheService::class);
        $this->app->singleton(TaxonomyCacheService::class);
        $this->app->singleton(ThemeOptionsCacheService::class);
    }

    protected function registerThemeServices(): void
    {
        $this->app->singleton(ThemeRepository::class);
        $this->app->singleton(ThemeValidationService::class);
        $this->app->singleton(ThemeConfigService::class);
        $this->app->singleton(TwigService::class);
        $this->app->singleton(CmsUrlService::class);
        $this->app->singleton(ContentTransformer::class);
        $this->app->singleton(ThemeDataService::class);
        $this->app->singleton(ThemeBlockService::class);
    }

    protected function registerCommands(): void
    {
        $commandsPath = $this->modulePath('app/Console');

        if (! is_dir($commandsPath)) {
            return;
        }

        $classes = [];

        foreach (glob($commandsPath.'/*.php') as $file) {
            $class = '\\Modules\\CMS\\Console\\'.basename($file, '.php');
            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        if ($classes !== []) {
            $this->commands($classes);
        }
    }

    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('cms:publish-scheduled-posts')->everyFiveMinutes()->runInBackground();
        });
    }

    protected function registerHelpers(): void
    {
        $helpersPath = $this->modulePath('app/Helpers');

        if (is_dir($helpersPath)) {
            foreach (glob($helpersPath.'/*.php') as $helperFile) {
                require_once $helperFile;
            }
        }
    }

    protected function registerGlobalWarnings(): void
    {
        if (! class_exists(GlobalWarningService::class)) {
            return;
        }

        GlobalWarningService::registerCollector('seo_visibility', function (): ?array {
            if (! auth()->check() || ! auth()->user()->can('manage_seo_settings')) {
                return null;
            }

            $visibility = setting('seo_search_engine_visibility', 'true');
            $isNoIndex = in_array($visibility, ['false', false, '0', 0], true);

            if ($isNoIndex) {
                return [
                    'title' => __('seo::seo.search_engine_indexing_disabled'),
                    'message' => __('seo::seo.search_engine_warning_message'),
                    'type' => 'warning',
                    'icon' => 'ri-forbid-line',
                    'action' => [
                        'label' => __('general.fix_now'),
                        'url' => route('seo.settings.titlesmeta'),
                    ],
                ];
            }

            return null;
        });
    }

    /**
     * Register all config files from the config directory.
     *
     * CMS has multiple config files (config.php, seo.php, sitemap.php, forms.php, navigation.php, abilities.php).
     */
    protected function registerAllConfigFiles(): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $configPath = $this->modulePath('config');

        if (! is_dir($configPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $configKey = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);

            // config.php → 'cms', other files → 'cms.{name}'
            $key = $configKey === 'config' ? 'cms' : 'cms.'.$configKey;

            $existing = config($key, []);
            $moduleConfig = require $file->getPathname();

            if (is_array($existing) && is_array($moduleConfig)) {
                config([$key => array_replace_recursive($existing, $moduleConfig)]);
            } else {
                config([$key => $moduleConfig]);
            }
        }

        // Alias seo config at top level for backward compatibility
        if (config()->has('cms.seo')) {
            config(['seo' => config('cms.seo')]);
        }
    }
}
