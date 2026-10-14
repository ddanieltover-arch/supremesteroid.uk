<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportMappingStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}
