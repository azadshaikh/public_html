<?php

namespace App\Console\Commands;

use App\Helpers\NavigationHelper;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;

class ClearNavigationCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'navigation:clear-cache
                            {--all : Clear all sidebar navigation cache}
                            {--user= : Clear cache for specific user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear unified sidebar navigation cache for better performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            $this->clearAllSidebarNavigationCache();
        } elseif ($userId = $this->option('user')) {
            $this->clearUserSidebarNavigationCache($userId);
        } else {
            $this->clearAllSidebarNavigationCache();
        }

        return Command::SUCCESS;
    }

    /**
     * Clear all sidebar navigation cache entries
     */
    private function clearAllSidebarNavigationCache(): void
    {
        $this->info('Clearing all sidebar navigation cache...');

        try {
            $cleared = NavigationHelper::clearAllCache();

            if ($cleared > 0) {
                $this->info(sprintf('✅ Cleared %d sidebar navigation cache entries.', $cleared));
            } else {
                $this->warn('No sidebar navigation cache entries found to clear.');
            }
        } catch (Exception $exception) {
            $this->error('Failed to clear sidebar navigation cache: '.$exception->getMessage());
            $this->info('💡 Alternative: Restart your application or clear all cache with `php artisan cache:clear`');
        }
    }

    /**
     * Clear sidebar navigation cache for specific user
     */
    private function clearUserSidebarNavigationCache($userId): ?int
    {
        $this->info(sprintf('Clearing sidebar navigation cache for user %s...', $userId));

        try {
            $user = User::query()->findOrFail($userId);
            $success = NavigationHelper::clearUserCache($user->id);

            if ($success) {
                $this->info(sprintf('✅ Cleared sidebar navigation cache for user %s.', $userId));
            } else {
                $this->warn(sprintf('No sidebar navigation cache found for user %s.', $userId));
            }
        } catch (Exception $exception) {
            $this->error(sprintf('Error clearing cache for user %s: ', $userId).$exception->getMessage());

            return Command::FAILURE;
        }

        return null;
    }
}
