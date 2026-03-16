<?php

namespace Modules\CMS\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\CMS\Models\Menu;

class ClearMenuCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'menu:clear-cache {location?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear menu caches for all locations or a specific location';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $location = $this->argument('location');

        if ($location) {
            // Clear specific location cache
            Cache::forget('menu_'.$location);
            $this->info('Cleared cache for menu location: '.$location);
        } else {
            // Clear all menu caches
            $locations = Menu::getAvailableLocations();
            $clearedCount = 0;

            foreach (array_keys($locations) as $loc) {
                Cache::forget('menu_'.$loc);
                $clearedCount++;
            }

            // Clear all menu items caches
            $menus = Menu::all();
            foreach ($menus as $menu) {
                Cache::forget('menu_items_'.$menu->id);
            }

            $this->info(sprintf('Cleared cache for %d menu locations and ', $clearedCount).$menus->count().' menu item caches');
        }

        // Clear view cache
        $this->call('view:clear');

        $this->info('Menu cache clearing completed!');
    }
}
