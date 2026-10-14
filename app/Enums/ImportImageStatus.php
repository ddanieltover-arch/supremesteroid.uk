<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportImageStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}
