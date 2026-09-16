<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Inventory;
use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role instanceof UserRole && $user->role->isAdministrative();
    }

    public function view(User $user, Inventory $inventory): bool
    {
        return $user->role instanceof UserRole && $user->role->isAdministrative();
    }

    public function update(User $user, Inventory $inventory): bool
    {
        return $user->role instanceof UserRole && $user->role->canManageCatalogue();
    }

    public function adjust(User $user, Inventory $inventory): bool
    {
        return $this->update($user, $inventory);
    }
}
