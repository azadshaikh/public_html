<?php

namespace Database\Seeders;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Get the application's core seeders.
     *
     * @return array<int, class-string<Seeder>>
     */
    public function getSeeders(): array
    {

        $seeders = [
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
            RegistrationSettingsSeeder::class,
            MediaSettingSeeder::class,
            EmailProviderSeeder::class,
            EmailTemplateSeeder::class,
        ];

        return $seeders;
    }

    /**
     * Get the enabled module seeders.
     *
     * @return array<int, class-string<Seeder>>
     */
    public function getModuleSeeders(): array
    {
        $enabledModules = resolve(ModuleManager::class)->enabled();

        ModuleAutoloader::register($enabledModules->all());

        return $enabledModules
            ->map(fn ($module): string => rtrim($module->namespace, '\\').'\\Database\\Seeders\\DatabaseSeeder')
            ->filter(fn (string $class): bool => class_exists($class))
            ->values()
            ->all();
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call($this->getSeeders());

        $moduleSeeders = $this->getModuleSeeders();
        if ($moduleSeeders !== []) {
            $this->call($moduleSeeders);
        }
    }
}
