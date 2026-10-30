<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Contracts\NewsletterProvider;
use App\Models\HomepageContent;
use App\Models\HomepageFaq;
use App\Services\Catalogue\ProductRevisionService;
use App\Services\Marketing\LogNewsletterProvider;
use App\Services\Storefront\HomepageService;
use Illuminate\Support\Facades\Cache;
use App\Policies\InventoryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NewsletterProvider::class, LogNewsletterProvider::class);

        if ($this->app->environment('qa')) {
            $qaSqlite = database_path('qa.sqlite');
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => $qaSqlite,
                'database.connections.sqlite.url' => null,
                'database.connections.pgsql.url' => null,
                'database.connections.pgsql_direct.url' => null,
            ]);
        }

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
        if ($this->app->environment('qa')) {
            \Illuminate\Support\Facades\DB::purge();
            \Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');
            \Illuminate\Support\Facades\DB::reconnect('sqlite');
        }

        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Password::defaults(static fn () => Password::min(8)->letters()->mixedCase()->numbers());

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Inventory::class, InventoryPolicy::class);

        Product::updated(function (Product $product) {
            app(ProductRevisionService::class)->recordIfChanged($product, [
                'name',
                'description',
                'short_description',
                'price_amount',
                'compare_at_price_amount',
                'status',
                'classification',
                'brand_id',
                'category_id',
                'compliance_verified_at',
            ]);
            Cache::forget('catalogue.categories.visible');
            Cache::forget('catalogue.categories.visible.v2');
            Cache::forget('catalogue.brands.active');
            Cache::forget('catalogue.brands.active.v2');
            Cache::forget('catalogue.navigation');
            Cache::forget('catalogue.navigation.v4');
            Cache::forget('catalogue.navigation.highlights.v1');
            app(HomepageService::class)->flush();
        });

        Category::saved(function () {
            Cache::forget('catalogue.categories.visible');
            Cache::forget('catalogue.categories.visible.v2');
            Cache::forget('catalogue.navigation');
            Cache::forget('catalogue.navigation.v4');
            Cache::forget('catalogue.navigation.highlights.v1');
            app(HomepageService::class)->flush();
        });
        Brand::saved(function () {
            Cache::forget('catalogue.brands.active');
            Cache::forget('catalogue.brands.active.v2');
            Cache::forget('catalogue.navigation');
            Cache::forget('catalogue.navigation.v4');
            Cache::forget('catalogue.navigation.highlights.v1');
            app(HomepageService::class)->flush();
        });

        HomepageContent::saved(fn () => app(HomepageService::class)->flush());
        HomepageFaq::saved(fn () => app(HomepageService::class)->flush());
    }
}
