<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\UserRole;
use App\Exceptions\InvalidCheckoutException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuestCustomerService
{
    public function resolve(string $name, string $email): User
    {
        $email = Str::lower(trim($email));
        $name = trim($name);

        if ($email === '' || $name === '') {
            throw new InvalidCheckoutException(
                'Guest checkout requires a name and email.',
                'Please enter your name and email to continue checkout.'
            );
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing) {
            $role = $existing->role instanceof UserRole ? $existing->role : UserRole::tryFrom((string) $existing->role);

            if ($role?->isAdministrative()) {
                throw new InvalidCheckoutException(
                    'Administrative accounts cannot be used for storefront checkout.',
                    'Please use a different email address for checkout.'
                );
            }

            return $existing;
        }

        return User::forceCreate([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::password(32)),
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
        ]);
    }
}
