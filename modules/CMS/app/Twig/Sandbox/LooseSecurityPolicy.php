<?php

namespace Modules\CMS\Twig\Sandbox;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Twig\Markup;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityPolicyInterface;

class LooseSecurityPolicy implements SecurityPolicyInterface
{
    /**
     * Methods that are NEVER allowed on any object (mutating/dangerous operations)
     * NOTE: All entries MUST be lowercase since method names are lowercased before comparison
     */
    private const array BLOCKED_METHODS = [
        // Eloquent mutating methods
        'save', 'update', 'delete', 'forcedelete', 'destroy', 'restore',
        'create', 'insert', 'updateorcreate', 'firstorcreate', 'upsert',
        'truncate', 'forcecreate', 'fill', 'forcefill',
        // Relationship mutations
        'attach', 'detach', 'sync', 'syncwithoutdetaching', 'toggle',
        'associate', 'dissociate', 'push',
        // Query builder mutations
        'increment', 'decrement',
        // Potentially dangerous
        'replicate', 'touch',
        // Auth/Password related - block methods that expose sensitive data
        'setpasswordattribute', 'setremembertokenattribute',
        'getauthpassword', 'getremembertoken', 'getremembertokenname',
        'getattribute', 'getattributes', 'getoriginal', 'getraworiginal',
        'toarray', 'tojson', 'jsonserialize', 'attributestoarray',
        'relationstoarray', 'makevisible', 'makehidden',
    ];

    /**
     * Properties that are NEVER allowed on User/Auth models
     */
    private const array BLOCKED_USER_PROPERTIES = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'api_token', 'email_verified_at',
    ];

    private array $allowedMethods;

    public function __construct(private array $allowedTags = [], private array $allowedFilters = [], array $allowedMethods = [], private array $allowedProperties = [], private array $allowedFunctions = [])
    {
        $this->setAllowedMethods($allowedMethods);
    }

    public function setAllowedTags(array $tags): void
    {
        $this->allowedTags = $tags;
    }

    public function setAllowedFilters(array $filters): void
    {
        $this->allowedFilters = $filters;
    }

    public function setAllowedMethods(array $methods): void
    {
        $this->allowedMethods = [];
        foreach ($methods as $class => $m) {
            $this->allowedMethods[$class] = array_map(strtolower(...), (array) $m);
        }
    }

    public function setAllowedProperties(array $properties): void
    {
        $this->allowedProperties = $properties;
    }

    public function setAllowedFunctions(array $functions): void
    {
        $this->allowedFunctions = $functions;
    }

    public function checkSecurity($tags, $filters, $functions): void
    {
        foreach ($tags as $tag) {
            if (! in_array($tag, $this->allowedTags)) {
                throw new SecurityNotAllowedTagError(sprintf('Tag "%s" is not allowed.', $tag), $tag);
            }
        }

        foreach ($filters as $filter) {
            if (! in_array($filter, $this->allowedFilters)) {
                throw new SecurityNotAllowedFilterError(sprintf('Filter "%s" is not allowed.', $filter), $filter);
            }
        }

        foreach ($functions as $function) {
            if (! in_array($function, $this->allowedFunctions)) {
                throw new SecurityNotAllowedFunctionError(sprintf('Function "%s" is not allowed.', $function), $function);
            }
        }
    }

    public function checkMethodAllowed($obj, $method): void
    {
        if ($obj instanceof Markup) {
            return;
        }

        $methodLower = strtolower($method);

        // ALWAYS block dangerous/mutating methods regardless of namespace
        if (in_array($methodLower, self::BLOCKED_METHODS)) {
            throw new SecurityNotAllowedMethodError(
                sprintf('Calling "%s" method is not allowed for security reasons.', $method),
                $obj::class,
                $method
            );
        }

        // Allow all methods on allowed namespaces (after blocklist check)
        if ($this->isAllowedNamespace($obj)) {
            return;
        }

        $allowed = false;
        foreach ($this->allowedMethods as $class => $methods) {
            if (is_string($class) && $obj instanceof $class) {
                $allowed = in_array($methodLower, $methods);
                if ($allowed) {
                    break;
                }
            }
        }

        if (! $allowed) {
            throw new SecurityNotAllowedMethodError(sprintf('Calling "%s" method on a "%s" object is not allowed.', $method, $obj::class), $obj::class, $method);
        }
    }

    public function checkPropertyAllowed($obj, $property): void
    {
        // Block sensitive properties on User models
        if ($this->isUserModel($obj) && in_array(strtolower($property), self::BLOCKED_USER_PROPERTIES)) {
            throw new SecurityNotAllowedPropertyError(
                sprintf('Accessing "%s" property on user objects is not allowed for security reasons.', $property),
                $obj::class,
                $property
            );
        }

        // Allow all properties on allowed namespaces (after blocklist check)
        if ($this->isAllowedNamespace($obj)) {
            return;
        }

        $allowed = false;
        foreach ($this->allowedProperties as $class => $properties) {
            if (is_string($class) && $obj instanceof $class) {
                $allowed = in_array($property, (is_array($properties) ? $properties : [$properties]));
                if ($allowed) {
                    break;
                }
            }
        }

        if (! $allowed) {
            throw new SecurityNotAllowedPropertyError(sprintf('Calling "%s" property on a "%s" object is not allowed.', $property, $obj::class), $obj::class, $property);
        }
    }

    /**
     * Check if the object is a User model (Authenticatable)
     */
    private function isUserModel($obj): bool
    {
        return $obj instanceof Authenticatable;
    }

    /**
     * Check if the object belongs to a namespace customized for content
     * Note: Services namespace is removed as it could expose internal business logic
     */
    private function isAllowedNamespace($obj): bool
    {
        $class = $obj::class;

        // Allow CMS Models, App Models, and standard PHP classes used in templates
        // NOTE: Modules\CMS\Services\ is intentionally NOT included for security
        $allowedNamespaces = [
            'Modules\\CMS\\Models\\',
            'Modules\\CMS\\Twig\\Security\\',  // SafeUserProxy and other security wrappers
            'App\\Models\\',
            Collection::class,
            \Illuminate\Database\Eloquent\Collection::class,
            'Illuminate\\Database\\Eloquent\\Relations\\',
            LengthAwarePaginator::class,
            Paginator::class,
            Carbon::class,
            \Illuminate\Support\Carbon::class,
        ];

        foreach ($allowedNamespaces as $ns) {
            if (str_starts_with($class, $ns)) {
                return true;
            }
        }

        return false;
    }
}
