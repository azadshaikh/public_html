<?php

namespace Modules\Customers\Enums;

enum AnnualRevenue: string
{
    case UNDER_50K = '0-50k';
    case RANGE_50K_100K = '50k-100k';
    case RANGE_100K_500K = '100k-500k';
    case RANGE_500K_1M = '500k-1m';
    case RANGE_1M_5M = '1m-5m';
    case RANGE_5M_10M = '5m-10m';
    case OVER_10M = '10m+';

    public function label(): string
    {
        return match ($this) {
            self::UNDER_50K => 'Less than $50,000',
            self::RANGE_50K_100K => '$50,000 - $100,000',
            self::RANGE_100K_500K => '$100,000 - $500,000',
            self::RANGE_500K_1M => '$500,000 - $1,000,000',
            self::RANGE_1M_5M => '$1,000,000 - $5,000,000',
            self::RANGE_5M_10M => '$5,000,000 - $10,000,000',
            self::OVER_10M => '$10,000,000+',
        };
    }
}
