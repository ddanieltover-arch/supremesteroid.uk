<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportMatchStatus: string
{
    case NEW = 'NEW';
    case POSSIBLE_DUPLICATE = 'POSSIBLE_DUPLICATE';
    case EXACT_MATCH = 'EXACT_MATCH';
}
