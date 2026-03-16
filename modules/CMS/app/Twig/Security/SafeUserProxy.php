<?php

namespace Modules\CMS\Twig\Security;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Stringable;

/**
 * A safe proxy wrapper for User models in Twig templates.
 * Only exposes whitelisted, safe properties and methods.
 * Blocks access to sensitive data like passwords, tokens, etc.
 */
class SafeUserProxy implements Stringable
{
    /**
     * Properties that are safe to expose in templates
     */
    private const array ALLOWED_PROPERTIES = [
        'id',
        'name',
        'first_name',
        'last_name',
        'email',
        'avatar',
        'avatar_url',
        'profile_photo_url',
        'initials',
        'created_at',
        'updated_at',
        'email_verified_at',
        'is_admin',
        'role',
        'roles',
        'permissions',
        'status',
        'bio',
        'website',
        'location',
        'timezone',
        'locale',
    ];

    /**
     * Methods that are safe to call in templates
     */
    private const array ALLOWED_METHODS = [
        'getkey',
        'getkeyname',
        'getauthidentifier',
        'getauthidentifiername',
        'hasverifiedemail',
        'can',
        'cannot',
        'hasrole',
        'hasanyrole',
        'hasallroles',
        'haspermissionto',
        'hasanypermission',
        'hasallpermissions',
        'getrole',
        'getroles',
        'getpermissions',
        'getavatarurl',
        'getprofilephotourl',
        'getinitials',
        'getfullname',
        'getdisplayname',
    ];

    public function __construct(private readonly ?Authenticatable $user) {}

    /**
     * Magic getter for properties
     */
    public function __get(string $name): mixed
    {
        if (! $this->user instanceof Authenticatable) {
            return null;
        }

        $nameLower = strtolower($name);

        // Check if property is in allowed list
        if (in_array($nameLower, array_map(strtolower(...), self::ALLOWED_PROPERTIES))) {
            return $this->user->{$name} ?? null;
        }

        // Block access to non-whitelisted properties
        return null;
    }

    /**
     * Magic isset check
     */
    public function __isset(string $name): bool
    {
        if (! $this->user instanceof Authenticatable) {
            return false;
        }

        $nameLower = strtolower($name);

        if (in_array($nameLower, array_map(strtolower(...), self::ALLOWED_PROPERTIES))) {
            return isset($this->user->{$name});
        }

        return false;
    }

    /**
     * Magic method caller
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (! $this->user instanceof Authenticatable) {
            return null;
        }

        $methodLower = strtolower($method);

        // Check if method is in allowed list
        if (in_array($methodLower, self::ALLOWED_METHODS) && method_exists($this->user, $method)) {
            return $this->user->{$method}(...$arguments);
        }

        // Block access to non-whitelisted methods
        return null;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        /** @var User|null $user */
        $user = $this->user;

        // @phpstan-ignore-next-line nullsafe.neverNull
        return (string) ($user?->name ?? '');
    }

    /**
     * Check if the user exists
     */
    public function exists(): bool
    {
        return $this->user instanceof Authenticatable;
    }
}
