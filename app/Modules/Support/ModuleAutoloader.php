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
            self::$prefixes[$module->namespace] = $module->appPath;
        }

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
            $filePath = $basePath.DIRECTORY_SEPARATOR.$relativePath;

            if (is_file($filePath)) {
                require_once $filePath;
            }

            return;
        }
    }
}
