<?php

declare(strict_types=1);

namespace App\Scaffold;

use App\Support\Auth\PermissionMemoizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Action - Unified action builder for scaffold definitions
 *
 * Supports both row actions and bulk actions with a single definition.
 *
 * @example
 * // Row-only action
 * Action::make('edit')->label('Edit')->icon('ri-pencil-line')->forRow()
 *
 * // Bulk-only action
 * Action::make('export')->label('Export Selected')->icon('ri-download-line')->forBulk()
 *
 * // Action for both row and bulk (default for CRUD actions)
 * Action::make('delete')->label('Delete')->danger()->confirm('...')->forBoth()
 *
 * // With status conditions
 * Action::make('restore')->showOnStatus('trash')->forBoth()
 * Action::make('delete')->hideOnStatus('trash')->forBoth()
 */
class Action
{
    // Scope constants
    public const SCOPE_ROW = 'row';

    public const SCOPE_BULK = 'bulk';

    public const SCOPE_BOTH = 'both';

    // Core properties
    public string $key;

    public string $label;

    public ?string $icon = null;

    public ?string $route = null;

    public ?string $url = null;

    public ?string $method = null;

    public ?string $confirm = null;

    public ?string $confirmBulk = null;

    public string $variant = 'default';

    public ?string $permission = null;

    // Scope: 'row', 'bulk', or 'both'
    public string $scope = self::SCOPE_BOTH;

    // Status conditions
    public array $conditions = [];

    // Additional metadata
    public array $meta = [];

    // Custom HTML attributes (e.g., data-* attributes)
    public array $attributes = [];

    // =========================================================================
    // PERMISSIONS
    // =========================================================================

    /**
     * Set required permission to see/use this action
     */
    public ?string $ability = null;

    // =========================================================================
    // STATIC CONSTRUCTORS
    // =========================================================================

    /**
     * Create a new action
     */
    public static function make(string $key): self
    {
        $action = new self;
        $action->key = $key;
        $action->label = str($key)->headline()->toString();

        return $action;
    }

    // =========================================================================
    // SCOPE METHODS
    // =========================================================================

    /**
     * Action appears only in row actions dropdown
     */
    public function forRow(): self
    {
        $this->scope = self::SCOPE_ROW;

        return $this;
    }

    /**
     * Action appears only in bulk action bar
     */
    public function forBulk(): self
    {
        $this->scope = self::SCOPE_BULK;

        return $this;
    }

    /**
     * Action appears in both row and bulk actions
     */
    public function forBoth(): self
    {
        $this->scope = self::SCOPE_BOTH;

        return $this;
    }

    /**
     * Check if action is for row context
     */
    public function isForRow(): bool
    {
        return $this->scope === self::SCOPE_ROW || $this->scope === self::SCOPE_BOTH;
    }

    /**
     * Check if action is for bulk context
     */
    public function isForBulk(): bool
    {
        return $this->scope === self::SCOPE_BULK || $this->scope === self::SCOPE_BOTH;
    }

    // =========================================================================
    // LABEL & APPEARANCE
    // =========================================================================

    /**
     * Set action label
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set action icon (Remix icon class)
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set as danger variant (red styling)
     */
    public function danger(): self
    {
        $this->variant = 'danger';

        return $this;
    }

    /**
     * Set as success variant (green styling)
     */
    public function success(): self
    {
        $this->variant = 'success';

        return $this;
    }

    /**
     * Set as warning variant (yellow styling)
     */
    public function warning(): self
    {
        $this->variant = 'warning';

        return $this;
    }

    /**
     * Set as primary variant (blue styling)
     */
    public function primary(): self
    {
        $this->variant = 'primary';

        return $this;
    }

    // =========================================================================
    // URL & ROUTING
    // =========================================================================

    /**
     * Set route name for action URL generation
     */
    public function route(string $route): self
    {
        $this->route = $route;

        return $this;
    }

