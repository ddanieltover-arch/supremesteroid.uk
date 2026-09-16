<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case DRAFT = 'DRAFT';
    case COMPLIANCE_REVIEW = 'COMPLIANCE_REVIEW';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case IMPORTED = 'IMPORTED';
    case ACTIVE = 'ACTIVE';
    case OUT_OF_STOCK = 'OUT_OF_STOCK';
    case SUSPENDED = 'SUSPENDED';
    case ARCHIVED = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft (Internal)',
            self::COMPLIANCE_REVIEW => 'Pending Compliance Review',
            self::UNDER_REVIEW => 'Under Regulatory Review',
            self::IMPORTED => 'Imported (Pending Verification)',
            self::ACTIVE => 'Active & Lawfully Approved',
            self::OUT_OF_STOCK => 'Out of Stock',
            self::SUSPENDED => 'Suspended from Sale',
            self::ARCHIVED => 'Archived / Decommissioned',
        };
    }

    public function isPurchasable(): bool
    {
        return $this === self::ACTIVE;
    }
}
