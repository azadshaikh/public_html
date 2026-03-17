<?php

namespace Modules\Platform\Enums;

/**
 * Website status enumeration.
 *
 * Represents the possible states of a website throughout its lifecycle.
 */
enum WebsiteStatus: string
{
    case Provisioning = 'provisioning';
    case WaitingForDns = 'waiting_for_dns';
    case Active = 'active';
    case Failed = 'failed';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Trash = 'trash';
    case Deleted = 'deleted';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'Provisioning',
            self::WaitingForDns => 'Waiting for DNS',
            self::Active => 'Active',
            self::Failed => 'Failed',
            self::Suspended => 'Suspended',
            self::Expired => 'Expired',
            self::Trash => 'Trash',
            self::Deleted => 'Deleted',
        };
    }

    /**
     * Get the badge color for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Provisioning => 'info',
            self::WaitingForDns => 'warning',
            self::Active => 'success',
            self::Failed => 'danger',
            self::Suspended => 'danger',
            self::Expired => 'danger',
            self::Trash => 'danger',
            self::Deleted => 'danger',
        };
    }

    /**
     * Get the badge class for the status.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Provisioning => 'bg-info-subtle text-info',
            self::WaitingForDns => 'bg-warning-subtle text-warning',
            self::Active => 'bg-success-subtle text-success',
            self::Failed => 'bg-danger-subtle text-danger',
            self::Suspended => 'bg-warning-subtle text-warning',
            self::Expired => 'bg-danger-subtle text-danger',
            self::Trash => 'bg-danger-subtle text-danger',
            self::Deleted => 'bg-danger-subtle text-danger',
        };
    }

    /**
     * Get all statuses as an array for dropdown options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Provisioning->value => self::Provisioning->label(),
            self::WaitingForDns->value => self::WaitingForDns->label(),
            self::Active->value => self::Active->label(),
            self::Failed->value => self::Failed->label(),
            self::Suspended->value => self::Suspended->label(),
            self::Expired->value => self::Expired->label(),
            self::Trash->value => self::Trash->label(),
            self::Deleted->value => self::Deleted->label(),
        ];
    }

    /**
     * Check if the status is a final/terminal state.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Deleted, self::Trash]);
    }

    /**
     * Check if the status is active/operational.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if the status requires attention/action.
     */
    public function requiresAttention(): bool
    {
        return in_array($this, [self::Provisioning, self::WaitingForDns, self::Failed, self::Suspended, self::Expired]);
    }
}
