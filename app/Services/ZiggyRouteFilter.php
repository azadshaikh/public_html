<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tighten\Ziggy\BladeRouteGenerator;
use Tighten\Ziggy\Ziggy;

class ZiggyRouteFilter
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    private const CACHE_TTL = 3600;

    /**
     * Cache version token used to invalidate all generated route sets.
     */
    private const CACHE_VERSION_KEY = 'ziggy_routes:version';

    /**
     * Map of Ziggy group names to the permission required to access them.
     *
     * Groups not listed here are handled by special logic in resolveGroups().
     *
     * @var array<string, string>
     */
    private const GROUP_PERMISSIONS = [
        'users' => 'view_users',
        'roles' => 'view_roles',
    ];

    /**
     * Groups that unlock when the user has any one of the listed permissions.
     *
     * @var array<string, list<string>>
     */
    private const GROUP_ANY_PERMISSIONS = [
        'logs' => [
            'view_activity_logs',
            'view_login_attempts',
            'view_not_found_logs',
        ],
    ];

    /**
     * Groups that only super users may access.
     *
     * @var list<string>
     */
    private const SUPER_USER_GROUPS = [
        'masters',
        'log_viewer',
        'broadcast',
    ];

    /**
     * Resolve the Ziggy groups the given user is allowed to see.
     *
     * @return list<string>
     */
    public function resolveGroups(?User $user): array
    {
        // Guests only see public routes.
        if (! $user) {
            return ['public'];
        }

        // Super users see everything — return null to skip filtering entirely.
        if ($user->isSuperUser()) {
            return [];
        }

        $groups = ['public', 'authenticated'];

        // Permission-gated groups.
        foreach (self::GROUP_PERMISSIONS as $group => $permission) {
            if ($user->can($permission)) {
                $groups[] = $group;
            }
        }

        foreach (self::GROUP_ANY_PERMISSIONS as $group => $permissions) {
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    $groups[] = $group;

                    break;
                }
            }
        }

        // Super-user-only groups are excluded for regular users.

        return $groups;
    }

    /**
     * Build the cache key for a user's route set.
     */
    public function cacheKey(?User $user): string
    {
        $cacheVersion = $this->cacheVersion();
        $routeSignature = $this->routeSignature();

        if (! $user) {
            return sprintf('ziggy_routes:v%d:guest:%s', $cacheVersion, $routeSignature);
        }

        // Use the primary role ID so all users sharing a role share the cache.
        $roleId = $user->roles->first()?->id ?? 0;

        $permissionSignature = $this->permissionSignature($user);

        return sprintf(
            'ziggy_routes:v%d:role:%d:%s:%s',
            $cacheVersion,
            $roleId,
            $permissionSignature,
            $routeSignature,
        );
    }

    private function cacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    private function permissionSignature(User $user): string
    {
        $permissions = $user->getAllPermissions()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        return md5(implode('|', $permissions));
    }

    private function routeSignature(): string
    {
        $signatureParts = [
            'admin_slug:'.config('app.admin_slug'),
            'ziggy_config:'.(file_exists(config_path('ziggy.php')) ? filemtime(config_path('ziggy.php')) : 'missing'),
        ];

        $cachedRoutesPath = app()->getCachedRoutesPath();

        if (file_exists($cachedRoutesPath)) {
            $signatureParts[] = 'route_cache:'.filemtime($cachedRoutesPath);
        } else {
            foreach (glob(base_path('routes/*.php')) ?: [] as $routeFile) {
                $signatureParts[] = basename($routeFile).':'.filemtime($routeFile);
            }
        }

        return md5(implode('|', $signatureParts));
    }

    /**
     * Render the Ziggy script tag with routes filtered for the current user.
     *
     * Super users get the unfiltered output (all routes). Other users get
     * only the groups their permissions allow. The rendered HTML is cached
     * per role to avoid rebuilding on every request.
     */
    public function render(?User $user, ?string $nonce = null): string
    {
        $groups = $this->resolveGroups($user);

        // Super users — no filtering, use the standard generator.
        if ($groups === []) {
            return app(BladeRouteGenerator::class)->generate(nonce: $nonce);
        }

        $cacheKey = $this->cacheKey($user);

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            function () use ($groups, $nonce): string {
                $ziggy = new Ziggy($groups);

                $routeFunction = config('ziggy.skip-route-function')
                    ? ''
                    : file_get_contents(base_path('vendor/tightenco/ziggy/dist/route.umd.js'));

                $nonceAttr = $nonce ? " nonce=\"{$nonce}\"" : '';

                return "<script type=\"text/javascript\"{$nonceAttr}>const Ziggy={$ziggy->toJson()};{$routeFunction}</script>";
            },
        );
    }

    /**
     * Clear all cached Ziggy route sets.
     */
    public static function clearCache(): void
    {
        $currentVersion = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::forever(self::CACHE_VERSION_KEY, $currentVersion + 1);

        // Forget common keys. For a full flush, use cache:clear.
        Cache::forget('ziggy_routes:guest');
        Cache::forget(self::CACHE_VERSION_KEY.':legacy');

        // Flush role-based caches by pattern (works with tagged caches).
        // For drivers that don't support tags, we clear individual known keys.
        $roles = Role::pluck('id');

        foreach ($roles as $roleId) {
            Cache::forget("ziggy_routes:role:{$roleId}");
        }
    }
}