    /**
     * Set URL directly
     */
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Set HTTP method for AJAX actions
     */
    public function method(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    /**
     * Set as DELETE method
     */
    public function delete(): self
    {
        return $this->method('DELETE');
    }

    /**
     * Set as POST method
     */
    public function post(): self
    {
        return $this->method('POST');
    }

    // =========================================================================
    // CONFIRMATION
    // =========================================================================

    /**
     * Set confirmation message (shown before action executes)
     */
    public function confirm(string $message): self
    {
        $this->confirm = $message;

        return $this;
    }

    /**
     * Set bulk-specific confirmation message
     * Supports {count} placeholder for number of selected items
     */
    public function confirmBulk(string $message): self
    {
        $this->confirmBulk = $message;

        return $this;
    }

    public function permission(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * Set policy ability to check against model (e.g. 'update', 'delete')
     */
    public function ability(string $ability): self
    {
        $this->ability = $ability;

        return $this;
    }

    /**
     * Check if current user can perform action
     */
    public function authorized(): bool
    {
        if ($this->permission === null) {
            return true;
        }

        return PermissionMemoizer::can($this->permission);
    }

    // =========================================================================
    // STATUS CONDITIONS
    // =========================================================================

    /**
     * Show action only on specific status(es)
     *
     * Accumulates across chained calls:
     * ->showOnStatus('active')->showOnStatus('pending') === ->showOnStatus(['active', 'pending'])
     *
     * @param  string|array  $status  Single status or array of statuses
     */
    public function showOnStatus(string|array $status): self
    {
        $statuses = (array) $status;

        // Accumulate with any existing 'show' conditions
        if (isset($this->conditions['status'])) {
            [$op, $existing] = $this->conditions['status'];
            if ($op === '=' || $op === 'in') {
                $statuses = array_values(array_unique(array_merge((array) $existing, $statuses)));
            }
        }

        $this->conditions['status'] = count($statuses) > 1
            ? ['in', $statuses]
            : ['=', $statuses[0]];

        return $this;
    }

    /**
     * Hide action on specific status(es)
     *
     * Accumulates across chained calls:
     * ->hideOnStatus('trash')->hideOnStatus('banned') === ->hideOnStatus(['trash', 'banned'])
     *
     * @param  string|array  $status  Single status or array of statuses
     */
    public function hideOnStatus(string|array $status): self
    {
        $statuses = (array) $status;

        // Accumulate with any existing 'hide' conditions
        if (isset($this->conditions['status'])) {
            [$op, $existing] = $this->conditions['status'];
            if ($op === '!=' || $op === 'not_in') {
                $statuses = array_values(array_unique(array_merge((array) $existing, $statuses)));
            }
        }

        $this->conditions['status'] = count($statuses) > 1
            ? ['not_in', $statuses]
            : ['!=', $statuses[0]];

        return $this;
    }

    /**
     * Check if action should be shown for a given status
     */
    public function shouldShow(string $status): bool
    {
        if ($this->conditions === []) {
            return true;
        }

        foreach ($this->conditions as $field => $condition) {
            if ($field === 'status') {
                [$operator, $value] = $condition;

                return match ($operator) {
                    '=' => $status === $value,
                    '!=' => $status !== $value,
                    'in' => in_array($status, (array) $value),
                    'not_in' => ! in_array($status, (array) $value),
                    default => true,
                };
            }
        }

        return true;
    }

    // =========================================================================
    // METADATA & ATTRIBUTES
    // =========================================================================

    /**
     * Add custom metadata
     */
    public function meta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Add a custom HTML attribute
     *
     * Useful for adding data-* attributes to action buttons/links.
     * The JavaScript renderer will apply these to the action element.
     *
     * @example
     * Action::make('edit')
     *     ->attribute('data-modal', 'edit-media')
     *     ->attribute('data-id', 'row.id')  // 'row.id' will be interpolated
     *
     * @param  string  $name  Attribute name (e.g., 'data-modal', 'data-action')
     * @param  string|bool  $value  Attribute value (use true for boolean attributes like 'disabled')
     */
    public function attribute(string $name, string|bool $value = true): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Add multiple custom HTML attributes at once
     *
     * @param  array<string, string|bool>  $attributes
     */
    public function attributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    // =========================================================================
    // SERIALIZATION
    // =========================================================================

    /**
     * Convert to array for JSON API response
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'route' => $this->route,
            'url' => $this->url,
            'method' => $this->method,
            'confirm' => $this->confirm,
            'confirmBulk' => $this->confirmBulk,
            'variant' => $this->variant !== 'default' ? $this->variant : null,
            'scope' => $this->scope,
            'conditions' => $this->conditions === [] ? null : $this->conditions,
            'attributes' => $this->attributes === [] ? null : $this->attributes,
            ...$this->meta,
        ], fn ($v): bool => $v !== null);
    }

    // =========================================================================
    // ROW ACTION RESOLUTION (shared by ScaffoldResource + Scaffoldable)
    // =========================================================================

    /**
     * Resolve row actions for a model given its scaffold definition.
     *
     * Filters by: authorization, policy ability, scope (row/both), status conditions.
     * Builds URLs from route names. Skips actions with null URLs.
     *
     * @return array<string, array> Keyed by action key
     */
    public static function resolveForRow(ScaffoldDefinition $definition, Model $model): array
    {
        $isTrashed = method_exists($model, 'trashed') && $model->trashed();

        // Resolve actual model status for action filtering
        $statusField = $definition->getStatusField();
        if ($isTrashed) {
            $status = 'trash';
        } elseif ($statusField && isset($model->{$statusField})) {
            $rawStatus = $model->{$statusField};
            $status = is_object($rawStatus) ? ($rawStatus->value ?? (string) $rawStatus) : $rawStatus;
        } else {
            $status = 'all';
        }

        // Resolve route parameter name
        $routeValue = $model->getRouteKey();
        $routeKeyName = $model->getRouteKeyName();
        if ($routeKeyName === 'id') {
            $routeParam = str($definition->getEntityName())->camel()->toString();
        } else {
            $routeParam = $routeKeyName;
        }

        $definedActions = collect($definition->actions())
            ->filter(fn (self $action): bool => $action->authorized())
            ->filter(function (self $action) use ($model): bool {
                if (! empty($action->ability)) {
                    return Auth::check()
                        && Auth::user()->can($action->ability, $model);
                }

                return true;
            })
            ->filter(fn (self $action): bool => $action->isForRow())
            ->filter(fn (self $action): bool => $action->shouldShow($status));

        $actions = [];

        foreach ($definedActions as $action) {
            $actionData = $action->toArray();
            $key = $actionData['key'];

            // Build URL from route if provided
            $url = null;
            if (! empty($actionData['route'])) {
                try {
                    $url = route($actionData['route'], [$routeParam => $routeValue]);
                } catch (\Exception) {
                    // Fallback for routes with different parameter names
                    try {
                        $url = route($actionData['route'], [$routeValue]);
                    } catch (\Exception) {
                        continue;
                    }
                }
            }

            // Skip actions with no URL — they would render as dead buttons (B3 fix)
            if ($url === null && ! empty($actionData['route'])) {
                continue;
            }

            $actions[$key] = [
                'url' => $url,
                'label' => $actionData['label'],
                'icon' => $actionData['icon'] ?? null,
                'method' => $actionData['method'] ?? 'GET',
                'confirm' => $actionData['confirm'] ?? null,
                'variant' => $actionData['variant'] ?? 'default',
                'attributes' => $actionData['attributes'] ?? null,
            ];

            if (($actionData['variant'] ?? null) === 'danger') {
                $actions[$key]['danger'] = true;
            }
        }

        return $actions;
    }
}
