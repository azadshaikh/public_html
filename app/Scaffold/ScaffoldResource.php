<?php

declare(strict_types=1);

namespace App\Scaffold;

use App\Support\Auth\PermissionMemoizer;
use App\Traits\DateTimeFormattingTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ScaffoldResource - Convention-based JSON resource for Scaffold system
 *
 * Provides automatic transformation for DataGrid format:
 * - Base attributes from model
 * - Formatted dates
 * - Status labels/badges for enum fields
 * - Audit fields (created_by, updated_by)
 * - Soft delete fields
 * - Row actions based on permissions
 *
 * @example
 * class AddressResource extends ScaffoldResource
 * {
 *     protected function definition(): ScaffoldDefinition
 *     {
 *         return new AddressDefinition();
 *     }
 *
 *     // Optional: add custom fields
 *     protected function customFields(): array
 *     {
 *         return [
 *             'full_address' => $this->getFullAddress(),
 *             'owner' => $this->whenLoaded('owner', fn() => $this->owner->name),
 *         ];
 *     }
 * }
 */
abstract class ScaffoldResource extends JsonResource
{
    use DateTimeFormattingTrait;

    protected ?Request $resourceRequest = null;

    /**
     * Cached scaffold definition to prevent N+1 calls
     */
    protected ?ScaffoldDefinition $definitionCache = null;

    /**
     * Transform the resource into an array (DataGrid format)
     *
     * Order matters: later arrays override earlier ones
     * 1. Base attributes (all model fields)
     * 2. Formatted dates
     * 3. Status fields
     * 4. Audit fields
     * 5. Soft delete fields
     * 6. Custom fields (override any above)
     * 7. Actions
     */
    public function toArray(Request $request): array
    {
        $this->resourceRequest = $request;

        try {
            $payload = [
                ...$this->getBaseAttributes(),
            ];

            if ($this->includesFormattedDates()) {
                $payload = [
                    ...$payload,
                    ...$this->getFormattedDates(),
                ];
            }

            if ($this->includesEnumFields()) {
                $payload = [
                    ...$payload,
                    ...$this->getEnumFields(),
                ];
            }

            if ($this->includesStatusFields()) {
                $payload = [
                    ...$payload,
                    ...$this->getStatusFields(),
                ];
            }

            if ($this->includesAuditFields()) {
                $payload = [
                    ...$payload,
                    ...$this->getAuditFields(),
                ];
            }

            if ($this->includesSoftDeleteFields()) {
                $payload = [
                    ...$payload,
                    ...$this->getSoftDeleteFields(),
                ];
            }

            $payload = [
                ...$payload,
                ...$this->customFields(),
            ];

            if ($this->includesActions()) {
                $payload['actions'] = $this->getActions();
            }

            return $payload;
        } finally {
            $this->resourceRequest = null;
        }
    }

    /**
     * Get the scaffold definition
     */
    abstract protected function definition(): ScaffoldDefinition;

    /**
     * Get cached scaffold definition
     * Prevents N+1 when getActions() or getStatusFields() are called per row
     */
    protected function scaffold(): ScaffoldDefinition
    {
        return $this->definitionCache ??= $this->definition();
    }

    // =========================================================================
    // ATTRIBUTE GROUPS
    // =========================================================================

    /**
     * Get base attributes from model
     *
     * Uses getAttributes() to include only model columns, excluding:
     * - Loaded relationships (e.g., createdBy, updatedBy objects)
     * - Appended attributes
     * - Hidden attributes (passwords, tokens, secrets)
     * This keeps payloads lean while still providing all database fields.
     * Subclasses can add relations/computed fields via customFields().
     */
    protected function getBaseAttributes(): array
    {
        $attributes = $this->resource->getAttributes();
        $hidden = $this->resource->getHidden();

        // Filter out hidden attributes to prevent exposure of sensitive data
        if (! empty($hidden)) {
            $attributes = array_diff_key($attributes, array_flip($hidden));
        }

        $allowedKeys = $this->baseAttributeKeys();

        if (is_array($allowedKeys)) {
            $attributes = array_intersect_key($attributes, array_flip($allowedKeys));
        }

        return [
            'id' => $this->resource->getKey(),
            ...$attributes,
        ];
    }

