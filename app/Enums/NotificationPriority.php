<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationPriority: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }

    /**
     * Get Bootstrap badge class.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::High => 'bg-danger-subtle text-danger',
            self::Medium => 'bg-warning-subtle text-warning',
            self::Low => 'bg-secondary-subtle text-secondary',
        };
    }

    /**
     * Get Bootstrap color.
     */
    public function color(): string
    {
        return match ($this) {
            self::High => 'danger',
            self::Medium => 'warning',
            self::Low => 'secondary',
        };
    }

    /**
     * Get icon class.
     */
    public function icon(): string
    {
        return match ($this) {
            self::High => 'ri-error-warning-line',
            self::Medium => 'ri-information-line',
            self::Low => 'ri-checkbox-circle-line',
        };
    }

    /**
     * Get options for select dropdowns.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (NotificationPriority $case): array => [
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
