<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductRelationType: string
{
    case RELATED = 'RELATED';
    case ALTERNATIVE = 'ALTERNATIVE';
    case COMPLEMENTARY = 'COMPLEMENTARY';
}
