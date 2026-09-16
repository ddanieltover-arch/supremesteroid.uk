<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role instanceof UserRole && $user->role->isAdministrative();
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->role instanceof UserRole && $user->role->isAdministrative()) {
            return true;
        }

        return $payment->order?->user_id === $user->id;
    }

    public function submitProof(User $user, Payment $payment): bool
    {
        return $payment->order?->user_id === $user->id;
    }

    public function review(User $user, Payment $payment): bool
    {
        return $user->role instanceof UserRole && $user->role->canReviewPayments();
    }
}
