<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Policies\InventoryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $fallbacks = [
            'database.default' => filled(env('DATABASE_URL')) ? 'pgsql' : 'sqlite',
            'session.driver' => 'database',
            'cache.default' => 'file',
            'logging.default' => 'stderr',
            'queue.default' => 'sync',
            'mail.default' => 'log',
            'filesystems.default' => 'local',
            'hashing.driver' => 'bcrypt',
            'app.maintenance.driver' => 'file',
        ];

        foreach ($fallbacks as $key => $fallback) {
            if (blank(config($key))) {
                config([$key => $fallback]);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Inventory::class, InventoryPolicy::class);
    }
}
