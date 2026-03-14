<?php

namespace App\Services\LaravelTools;

use Exception;
use Illuminate\Support\Facades\Artisan;

class ArtisanService
{
    /**
     * Safe Artisan commands that can be executed
     */
    protected array $safeCommands = [
        'astero:recache' => 'Clear all application caches and optionally rebuild them',
        'cache:clear' => 'Clear application cache',
        'config:clear' => 'Clear configuration cache',
        'config:cache' => 'Create a configuration cache file',
        'route:clear' => 'Clear route cache',
        'route:cache' => 'Create a route cache file',
        'view:clear' => 'Clear compiled view files',
        'view:cache' => 'Compile all Blade templates',
        'optimize:clear' => 'Clear all cached files',
        'optimize' => 'Cache the framework bootstrap files',
        'storage:link' => 'Create symbolic link from public/storage to storage/app/public',
        'schedule:list' => 'List all scheduled tasks',
        'about' => 'Display basic information about your application',
    ];

    /**
     * Get safe commands list
     */
    public function getSafeCommands(): array
    {
        return $this->safeCommands;
    }

    /**
     * Check if command is allowed
     */
    public function isAllowed(string $command): bool
    {
        return array_key_exists($command, $this->safeCommands);
    }

    /**
     * Run Artisan command
     */
    public function run(string $command): array
    {
        if (! $this->isAllowed($command)) {
            return [
                'success' => false,
                'error' => __('This command is not allowed.'),
            ];
        }

        try {
            $startTime = microtime(true);
            Artisan::call($command);
            $output = Artisan::output();
            $duration = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => true,
                'output' => $output,
                'duration' => $duration,
                'message' => __('Command executed successfully.'),
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }
}
