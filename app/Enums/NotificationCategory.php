<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationCategory: string
{
    case System = 'system';
    case Website = 'website';
    case User = 'user';
    case Cms = 'cms';
    case Broadcast = 'broadcast';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::System => 'System',
            self::Website => 'Website',
            self::User => 'User',
            self::Cms => 'CMS',
            self::Broadcast => 'Broadcast',
        };
    }

    /**
     * Get icon class.
     */
    public function icon(): string
    {
        return match ($this) {
            self::System => 'ri-settings-3-line',
            self::Website => 'ri-earth-line',
            self::User => 'ri-user-line',
            self::Cms => 'ri-file-text-line',
            self::Broadcast => 'ri-broadcast-line',
        };
    }

    /**
     * Get Bootstrap color class.
     */
    public function color(): string
    {
        return match ($this) {
            self::System => 'danger',
            self::Website => 'primary',
            self::User => 'info',
            self::Cms => 'success',
            self::Broadcast => 'warning',
        };
    }

    /**
     * Get badge class for UI.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::System => 'bg-danger-subtle text-danger',
            self::Website => 'bg-primary-subtle text-primary',
            self::User => 'bg-info-subtle text-info',
            self::Cms => 'bg-success-subtle text-success',
            self::Broadcast => 'bg-warning-subtle text-warning',
        };
    }

    /**
     * Get options for select dropdowns.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (NotificationCategory $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }

    /**
     * Get all values as array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
