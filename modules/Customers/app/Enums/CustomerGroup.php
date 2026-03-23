<?php

declare(strict_types=1);

namespace Modules\Customers\Enums;

enum CustomerGroup: string
{
    case Standard = 'standard';
    case Premium = 'premium';
    case Enterprise = 'enterprise';
    case Smb = 'smb';
    case Startup = 'startup';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Premium => 'Premium',
            self::Enterprise => 'Enterprise',
            self::Smb => 'SMB',
            self::Startup => 'Startup',
            self::Other => 'Other',
        };
    }
}
