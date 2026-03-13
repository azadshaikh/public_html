<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Definitions\UserDefinition;
use App\Enums\Status;
use App\Models\Address;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use App\Traits\DateTimeFormattingTrait;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class UserResource extends ScaffoldResource
{
    use DateTimeFormattingTrait;

    // ================================================================
    // REQUIRED METHOD
    // ================================================================

    protected function definition(): ScaffoldDefinition
    {
        return new UserDefinition;
    }

    // ================================================================
    // CUSTOM FIELDS FOR DATAGRID
    // ================================================================

    protected function customFields(): array
    {
        $user = $this->user();
        $primaryAddressRelation = $user->primaryAddress()->first();
        $primaryAddress = $primaryAddressRelation instanceof Address ? $primaryAddressRelation : null;
        $status = $user->getAttribute('status');
        $statusValue = $status instanceof Status ? $status->value : (string) ($status ?? 'active');
        $emailVerifiedAt = $user->getAttribute('email_verified_at');
        $roles = $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->values()->toArray()
            : $user->roles()->pluck('name')->values()->toArray();

        $data = [
            // URL for row link to show page
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $user->getKey()),

            // Basic fields
            'first_name' => $user->getAttribute('first_name'),
            'last_name' => $user->getAttribute('last_name'),
            'full_name' => $user->getAttribute('full_name'),
            'name' => $user->getAttribute('full_name'),
            'email' => $user->getAttribute('email'),
            'username' => $user->getAttribute('username'),
            'phone' => $primaryAddress?->getAttribute('phone'),

            // Avatar
            'avatar' => $user->getAttribute('avatar'),
            'avatar_url' => $user->getAttribute('avatar_image'),

            // Email verification
            'email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $emailVerifiedAt instanceof CarbonInterface
                ? $emailVerifiedAt->toISOString()
                : ($emailVerifiedAt instanceof DateTimeInterface ? $emailVerifiedAt->format(DateTimeInterface::ATOM) : null),

            // Personal info
            'gender' => $user->getAttribute('gender'),
            'birth_date' => $user->getBirthDate(),
            'tagline' => $user->getAttribute('tagline'),
            'bio' => $user->getAttribute('bio'),

            // Status fields
            'status' => $statusValue,
            'status_label' => $this->getStatusLabel($statusValue),

            // Roles
            'roles' => $roles,

            // Social URLs
            'website_url' => $user->getWebsiteUrl(),
            'twitter_url' => $user->getTwitterUrl(),
            'facebook_url' => $user->getFacebookUrl(),
            'instagram_url' => $user->getInstagramUrl(),
            'linkedin_url' => $user->getLinkedinUrl(),

            // Datetime fields (will be formatted below)
            'created_at' => $user->getAttribute('created_at'),
            'updated_at' => $user->getAttribute('updated_at'),
            'last_access' => $user->getAttribute('last_access'),
        ];

        // Format datetime fields using app settings (timezone + format)
        return $this->formatDateTimeFields(
            $data,
            dateFields: [],
            timeFields: [],
            datetimeFields: ['created_at', 'updated_at', 'last_access']
        );
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    /**
     * Get status label for display
     */
    protected function getStatusLabel(string $status): string
    {
        $enum = Status::tryFrom($status);

        return $enum?->label() ?? ucfirst(str_replace('_', ' ', $status));
    }

    // ================================================================
    // OVERRIDE ACTIONS FOR USER-SPECIFIC ACTIONS
    // ================================================================

    protected function getActions(): array
    {
        $user = $this->user();
        $actions = [];
        $definition = $this->scaffold();
        $permissionPrefix = $definition->getPermissionPrefix();
        $routePrefix = $definition->getRoutePrefix();
        $status = $user->getAttribute('status');
        $statusValue = $status instanceof Status ? $status->value : (string) ($status ?? '');
        $userId = (int) $user->getKey();
        $isTrashed = $user->getAttribute('deleted_at') !== null;
        $canView = $this->can('view_'.$permissionPrefix);
        $canEdit = $this->can('edit_'.$permissionPrefix);
        $canImpersonate = $this->can('impersonate_'.$permissionPrefix);
        $canDelete = $this->can('delete_'.$permissionPrefix);
        $canRestore = $this->can('restore_'.$permissionPrefix);

        if (! $isTrashed) {
            // Show action
            if ($canView) {
                $actions['show'] = [
                    'url' => route($routePrefix.'.show', $userId),
                    'label' => 'View',
                    'icon' => 'ri-eye-line',
                    'method' => 'GET',
                ];
            }

            // Edit action
            if ($canEdit) {
                $actions['edit'] = [
                    'url' => route($routePrefix.'.edit', $userId),
                    'label' => 'Edit',
                    'icon' => 'ri-pencil-line',
                    'method' => 'GET',
                ];
            }

            // Impersonate action (not for self)
            if ($canImpersonate && Auth::id() !== $userId) {
                $actions['impersonate'] = [
                    'url' => route($routePrefix.'.impersonate', $userId),
                    'label' => 'Impersonate',
                    'icon' => 'ri-user-settings-line',
                    'method' => 'GET',
                    'fullReload' => true, // Force full page reload to update session
                ];
            }

            // Status-specific actions
            if ($canEdit) {
                // Suspend action (hide for already suspended/banned)
                if (! in_array($statusValue, ['suspended', 'banned'], true)) {
                    $actions['suspend'] = [
                        'url' => route($routePrefix.'.suspend', $userId),
                        'label' => 'Suspend',
                        'icon' => 'ri-pause-circle-line',
                        'method' => 'PATCH',
                        'confirm' => 'Suspend this user? They will be unable to log in.',
                    ];
                }

                // Ban action (hide for already banned)
                if ($statusValue !== 'banned') {
                    $actions['ban'] = [
                        'url' => route($routePrefix.'.ban', $userId),
                        'label' => 'Ban',
                        'icon' => 'ri-forbid-line',
                        'method' => 'PATCH',
                        'confirm' => 'Ban this user? They will be permanently blocked.',
                    ];
                }

                // Unban action (only for banned users)
                if ($statusValue === 'banned') {
                    $actions['unban'] = [
                        'url' => route($routePrefix.'.unban', $userId),
                        'label' => 'Unban',
                        'icon' => 'ri-checkbox-circle-line',
                        'method' => 'PATCH',
                        'confirm' => 'Unban this user? They will be able to log in again.',
                    ];
                }
            }

            // Delete action
            if ($canDelete) {
                $actions['delete'] = [
                    'url' => route($routePrefix.'.destroy', $userId),
                    'label' => 'Move to Trash',
                    'icon' => 'ri-delete-bin-line',
                    'method' => 'DELETE',
                    'confirm' => 'Move this user to trash? They can be restored later.',
                ];
            }
        } else {
            // Trashed items - restore and force delete
            if ($canRestore) {
                $actions['restore'] = [
                    'url' => route($routePrefix.'.restore', $userId),
                    'label' => 'Restore',
                    'icon' => 'ri-refresh-line',
                    'method' => 'PATCH',
                    'confirm' => 'Restore this user?',
                ];
            }

            if ($canDelete) {
                $actions['force_delete'] = [
                    'url' => route($routePrefix.'.force-delete', $userId),
                    'label' => 'Delete Permanently',
                    'icon' => 'ri-delete-bin-fill',
                    'method' => 'DELETE',
                    'confirm' => '⚠️ Permanently delete this user? This cannot be undone!',
                ];
            }
        }

        return $actions;
    }

    private function user(): User
    {
        throw_unless($this->resource instanceof User, RuntimeException::class, 'UserResource expects a User model instance.');

        return $this->resource;
    }
}
