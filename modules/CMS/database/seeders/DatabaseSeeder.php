<?php

namespace Modules\CMS\Database\Seeders;

use App\Services\SettingsCacheService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            MenuSeeder::class,
            SeoSettingSeeder::class,
        ]);

        resolve(SettingsCacheService::class)->invalidate('CMS seeder completed');
        resolve(SettingsCacheService::class)->invalidateTableExistsCache();

        $this->call([
            CmsDefaultContentSeeder::class,
            RedirectionSeeder::class,
        ]);
    }
}
