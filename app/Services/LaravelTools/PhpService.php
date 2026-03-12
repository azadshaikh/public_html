<?php

namespace App\Services\LaravelTools;

use PDO;

class PhpService
{
    public function getSummary(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'ini_file' => php_ini_loaded_file() ?: '(none)',
            'memory_limit' => $this->getIniValue('memory_limit'),
            'max_execution_time' => $this->getIniValue('max_execution_time'),
            'opcache_enabled' => $this->isIniEnabled('opcache.enable'),
        ];
    }

    public function getSettingGroups(): array
    {
        return [
            'Runtime' => [
                'memory_limit' => $this->getIniValue('memory_limit'),
                'max_execution_time' => $this->getIniValue('max_execution_time'),
                'max_input_time' => $this->getIniValue('max_input_time'),
                'max_input_vars' => $this->getIniValue('max_input_vars'),
                'post_max_size' => $this->getIniValue('post_max_size'),
                'upload_max_filesize' => $this->getIniValue('upload_max_filesize'),
                'default_socket_timeout' => $this->getIniValue('default_socket_timeout'),
            ],
            'Error Handling' => [
                'display_errors' => $this->getIniValue('display_errors'),
                'display_startup_errors' => $this->getIniValue('display_startup_errors'),
                'log_errors' => $this->getIniValue('log_errors'),
                'error_reporting' => $this->getIniValue('error_reporting'),
            ],
            'OPcache' => [
                'opcache.enable' => $this->getIniValue('opcache.enable'),
                'opcache.validate_timestamps' => $this->getIniValue('opcache.validate_timestamps'),
                'opcache.revalidate_freq' => $this->getIniValue('opcache.revalidate_freq'),
                'opcache.memory_consumption' => $this->getIniValue('opcache.memory_consumption'),
                'opcache.max_accelerated_files' => $this->getIniValue('opcache.max_accelerated_files'),
                'opcache.enable_cli' => $this->getIniValue('opcache.enable_cli'),
            ],
            'Session' => [
                'session.save_handler' => $this->getIniValue('session.save_handler'),
                'session.save_path' => $this->getIniValue('session.save_path'),
                'session.gc_maxlifetime' => $this->getIniValue('session.gc_maxlifetime'),
            ],
            'Paths / Filesystem' => [
                'realpath_cache_size' => $this->getIniValue('realpath_cache_size'),
                'realpath_cache_ttl' => $this->getIniValue('realpath_cache_ttl'),
                'include_path' => $this->getIniValue('include_path'),
                'disable_functions' => $this->getIniValue('disable_functions'),
            ],
        ];
    }

    public function getExtensions(): array
    {
        $extensions = [];

        foreach (get_loaded_extensions() as $extension) {
            $extensions[] = [
                'name' => $extension,
                'version' => phpversion($extension) ?: 'built-in',
            ];
        }

        usort($extensions, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $extensions;
    }

    public function getPdoDrivers(): array
    {
        if (! class_exists(PDO::class)) {
            return [];
        }

        $drivers = PDO::getAvailableDrivers();
        sort($drivers);

        return $drivers;
    }

    private function getIniValue(string $key): string
    {
        $value = ini_get($key);

        if ($value === false || $value === '') {
            return '(not set)';
        }

        if ($value === '1') {
            return 'On';
        }

        if ($value === '0') {
            return 'Off';
        }

        return $value;
    }

    private function isIniEnabled(string $key): bool
    {
        return ini_get($key) === '1';
    }
}
