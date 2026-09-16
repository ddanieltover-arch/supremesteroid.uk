<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductClassification: string
{
    case OTC_CONSUMER = 'OTC_CONSUMER';
    case RESEARCH_USE = 'RESEARCH_USE';
    case OTHER_LAWFUL_PRODUCT = 'OTHER_LAWFUL_PRODUCT';

    public function label(): string
    {
        return match ($this) {
            self::OTC_CONSUMER => 'Over-the-Counter Consumer Lawful Item',
            self::RESEARCH_USE => 'Certified Research / Reagent Use Only',
            self::OTHER_LAWFUL_PRODUCT => 'Other Lawful Health & Wellness Product',
        };
    }

    public function requiresLabVerificationReference(): bool
    {
        return $this === self::RESEARCH_USE;
    }

    /**
     * @return list<self>
     */
    public static function approvedForRetail(): array
    {
        return [
            self::OTC_CONSUMER,
            self::RESEARCH_USE,
            self::OTHER_LAWFUL_PRODUCT,
        ];
    }
}
