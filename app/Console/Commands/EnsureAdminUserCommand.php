<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
class EnsureAdminUserCommand extends Command
{
    protected $signature = 'app:ensure-admin
                            {--reset-password : Reset the admin password even if the account already exists}';

    protected $description = 'Create the default Filament admin account when it is missing';

    public function handle(): int
    {
        $email = (string) env('ADMIN_DEFAULT_EMAIL', 'info@supremesteroid.uk');
        // Plain text: the User model hashed cast hashes once on save.
        $password = (string) env('ADMIN_BOOTSTRAP_PASSWORD', 'AdminPass123!');

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('ADMIN_DEFAULT_EMAIL is missing or invalid.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = new User;
            $user->forceFill([
                'name' => 'Supreme Super Admin',
                'email' => $email,
                'password' => $password,
                'role' => UserRole::SUPER_ADMIN,
                'phone' => '+442080000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();

            $this->info("Created admin account for {$email}.");

            return self::SUCCESS;
        }

        $changed = false;

        if ($user->role !== UserRole::SUPER_ADMIN) {
            $user->role = UserRole::SUPER_ADMIN;
            $changed = true;
        }

        if (! $user->is_active) {
            $user->is_active = true;
            $changed = true;
        }

        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
            $changed = true;
        }

        $shouldResetPassword = $this->option('reset-password')
            || filter_var((string) env('ADMIN_RESET_PASSWORD', 'false'), FILTER_VALIDATE_BOOLEAN)
            || ! ($user->role instanceof UserRole && $user->role->canAccessFilament());

        if ($shouldResetPassword) {
            $user->password = $password;
            $changed = true;
        }

        if ($changed) {
            $user->save();
            $this->info("Updated admin account for {$email}.");
        } else {
            $this->info("Admin account already present for {$email}.");
        }

        return self::SUCCESS;
    }
}
