<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case CUSTOMER = 'CUSTOMER';
    case STAFF = 'STAFF';
    case MANAGER = 'MANAGER';
    case SUPER_ADMIN = 'SUPER_ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER => 'Retail Customer',
            self::STAFF => 'Fulfillment & Support Staff',
            self::MANAGER => 'Operations Manager',
            self::SUPER_ADMIN => 'Super Administrator',
        };
    }

    public function isAdministrative(): bool
    {
        return in_array($this, [self::STAFF, self::MANAGER, self::SUPER_ADMIN], true);
    }

    public function canManageCatalogue(): bool
    {
        return in_array($this, [self::MANAGER, self::SUPER_ADMIN], true);
    }

    public function canReviewPayments(): bool
    {
        return in_array($this, [self::STAFF, self::MANAGER, self::SUPER_ADMIN], true);
    }

    public function canAccessFilament(): bool
    {
        return $this->isAdministrative();
    }
}
