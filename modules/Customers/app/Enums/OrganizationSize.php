<?php

namespace Modules\Customers\Enums;

enum OrganizationSize: string
{
    case SOLO = '1';
    case SMALL = '2-10';
    case MEDIUM = '11-50';
    case MID_LARGE = '51-200';
    case LARGE = '201-500';
    case ENTERPRISE = '501-1000';
    case HUGE = '1000+';

    public function label(): string
    {
        return match ($this) {
            self::SOLO => '1 Employee',
            self::SMALL => '2-10 Employees',
            self::MEDIUM => '11-50 Employees',
            self::MID_LARGE => '51-200 Employees',
            self::LARGE => '201-500 Employees',
            self::ENTERPRISE => '501-1,000 Employees',
            self::HUGE => '1,000+ Employees',
        };
    }
}
