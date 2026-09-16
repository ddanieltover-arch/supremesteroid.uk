<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Product $product): bool
    {
        if ($product->status->name === 'ACTIVE' && $product->compliance_verified_at !== null) {
            return true;
        }

        return $user !== null && $user->role instanceof UserRole && $user->role->isAdministrative();
    }

    public function create(User $user): bool
    {
        return $user->role instanceof UserRole && $user->role->canManageCatalogue();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->role instanceof UserRole && $user->role->canManageCatalogue();
    }

    public function verifyCompliance(User $user, Product $product): bool
    {
        return $user->role instanceof UserRole && in_array($user->role, [UserRole::MANAGER, UserRole::SUPER_ADMIN], true);
    }
}
