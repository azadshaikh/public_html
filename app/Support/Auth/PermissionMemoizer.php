<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

final class PermissionMemoizer
{
    /**
     * @var array<string, bool>
     */
    private static array $cache = [];

    /**
     * Check if a user can perform a permission, memoized per user+permission.
     */
    public static function can(string $permission, mixed $user = null): bool
    {
        if ($permission === '') {
            return false;
        }

        $user ??= Auth::user();

        if (! $user instanceof Authenticatable || ! $user instanceof AuthorizableContract) {
            return false;
        }

        $cacheKey = self::buildCacheKey($user, $permission);

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        return self::$cache[$cacheKey] = $user->can($permission);
    }

    /**
     * Clear memoized permissions.
     */
    public static function clear(): void
    {
        self::$cache = [];
    }

    private static function buildCacheKey(Authenticatable $user, string $permission): string
    {
        $identifier = $user->getAuthIdentifier();

        if ($identifier === null || $identifier === '') {
            $identifier = spl_object_id($user);
        }

        return sprintf('%s:%s:%s', $user::class, (string) $identifier, $permission);
    }
}
