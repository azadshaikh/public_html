<?php

namespace App\Enums;

enum AdminTheme: string
{
    case Default = 'default';
    case Sepia = 'sepia';
    case Ocean = 'ocean';
    case Forest = 'forest';
    case Ember = 'ember';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Sepia => 'Sepia',
            self::Ocean => 'Ocean',
            self::Forest => 'Forest',
            self::Ember => 'Ember',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Default => 'Keep the current neutral admin palette with no extra overrides.',
            self::Sepia => 'Warm paper tones with softened ink contrast for a calmer workspace.',
            self::Ocean => 'Salt-blue surfaces with crisp teal accents and cooler navigation depth.',
            self::Forest => 'Mossy neutrals with evergreen accents that stay restrained and readable.',
            self::Ember => 'Smoky charcoal paired with terracotta highlights for stronger focus areas.',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $theme): string => $theme->value,
            self::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $theme): array => [
                'value' => $theme->value,
                'label' => $theme->label(),
                'description' => $theme->description(),
            ],
            self::cases(),
        );
    }

    public static function sanitize(mixed $value): string
    {
        $candidate = is_string($value) ? $value : self::Default->value;

        foreach (self::cases() as $theme) {
            if ($theme->value === $candidate) {
                return $theme->value;
            }
        }

        return self::Default->value;
    }
}
