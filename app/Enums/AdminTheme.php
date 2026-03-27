<?php

namespace App\Enums;

enum AdminTheme: string
{
    case Default = 'default';
    case Swiss = 'swiss';
    case Green = 'green';
    case Zen = 'zen';
    case Vista = 'vista';
    case Claude = 'claude';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Swiss => 'Swiss',
            self::Green => 'Green',
            self::Zen => 'Zen',
            self::Vista => 'Vista',
            self::Claude => 'Claude',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Default => 'Keep the current neutral admin palette with no extra overrides.',
            self::Swiss => 'High-contrast Swiss minimalism with square corners and assertive offset shadows.',
            self::Green => 'Fresh leaf-green accents with a clean neutral shell and balanced radius.',
            self::Zen => 'Calm sand-and-stone neutrals with compact radius and understated elevation.',
            self::Vista => 'Soft frosted blues with warm signal accents and restrained shadows.',
            self::Claude => 'Warm editorial neutrals with softer depth and roomier radius.',
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