    /**
     * Get formatted date fields
     *
     * Uses app_date_time_format() for localized date formatting.
     * Provides both ISO (for JS parsing) and formatted (for display) versions.
     */
    protected function getFormattedDates(): array
    {
        $dates = [];
        $dateKeys = $this->formattedDateKeys();

        if (in_array('created_at', $dateKeys, true) && $this->resource->created_at) {
            $dates['created_at'] = $this->resource->created_at->toISOString();
            $dates['created_at_formatted'] = $this->formatDateTime($this->resource->created_at, 'datetime');
            $dates['created_at_human'] = $this->resource->created_at->diffForHumans();
        }

        if (in_array('updated_at', $dateKeys, true) && $this->resource->updated_at) {
            $dates['updated_at'] = $this->resource->updated_at->toISOString();
            $dates['updated_at_formatted'] = $this->formatDateTime($this->resource->updated_at, 'datetime');
            $dates['updated_at_human'] = $this->resource->updated_at->diffForHumans();
        }

        return $dates;
    }

    /**
     * Get fields for all enum-cast attributes automatically
     *
     * Detects enum casts on the model and extracts:
     * - {field}_label: from enum's label() method
     * - {field}_class: from enum's badge() or class() method
     *
     * This eliminates the need for manual getStatusLabel(), getCategoryLabel() etc.
     *
     * @example Model cast: 'priority' => PriorityEnum::class
     *          Enum has label() and badge() methods
     *          Output: priority_label, priority_class
     */
    protected function getEnumFields(): array
    {
        $fields = [];
        $casts = $this->resource->getCasts();
        $statusField = $this->scaffold()->getStatusField();

        foreach ($casts as $attribute => $castType) {
            // Skip status field — handled by getStatusFields() to avoid overlap (I6 fix)
            if ($attribute === $statusField) {
                continue;
            }

            // Skip non-enum casts
            if (! is_string($castType)) {
                continue;
            }

            if (! enum_exists($castType)) {
                continue;
            }

            $value = $this->resource->{$attribute};

            if ($value === null) {
                continue;
            }

            // Extract label if method exists
            if (method_exists($value, 'label')) {
                $fields[$attribute.'_label'] = $value->label();
            } else {
                // Fallback: humanize the enum value
                $fields[$attribute.'_label'] = ucfirst(str_replace('_', ' ', $value->value ?? (string) $value));
            }

            // Extract badge/class if method exists
            // Priority: badge() > badgeClass() > class() > color()
            if (method_exists($value, 'badge')) {
                $fields[$attribute.'_class'] = $value->badge();
            } elseif (method_exists($value, 'badgeClass')) {
                $fields[$attribute.'_class'] = $value->badgeClass();
            } elseif (method_exists($value, 'class')) {
                $fields[$attribute.'_class'] = $value->class();
            } elseif (method_exists($value, 'color')) {
                // Convert color to badge class
                $color = $value->color();
                $fields[$attribute.'_class'] = sprintf('bg-%s-subtle text-%s', $color, $color);
            }
        }

        return $fields;
    }

    /**
     * Get status fields with label and badge
     */
    protected function getStatusFields(): array
    {
        $statusField = $this->scaffold()->getStatusField();

        if (! $statusField || ! isset($this->resource->{$statusField})) {
            return [];
        }

        $status = $this->resource->{$statusField};
        $fields = [];

        // Handle enum status
        if (is_object($status)) {
            $fields['status_value'] = $status->value ?? (string) $status;

            if (method_exists($status, 'label')) {
                $fields['status_label'] = $status->label();
            }

            if (method_exists($status, 'badge')) {
                $fields['status_badge'] = $status->badge();
            }

            if (method_exists($status, 'color')) {
                $fields['status_color'] = $status->color();
            }
        } else {
            $fields['status_value'] = $status;
            $fields['status_label'] = ucfirst(str_replace('_', ' ', $status));
        }

        return $fields;
    }

