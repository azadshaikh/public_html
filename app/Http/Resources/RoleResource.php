<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Definitions\RoleDefinition;
use App\Enums\Status;
use App\Models\Role;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use RuntimeException;

class RoleResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new RoleDefinition;
    }

    protected function customFields(): array
    {
        $role = $this->role();

        return [
            // Link to show page
            'show_url' => route('app.roles.show', $role->getKey()),

            // System role flag (super user role)
            'is_system' => $role->id === (int) config('permission.super_user_role_id', 1),

            // Soft-delete flag
            'is_trashed' => $role->trashed(),

            // Badge fields for 'status' column
            'status_label' => $this->getStatusLabel($role),
            'status_class' => $this->getStatusClass($role),

            // Formatted Dates
            'created_at' => app_date_time_format($role->getAttribute('created_at'), 'datetime'),
            'updated_at' => app_date_time_format($role->getAttribute('updated_at'), 'datetime'),
        ];
    }

    private function getStatusLabel(Role $role): string
    {
        if ($role->trashed()) {
            return 'Trashed';
        }

        $status = $role->getAttribute('status');
        $statusValue = $status instanceof Status ? $status->value : (string) ($status ?? 'unknown');

        return Status::labels()[$statusValue] ?? ucfirst($statusValue);
    }

    private function getStatusClass(Role $role): string
    {
        if ($role->trashed()) {
            return 'bg-danger-subtle text-danger';
        }

        $status = $role->getAttribute('status');
        $statusValue = $status instanceof Status ? $status->value : (string) ($status ?? '');

        return match ($statusValue) {
            'active' => 'bg-success-subtle text-success',
            'inactive' => 'bg-warning-subtle text-warning',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    private function role(): Role
    {
        throw_unless($this->resource instanceof Role, RuntimeException::class, 'RoleResource expects a Role model instance.');

        return $this->resource;
    }
}
