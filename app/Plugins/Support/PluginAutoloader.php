<?php

namespace App\Plugins\Support;

final class PluginAutoloader
{
    /**
     * @var array<string, string>
     */
    protected static array $prefixes = [];

    protected static bool $registered = false;

    /**
     * @param  array<int, PluginManifest>  $plugins
     */
    public static function register(array $plugins): void
    {
        foreach ($plugins as $plugin) {
            self::$prefixes[$plugin->namespace] = $plugin->appPath;
        }

        if (self::$registered) {
            return;
        }

        spl_autoload_register(self::autoload(...), throw: true, prepend: true);

        self::$registered = true;
    }

    protected static function autoload(string $class): void
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
