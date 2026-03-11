<?php

namespace Database\Seeders;

use App\Modules\ModuleManager;
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
        if ($this->command?->getLaravel()->environment('local')) {
            $this->call(LocalUserSeeder::class);
        }

        $moduleSeeders = app(ModuleManager::class)
            ->enabled()
            ->map(fn ($module): string => $module->namespace.'Database\\Seeders\\DatabaseSeeder')
            ->filter(fn (string $class): bool => class_exists($class))
            ->values()
            ->all();

        if ($moduleSeeders !== []) {
            $this->call($moduleSeeders);
        }
    }
}