    /**
     * Get audit fields (creator, updater)
     */
    protected function getAuditFields(): array
    {
        $fields = [];

        // Created by
        if ($this->resource->relationLoaded('createdBy') && $this->resource->createdBy) {
            $fields['created_by_name'] = $this->formatUserName($this->resource->createdBy);
        }

        // Updated by
        if ($this->resource->relationLoaded('updatedBy') && $this->resource->updatedBy) {
            $fields['updated_by_name'] = $this->formatUserName($this->resource->updatedBy);
        }

        // Deleted by
        if ($this->resource->relationLoaded('deletedBy') && $this->resource->deletedBy) {
            $fields['deleted_by_name'] = $this->formatUserName($this->resource->deletedBy);
        }

        return $fields;
    }

    /**
     * Get soft delete fields
     * Only produces is_trashed/deleted_at for models that use SoftDeletes (I8 fix)
     */
    protected function getSoftDeleteFields(): array
    {
        // Guard: don't emit soft-delete metadata for models without SoftDeletes
        if (! in_array(SoftDeletes::class, class_uses_recursive($this->resource), true)) {
            return [];
        }

        if (! $this->resource->trashed()) {
            return ['is_trashed' => false];
        }

        if (! $this->includesDetailedSoftDeleteFields()) {
            return ['is_trashed' => true];
        }

        return [
            'is_trashed' => true,
            'deleted_at' => $this->resource->deleted_at->toISOString(),
            'deleted_at_formatted' => $this->formatDateTime($this->resource->deleted_at, 'date'),
            'deleted_at_human' => $this->resource->deleted_at->diffForHumans(),
        ];
    }

    /**
     * Get custom fields - override in subclass
     */
    protected function customFields(): array
    {
        return [];
    }

    protected function isIndexPayloadRequest(): bool
    {
        $route = $this->resourceRequest?->route();

        return $route !== null
            && method_exists($route, 'named')
            && $route->named($this->scaffold()->getIndexRoute());
    }

    /**
     * Explicit allowlist for raw model attributes in index/list rows.
     *
     * By default, derive the allowlist from visible scaffold column keys so list
     * payloads do not fall back to exposing every model attribute.
     *
     * @return array<int, string>|null
     */
    protected function baseAttributeKeys(): ?array
    {
        return collect($this->scaffold()->columns())
            ->pluck('key')
            ->filter(fn (string $key): bool => $key !== '' && ! str_starts_with($key, '_'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function formattedDateKeys(): array
    {
        return ['created_at', 'updated_at'];
    }

    protected function includesFormattedDates(): bool
    {
        return true;
    }

    protected function includesEnumFields(): bool
    {
        return true;
    }

    protected function includesStatusFields(): bool
    {
        return true;
    }

    protected function includesAuditFields(): bool
    {
        return true;
    }

    protected function includesSoftDeleteFields(): bool
    {
        return true;
    }

    protected function includesDetailedSoftDeleteFields(): bool
    {
        return true;
    }

    protected function includesActions(): bool
    {
        return $this->scaffold()->shouldIncludeRowActionsInInertiaRows();
    }

    // =========================================================================
    // ROW ACTIONS (DataGrid format)
    // =========================================================================

    /**
     * Get available row actions based on permissions and state
     *
     * Delegates to Action::resolveForRow() — the single source of truth
     * for action filtering, authorization, status conditions, and URL building.
     */
    protected function getActions(): array
    {
        return Action::resolveForRow($this->scaffold(), $this->resource);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Check if current user has permission
     */
    protected function can(string $permission): bool
    {
        return PermissionMemoizer::can($permission);
    }

    /**
     * Format user name from user model
     */
    protected function formatUserName($user): string
    {
        if (! $user) {
            return '';
        }

        // Try common name patterns
        if (isset($user->first_name, $user->last_name)) {
            return trim(sprintf('%s %s', $user->first_name, $user->last_name));
        }

        return $user->name ?? $user->email ?? 'User #'.$user->id;
    }
}
