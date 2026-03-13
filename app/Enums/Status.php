<?php

namespace App\Enums;

enum Status: string
{
    case ACTIVE = 'active';
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
    case PENDING = 'pending';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case EXPIRED = 'expired';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case UNDER_REVIEW = 'under_review';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case VERIFIED = 'verified';
    case UNVERIFIED = 'unverified';
    case LOCKED = 'locked';
    case MAINTENANCE = 'maintenance';
    case BANNED = 'banned';

    // Deployments
    case DEPLOYING = 'deploying';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case ROLLED_BACK = 'rolled_back';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function labels(): array
    {
        return [
            self::ACTIVE->value => 'Active',
            self::DRAFT->value => 'Draft',
            self::PUBLISHED->value => 'Published',
            self::ARCHIVED->value => 'Archived',
            self::PENDING->value => 'Pending',
            self::INACTIVE->value => 'Inactive',
            self::SUSPENDED->value => 'Suspended',
            self::EXPIRED->value => 'Expired',
            self::APPROVED->value => 'Approved',
            self::REJECTED->value => 'Rejected',
            self::UNDER_REVIEW->value => 'Under Review',
            self::COMPLETED->value => 'Completed',
            self::CANCELLED->value => 'Cancelled',
            self::VERIFIED->value => 'Verified',
            self::UNVERIFIED->value => 'Unverified',
            self::LOCKED->value => 'Locked',
            self::MAINTENANCE->value => 'Maintenance',
            self::BANNED->value => 'Banned',

            // Deployments
            self::DEPLOYING->value => 'Deploying',
            self::SUCCESS->value => 'Success',
            self::FAILED->value => 'Failed',
            self::ROLLED_BACK->value => 'Rolled Back',
        ];
    }

    /**
     * Get the human-readable label for this status instance
     */
    public function label(): string
    {
        return self::labels()[$this->value] ?? ucfirst(str_replace('_', ' ', $this->value));
    }

    /**
     * Get the Bootstrap badge color name for this status instance
     */
    public function badge(): string
    {
        return match ($this) {
            self::ACTIVE, self::PUBLISHED, self::APPROVED, self::VERIFIED, self::SUCCESS, self::COMPLETED => 'success',
            self::DRAFT, self::DEPLOYING => 'info',
            self::PENDING, self::UNDER_REVIEW => 'warning',
            self::INACTIVE, self::ARCHIVED, self::EXPIRED, self::ROLLED_BACK, self::CANCELLED => 'secondary',
            self::SUSPENDED => 'warning',
            self::REJECTED, self::FAILED, self::BANNED, self::LOCKED => 'danger',
            self::UNVERIFIED, self::MAINTENANCE => 'warning',
        };
    }

    /**
     * Get full Bootstrap CSS classes for badge display
     */
    public function badgeClass(): string
    {
        $color = $this->badge();

        return sprintf('bg-%s-subtle text-%s', $color, $color);
    }
}
