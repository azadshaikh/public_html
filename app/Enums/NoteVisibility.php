<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * NoteVisibility - Controls who can see a note
 *
 * @example
 * $note->visibility = NoteVisibility::Team;
 * $note->visibility->label(); // "Team"
 */
enum NoteVisibility: string
{
    case Private = 'private';     // Only the author can see
    case Team = 'team';           // All staff with model access
    case Customer = 'customer';   // Visible in customer portal

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Team => 'Team',
            self::Customer => 'Customer Visible',
        };
    }

    /**
     * Get Remix icon class.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Private => 'ri-lock-line',
            self::Team => 'ri-team-line',
            self::Customer => 'ri-user-line',
        };
    }

    /**
     * Get description for help text.
     */
    public function description(): string
    {
        return match ($this) {
            self::Private => 'Only you can see this note',
            self::Team => 'All team members can see this note',
            self::Customer => 'Customer can see this note in their portal',
        };
    }

    /**
     * Get Bootstrap color class.
     */
    public function color(): string
    {
        return match ($this) {
            self::Private => 'secondary',
            self::Team => 'info',
            self::Customer => 'success',
        };
    }

    /**
     * Get all visibilities as array for select options.
     */
    public static function options(): array
    {
        return array_map(
            fn (self $visibility): array => [
                'value' => $visibility->value,
                'label' => $visibility->label(),
                'description' => $visibility->description(),
            ],
            self::cases()
        );
    }
}
