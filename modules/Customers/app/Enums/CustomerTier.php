<?php

namespace Modules\Customers\Enums;

enum CustomerTier: string
{
    case BRONZE = 'bronze';
    case SILVER = 'silver';
    case GOLD = 'gold';
    case PLATINUM = 'platinum';

    public function label(): string
    {
        return match ($this) {
            self::BRONZE => 'Bronze',
            self::SILVER => 'Silver',
            self::GOLD => 'Gold',
            self::PLATINUM => 'Platinum',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BRONZE => 'secondary',
            self::SILVER => 'info',
            self::GOLD => 'warning',
            self::PLATINUM => 'primary',
        };
    }
}
