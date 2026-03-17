<?php

namespace App\Modules\Support;

final class ModuleAutoloader
{
    /**
     * @var array<string, string>
     */
    private static array $prefixes = [];

    private static bool $registered = false;

    /**
     * @param  array<int, ModuleManifest>  $modules
     */
    public static function register(array $modules): void
    {
        foreach ($modules as $module) {
            self::registerPrefix(rtrim($module->namespace, '\\').'\\Database\\Factories\\', $module->databaseFactoriesPath());
            self::registerPrefix(rtrim($module->namespace, '\\').'\\Database\\Seeders\\', $module->databaseSeedersPath());
            self::registerPrefix($module->namespace, $module->appPath);
        }

        uksort(self::$prefixes, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        if (self::$registered) {
            return;
        }

        spl_autoload_register(self::autoload(...), throw: true, prepend: true);

        self::$registered = true;
    }

    private static function autoload(string $class): void
    {
        foreach (self::$prefixes as $prefix => $basePath) {
            if (! str_starts_with($class, $prefix)) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass).'.php';
            $filePath = rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$relativePath;

            if (is_file($filePath)) {
                require_once $filePath;

                return;
            }

            return;
        }
    }

    private static function registerPrefix(string $prefix, string $basePath): void
    {
        if ($basePath === '') {
            return;
        }

        self::$prefixes[$prefix] = $basePath;
    }
}
