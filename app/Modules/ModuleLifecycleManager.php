<?php

namespace App\Modules;

use App\Modules\Support\ModuleAutoloader;
use App\Modules\Support\ModuleManifest;
use Illuminate\Support\Facades\Artisan;

class ModuleLifecycleManager
{
    public function __construct(protected ModuleManager $moduleManager) {}

    /**
     * @param  array<string, string>  $statuses
     */
    public function syncStatuses(array $statuses): void
    {
        $modulesToEnable = $this->modulesToEnable($statuses);

        foreach ($modulesToEnable as $module) {
            $this->installModule($module);
        }

        $this->moduleManager->writeStatuses($statuses);
    }

    /**
     * @param  array<string, string>  $statuses
     * @return array<int, ModuleManifest>
     */
    protected function modulesToEnable(array $statuses): array
    {
        return $this->moduleManager
            ->all()
            ->filter(function (ModuleManifest $module) use ($statuses): bool {
                $targetStatus = $statuses[$module->name]
                    ?? $statuses[$module->slug]
                    ?? 'disabled';

                return ! $module->enabled && $targetStatus === 'enabled';
            })
            ->values()
            ->all();
    }

    protected function installModule(ModuleManifest $module): void
    {
        ModuleAutoloader::register([$module]);

        $migrationsPath = $module->basePath.'/database/migrations';

        if (is_dir($migrationsPath)) {
            Artisan::call('migrate', [
                '--path' => $migrationsPath,
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        $seederClass = $module->namespace.'Database\\Seeders\\DatabaseSeeder';

        if (class_exists($seederClass)) {
            Artisan::call('db:seed', [
                '--class' => $seederClass,
                '--force' => true,
            ]);
        }
    }
}
