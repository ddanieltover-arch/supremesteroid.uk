<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role instanceof UserRole && $user->role->isAdministrative();
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->role instanceof UserRole && $user->role->isAdministrative()) {
            return true;
        }

        return $order->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->role instanceof UserRole && $user->role->isAdministrative();
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->role instanceof UserRole && $user->role->isAdministrative();
    }

    public function submitPaymentProof(User $user, Order $order): bool
    {
        if ($order->user_id !== $user->id) {
            return false;
        }

        // Only allowed while awaiting payment or re-submitting under review
        return in_array($order->status->value, ['PENDING_PAYMENT', 'PAYMENT_REVIEW'], true);
    }

    public function viewAdminNotes(User $user, Order $order): bool
    {
        return $user->role instanceof UserRole && $user->role->isAdministrative();
    }

    public function viewInternalMovements(User $user, Order $order): bool
    {
        return $user->role instanceof UserRole && $user->role->isAdministrative();
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($user->role instanceof UserRole && $user->role->isAdministrative()) {
            return true;
        }

        return $order->user_id === $user->id && $order->status === OrderStatus::PENDING_PAYMENT;
    }
}
