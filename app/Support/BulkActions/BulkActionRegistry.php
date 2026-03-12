<?php

namespace App\Support\BulkActions;

class BulkActionRegistry
{
    /** @var array<string, array<BulkAction>> */
    private static array $registry = [];

    /**
     * Register bulk actions for a specific context.
     */
    public static function register(string $context, array $actions): void
    {
        self::$registry[$context] = [];

        foreach ($actions as $action) {
            if ($action instanceof BulkAction) {
                self::$registry[$context][$action->key] = $action;
            }
        }
    }

    /**
     * Add a single bulk action to a context.
     */
    public static function add(string $context, BulkAction $action): void
    {
        if (! isset(self::$registry[$context])) {
            self::$registry[$context] = [];
        }

        self::$registry[$context][$action->key] = $action;
    }

    /**
     * Get all bulk actions for a context.
     */
    public static function get(string $context): array
    {
        return self::$registry[$context] ?? [];
    }

    /**
     * Get filtered bulk actions for a context and status.
     */
    public static function getForStatus(string $context, string $status): array
    {
        $actions = static::get($context);
        $filteredActions = [];

        foreach ($actions as $action) {
            if ($action->shouldShow($status) && $action->hasPermission()) {
                $filteredActions[$action->key] = $action->toArray();
            }
        }

        return $filteredActions;
    }

    /**
     * Check if a context has any bulk actions.
     */
    public static function has(string $context): bool
    {
        return ! empty(self::$registry[$context]);
    }

    /**
     * Remove all bulk actions for a context.
     */
    public static function clear(string $context): void
    {
        unset(self::$registry[$context]);
    }

    /**
     * Remove a specific bulk action from a context.
     */
    public static function remove(string $context, string $actionKey): void
    {
        unset(self::$registry[$context][$actionKey]);
    }

    /**
     * Get all registered contexts.
     */
    public static function getContexts(): array
    {
        return array_keys(self::$registry);
    }

    /**
     * Get the total count of actions across all contexts.
     */
    public static function getTotalCount(): int
    {
        return array_sum(array_map(count(...), self::$registry));
    }

    /**
     * Get a specific action from a context.
     */
    public static function getAction(string $context, string $actionKey): ?BulkAction
    {
        return self::$registry[$context][$actionKey] ?? null;
    }

    /**
     * Check if a specific action exists in a context.
     */
    public static function hasAction(string $context, string $actionKey): bool
    {
        return isset(self::$registry[$context][$actionKey]);
    }

    /**
     * Replace an existing action or add a new one.
     */
    public static function set(string $context, BulkAction $action): void
    {
        static::add($context, $action);
    }

    /**
     * Merge actions from another context.
     */
    public static function merge(string $targetContext, string $sourceContext): void
    {
        if (! isset(self::$registry[$sourceContext])) {
            return;
        }

        if (! isset(self::$registry[$targetContext])) {
            self::$registry[$targetContext] = [];
        }

        self::$registry[$targetContext] = array_merge(
            self::$registry[$targetContext],
            self::$registry[$sourceContext]
        );
    }

    /**
     * Get actions as JSON for frontend consumption.
     */
    public static function toJson(string $context, string $status): string
    {
        return json_encode(static::getForStatus($context, $status));
    }
}
