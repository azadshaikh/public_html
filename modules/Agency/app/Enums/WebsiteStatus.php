<?php

declare(strict_types=1);

namespace Modules\Agency\Enums;

/**
 * Local mirror of website statuses.
 *
 * This enum lives in the Agency module so it doesn't depend on
 * Modules\Platform\Enums\WebsiteStatus. Values match the Platform
 * enum exactly so webhook payloads map without conversion.
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
     * Human-readable label.
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
            self::Trash => 'Trashed',
            self::Deleted => 'Deleted',
        };
    }

    /**
     * Bootstrap badge class.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Provisioning => 'bg-info-subtle text-info',
            self::WaitingForDns => 'bg-warning-subtle text-warning',
            self::Active => 'bg-success-subtle text-success',
            self::Failed => 'bg-danger-subtle text-danger',
            self::Suspended => 'bg-warning-subtle text-warning',
            self::Expired => 'bg-secondary-subtle text-secondary',
            self::Trash => 'bg-dark-subtle text-dark',
            self::Deleted => 'bg-dark-subtle text-dark',
        };
    }
}
