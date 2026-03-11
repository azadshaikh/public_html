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
     * Seed the application's database.
     */
    public function run(): void
    {
        if ($this->command->getLaravel()->environment('local')) {
            $this->call(LocalUserSeeder::class);
        }

        $enabledModules = resolve(ModuleManager::class)->enabled();

        ModuleAutoloader::register($enabledModules->all());

        $moduleSeeders = $enabledModules
            ->map(fn ($module): string => rtrim($module->namespace, '\\').'\\Database\\Seeders\\DatabaseSeeder')
            ->filter(fn (string $class): bool => class_exists($class))
            ->values()
            ->all();

        if ($moduleSeeders !== []) {
            $this->call($moduleSeeders);
        }
    }
}
