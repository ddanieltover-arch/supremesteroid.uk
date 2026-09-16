<?php

declare(strict_types=1);

namespace App\Enums;

enum ShippingZoneType: string
{
    case UK_DOMESTIC = 'UK_DOMESTIC';
    case EUROPE = 'EUROPE';
    case INTERNATIONAL = 'INTERNATIONAL';

    public function label(): string
    {
        return match ($this) {
            self::UK_DOMESTIC => 'United Kingdom (Domestic)',
            self::EUROPE => 'European Union & EEA',
            self::INTERNATIONAL => 'International Zone',
        };
    }
}
