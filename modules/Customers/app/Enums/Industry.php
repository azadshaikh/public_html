<?php

declare(strict_types=1);

namespace Modules\Customers\Enums;

enum Industry: string
{
    case Technology = 'technology';
    case Healthcare = 'healthcare';
    case Finance = 'finance';
    case Retail = 'retail';
    case Manufacturing = 'manufacturing';
    case Education = 'education';
    case ProfessionalServices = 'professional_services';
    case Media = 'media';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Technology => 'Technology',
            self::Healthcare => 'Healthcare',
            self::Finance => 'Finance',
            self::Retail => 'Retail',
            self::Manufacturing => 'Manufacturing',
            self::Education => 'Education',
            self::ProfessionalServices => 'Professional Services',
            self::Media => 'Media',
            self::Other => 'Other',
        };
    }
}
