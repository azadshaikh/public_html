<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Support\ModuleAutoloader;
use App\Modules\Support\ModuleManifest;
use Illuminate\Filesystem\Filesystem;

class ModuleInspector
{
    public function __construct(
        private readonly ModuleManager $modules,
        private readonly Filesystem $files,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function inspectAll(): array
    {
        ModuleAutoloader::register($this->modules->all()->all());

        return $this->modules->all()
            ->map(fn (ModuleManifest $module): array => $this->inspectManifest($module))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(string $module): array
    {
        ModuleAutoloader::register($this->modules->all()->all());

        return $this->inspectManifest($this->modules->findOrFail($module));
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectManifest(ModuleManifest $module): array
    {
        $issues = [];
        $checks = [
            'provider' => $this->classCheck('provider', $module->provider, $module->providerPath(), $issues),
            'page_root' => $this->pathCheck('page_root', $module->pageRootPath, true, $issues),
            'abilities' => $this->optionalPathCheck('abilities', $module->abilitiesPath, $issues),
            'navigation' => $this->optionalPathCheck('navigation', $module->navigationPath, $issues),
            'database_seeder' => $this->classCheck('database_seeder', $module->databaseSeederClass, $module->databaseSeederPath(), $issues),
            'route_files' => array_values(array_map(
                fn (string $path, string $key): array => $this->pathCheck('route:'.$key, $path, false, $issues),
                $module->routeFiles,
                array_keys($module->routeFiles),
            )),
        ];

        return [
            ...$module->toManagementArray(),
            'basePath' => $module->basePath,
            'appPath' => $module->appPath,
            'checks' => $checks,
            'issues' => $issues,
            'issuesCount' => count($issues),
        ];
    }

    /**
     * @param  array<int, string>  $issues
     * @return array<string, mixed>
     */
    private function pathCheck(string $label, string $path, bool $directory, array &$issues): array
    {
        $exists = $directory
            ? $this->files->isDirectory($path)
            : $this->files->exists($path);

        if (! $exists) {
            $issues[] = sprintf('Missing %s at [%s].', str_replace('_', ' ', $label), $path);
        }

        return [
            'label' => $label,
            'path' => $path,
            'exists' => $exists,
            'type' => $directory ? 'directory' : 'file',
        ];
    }

    /**
     * @param  array<int, string>  $issues
     * @return array<string, mixed>
     */
    private function optionalPathCheck(string $label, ?string $path, array &$issues): array
    {
        if ($path === null) {
            return [
                'label' => $label,
                'path' => null,
                'exists' => false,
                'optional' => true,
                'type' => 'file',
            ];
        }

        return [
            ...$this->pathCheck($label, $path, false, $issues),
            'optional' => true,
        ];
    }

    /**
     * @param  array<int, string>  $issues
     * @return array<string, mixed>
     */
    private function classCheck(string $label, string $class, string $path, array &$issues): array
    {
        $pathExists = $this->files->exists($path);
        $classExists = class_exists($class);

        if (! $pathExists) {
            $issues[] = sprintf('Missing %s class file at [%s].', str_replace('_', ' ', $label), $path);
        }

        if (! $classExists) {
            $issues[] = sprintf('Unable to autoload %s class [%s].', str_replace('_', ' ', $label), $class);
        }

        return [
            'label' => $label,
            'class' => $class,
            'path' => $path,
            'pathExists' => $pathExists,
            'classExists' => $classExists,
        ];
    }
}
