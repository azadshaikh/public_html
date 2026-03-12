<?php

namespace App\Support\BulkActions;

use App\Support\Auth\PermissionMemoizer;

class BulkAction
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $icon,
        public readonly string $class,
        public readonly string $confirmMessage,
        public readonly string $action,
        public readonly string $url,
        public readonly ?string $permission = null,
        public readonly array $conditions = [],
        public readonly bool $requiresConfirmation = true,
        public readonly ?string $successMessage = null,
        public readonly ?string $errorMessage = null
    ) {}

    /**
     * Create a standard delete action.
     */
    public static function delete(string $url, ?string $permission = null): self
    {
        return new self(
            key: 'delete',
            label: 'Move to Trash',
            icon: 'ri-delete-bin-line',
            class: 'btn btn-outline-danger',
            confirmMessage: 'Are you sure you want to move the selected items to trash?',
            action: 'delete',
            url: $url,
            permission: $permission,
            conditions: ['status' => ['!=', 'trash']],
            successMessage: 'Items moved to trash successfully',
            errorMessage: 'Failed to move items to trash'
        );
    }

    /**
     * Create a standard restore action.
     */
    public static function restore(string $url, ?string $permission = null): self
    {
        return new self(
            key: 'restore',
            label: 'Restore Selected',
            icon: 'ri-refresh-line',
            class: 'btn btn-outline-success',
            confirmMessage: 'Are you sure you want to restore the selected items?',
            action: 'restore',
            url: $url,
            permission: $permission,
            conditions: ['status' => ['=', 'trash']],
            successMessage: 'Items restored successfully',
            errorMessage: 'Failed to restore items'
        );
    }

    /**
     * Create a standard force delete action.
     */
    public static function forceDelete(string $url, ?string $permission = null): self
    {
        return new self(
            key: 'force_delete',
            label: 'Delete Permanently',
            icon: 'ri-delete-bin-fill',
            class: 'btn btn-outline-danger',
            confirmMessage: '⚠️ WARNING: This will PERMANENTLY DELETE the selected items. All data will be lost forever and CANNOT be recovered. Are you absolutely sure you want to proceed?',
            action: 'force_delete',
            url: $url,
            permission: $permission,
            conditions: ['status' => ['=', 'trash']],
            requiresConfirmation: true,
            successMessage: 'Items permanently deleted',
            errorMessage: 'Failed to permanently delete items'
        );
    }

    /**
     * Create a custom action.
     */
    public static function custom(
        string $key,
        string $label,
        string $action,
        string $url,
        string $icon = 'ri-settings-3-line',
        string $class = 'btn btn-outline-primary',
        string $confirmMessage = 'Are you sure you want to perform this action?',
        ?string $permission = null,
        array $conditions = [],
        bool $requiresConfirmation = true
    ): self {
        return new self(
            key: $key,
            label: $label,
            icon: $icon,
            class: $class,
            confirmMessage: $confirmMessage,
            action: $action,
            url: $url,
            permission: $permission,
            conditions: $conditions,
            requiresConfirmation: $requiresConfirmation
        );
    }

    /**
     * Convert to array for frontend consumption.
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'icon' => $this->icon,
            'class' => $this->class,
            'confirm' => $this->confirmMessage,
            'action' => $this->action,
            'url' => $this->url,
            'permission' => $this->permission,
            'conditions' => $this->conditions,
            'requires_confirmation' => $this->requiresConfirmation,
            'success_message' => $this->successMessage,
            'error_message' => $this->errorMessage,
        ];
    }

    /**
     * Check if this action should be shown for the given status.
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

    /**
     * Check if user has permission for this action.
     */
    public function hasPermission(): bool
    {
        if (! $this->permission) {
            return true;
        }

        return PermissionMemoizer::can($this->permission);
    }
}
