<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportBatchStatus: string
{
    case DRAFT = 'DRAFT';
    case IMPORTING = 'IMPORTING';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case READY = 'READY';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::IMPORTING => 'Importing',
            self::UNDER_REVIEW => 'Under review',
            self::READY => 'Ready',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }
}
