<?php

namespace Modules\Customers\Enums;

enum CustomerSource: string
{
    case REFERRAL = 'referral';
    case MARKETING = 'marketing';
    case DIRECT = 'direct';
    case PARTNER = 'partner';
    case WEBSITE = 'website';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::REFERRAL => 'Referral',
            self::MARKETING => 'Marketing',
            self::DIRECT => 'Direct',
            self::PARTNER => 'Partner',
            self::WEBSITE => 'Website',
            self::OTHER => 'Other',
        };
    }
}
