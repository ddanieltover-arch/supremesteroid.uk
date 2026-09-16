<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role instanceof UserRole && $user->role->isAdministrative();
    }

    public function view(User $user, User $model): bool
    {
        if ($user->role instanceof UserRole && $user->role->isAdministrative()) {
            return true;
        }

        return $user->id === $model->id;
    }

    public function update(User $user, User $model): bool
    {
        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        return $user->id === $model->id;
    }

    public function manageRoles(User $user): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }
}
