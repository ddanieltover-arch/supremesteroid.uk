<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public function isVisible(): bool
    {
        return $this === self::APPROVED;
    }
}
