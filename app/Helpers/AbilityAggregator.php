<?php

namespace App\Helpers;

use App\Models\User;
use App\Modules\ModuleManager;
use Illuminate\Support\Facades\Log;

class AbilityAggregator
{
    /**
     * Resolve all module-provided abilities for a given user.
     *
     * Each enabled module may publish a `config/abilities.php` file that returns
     * an array of ability definitions. Each definition maps a camelCase key
     * (shared with the frontend) to either:
     *   - a permission string  → resolved via `$user->can($permission)`
     *   - a boolean            → used as-is (e.g. for super-user-only abilities)
     *
     * Example module `config/abilities.php`:
     *
     *     return [
     *         'viewTodos'    => 'view_todos',
     *         'addTodos'     => 'add_todos',
     *         'editTodos'    => 'edit_todos',
     *         'deleteTodos'  => 'delete_todos',
     *         'restoreTodos' => 'restore_todos',
     *     ];
     *
     * @return array<string, bool>
     */
    public static function resolve(?User $user): array
    {
        $moduleManager = resolve(ModuleManager::class);
        $abilities = [];

        foreach ($moduleManager->enabled() as $module) {
            $configPath = base_path(sprintf('modules/%s/config/abilities.php', $module->name));

            if (! file_exists($configPath)) {
                continue;
            }

            try {
                $definitions = include $configPath;

                if (! is_array($definitions)) {
                    continue;
                }

                foreach ($definitions as $key => $value) {
                    if (is_bool($value)) {
                        $abilities[$key] = $user ? $value : false;
                    } else {
                        $abilities[$key] = $user?->can($value) ?? false;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to load module abilities', [
                    'module' => $module->slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $abilities;
    }
}
