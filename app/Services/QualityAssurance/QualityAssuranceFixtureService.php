<?php

declare(strict_types=1);

namespace App\Services\QualityAssurance;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Enums\ShippingZoneType;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CheckoutSession;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\ShippingRule;
use App\Models\ShippingZone;
use App\Models\StoredFile;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Services\Catalogue\CatalogueDiscoveryService;
use App\Services\Storefront\HomepageService;
use App\Support\QualityAssuranceDatabase;
use App\Support\QualityAssuranceGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QualityAssuranceFixtureService
{
    public const CUSTOMER_A_EMAIL = 'qa-customer@example.test';

    public const CUSTOMER_B_EMAIL = 'qa-customer-b@example.test';

    public const ADMIN_EMAIL = 'qa-admin@example.test';

    public const CATEGORY_SLUG = 'qa-category';

    public const BRAND_SLUG = 'qa-brand';

    public function seed(): array
    {
        QualityAssuranceDatabase::bindIsolatedSqliteIfNeeded();
        QualityAssuranceGuard::assertSafeEnvironment();

        $password = (string) env('QA_CUSTOMER_PASSWORD', 'QaLocalOnly-123!');
        $adminPassword = (string) env('QA_ADMIN_PASSWORD', 'QaAdminLocal-123!');

        $this->ensureShipping();

        $admin = $this->user(self::ADMIN_EMAIL, 'QA Admin', UserRole::SUPER_ADMIN, $adminPassword);
        $customerA = $this->user(self::CUSTOMER_A_EMAIL, 'QA Customer A', UserRole::CUSTOMER, $password);
        $customerB = $this->user(self::CUSTOMER_B_EMAIL, 'QA Customer B', UserRole::CUSTOMER, $password);

        $address = Address::query()->updateOrCreate(
            ['user_id' => $customerA->id, 'address_line_1' => '10 QA Sample Street'],
            [
                'type' => 'shipping',
                'full_name' => 'QA Customer A',
                'address_line_2' => 'Fixture Suite',
                'city' => 'London',
                'state_county' => 'Greater London',
                'postal_code' => 'W6 9HW',
                'country_code' => 'GB',
                'phone' => '+440000000000',
                'is_default' => true,
            ]
        );

        $brand = Brand::query()->updateOrCreate(
            ['slug' => self::BRAND_SLUG],
            [
                'name' => 'QA Sample Brand',
                'description' => 'Fictional brand used only for local quality-assurance fixtures.',
                'country_of_origin' => 'GB',
                'is_verified' => true,
                'is_active' => true,
                'logo_url' => '/assets/branding/supreme-steroids-logo.png',
                'display_order' => 1,
            ]
        );

        $category = Category::query()->updateOrCreate(
            ['slug' => self::CATEGORY_SLUG],
            [
                'name' => 'QA Category',
                'description' => 'Public fixture category for local browser quality assurance.',
                'display_order' => 1,
                'is_visible' => true,
                'is_featured' => true,
                'image_url' => '/assets/branding/supreme-steroids-logo.png',
            ]
        );

        $productA = $this->product([
            'name' => 'QA Premium Sample',
            'slug' => 'qa-premium-sample',
            'sku' => 'QA-SKU-001',
            'price_amount' => 45.00,
            'short_description' => 'Neutral local fixture product with no variants.',
            'is_featured' => true,
        ], $brand, $category, 25);

        $productB = $this->product([
            'name' => 'QA Variant Sample',
            'slug' => 'qa-variant-sample',
            'sku' => 'QA-SKU-002',
            'price_amount' => 38.00,
            'short_description' => 'Neutral local fixture product with multiple variants.',
        ], $brand, $category, 0, attachBaseInventory: false);

        $this->variant($productB, 'QA-SKU-002-S', 'Small', '38.00', 12);
        $this->variant($productB, 'QA-SKU-002-L', 'Large', '42.00', 8);

        $sale = $this->product([
            'name' => 'QA Sale Sample',
            'slug' => 'qa-sale-sample',
            'sku' => 'QA-SKU-003',
            'price_amount' => 29.00,
            'compare_at_price_amount' => 40.00,
            'short_description' => 'Neutral local fixture product with a compare-at price.',
            'is_featured' => true,
        ], $brand, $category, 15);

        $outOfStock = $this->product([
            'name' => 'QA Out of Stock Sample',
            'slug' => 'qa-out-of-stock-sample',
            'sku' => 'QA-SKU-004',
            'price_amount' => 22.00,
            'short_description' => 'Neutral local fixture product with zero available stock.',
        ], $brand, $category, 0);

        $this->product([
            'name' => 'QA Demo Sample',
            'slug' => 'qa-demo-sample',
            'sku' => 'QA-SKU-DEMO',
            'price_amount' => 19.00,
            'short_description' => 'Internal demo fixture that must never appear publicly.',
            'is_demo' => true,
        ], $brand, $category, 10);

        for ($index = 1; $index <= 10; $index++) {
            $this->product([
                'name' => 'QA Listing Sample '.$index,
                'slug' => 'qa-listing-sample-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'sku' => 'QA-SKU-L'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'price_amount' => 18 + $index,
                'short_description' => 'Neutral listing fixture used to exercise category pagination.',
            ], $brand, $category, 6);
        }

        $this->forgetCaches();

        return [
            'admin' => $admin->only(['id', 'email', 'name']),
            'customer_a' => $customerA->only(['id', 'email', 'name']),
            'customer_b' => $customerB->only(['id', 'email', 'name']),
            'address_id' => $address->id,
            'category' => $category->only(['id', 'slug', 'name']),
            'brand' => $brand->only(['id', 'slug', 'name']),
            'products' => [
                'premium' => $productA->only(['id', 'slug', 'sku']),
                'variant' => $productB->fresh()->only(['id', 'slug', 'sku']),
                'sale' => $sale->only(['id', 'slug', 'sku']),
                'out_of_stock' => $outOfStock->only(['id', 'slug', 'sku']),
            ],
        ];
    }

    public function reset(): int
    {
        QualityAssuranceDatabase::bindIsolatedSqliteIfNeeded();
        QualityAssuranceGuard::assertSafeEnvironment();

        $deleted = 0;

        $users = User::query()->where('email', 'like', 'qa-%@example.test')->get();
        $userIds = $users->pluck('id');
        $products = Product::withTrashed()->where('sku', 'like', 'QA-SKU-%')->orWhere('slug', 'like', 'qa-%')->get();
        $productIds = $products->pluck('id');

        $orders = Order::query()->whereIn('user_id', $userIds)->get();
        $orderIds = $orders->pluck('id');
        $payments = Payment::query()->whereIn('order_id', $orderIds)->get();
        $paymentIds = $payments->pluck('id');
        $submissions = PaymentSubmission::query()->whereIn('payment_id', $paymentIds)->get();
        $fileIds = $submissions->pluck('proof_file_id')->filter();

        DB::transaction(function () use (
            $userIds,
            $productIds,
            $orderIds,
            $paymentIds,
            $submissions,
            $fileIds,
            $users,
            $products,
            &$deleted
        ): void {
            $deleted += PaymentSubmission::query()->whereIn('payment_id', $paymentIds)->delete();
            $deleted += Payment::query()->whereIn('id', $paymentIds)->delete();
            if (Schema::hasTable('order_items')) {
                $deleted += DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            }
            if (Schema::hasTable('order_status_history')) {
                $deleted += DB::table('order_status_history')->whereIn('order_id', $orderIds)->delete();
            }
            if (Schema::hasTable('inventory_reservations')) {
                $deleted += DB::table('inventory_reservations')->whereIn('product_id', $productIds)->delete();
            }
            $deleted += CheckoutSession::query()->whereIn('user_id', $userIds)->delete();
            $deleted += Order::query()->whereIn('id', $orderIds)->delete();

            $cartIds = Cart::query()->whereIn('user_id', $userIds)->pluck('id');
            $deleted += CartItem::query()->whereIn('cart_id', $cartIds)->delete();
            $deleted += Cart::query()->whereIn('id', $cartIds)->delete();

            $wishlistIds = Wishlist::query()->whereIn('user_id', $userIds)->pluck('id');
            $deleted += WishlistItem::query()->whereIn('wishlist_id', $wishlistIds)->orWhereIn('product_id', $productIds)->delete();
            $deleted += Wishlist::query()->whereIn('id', $wishlistIds)->delete();

            $deleted += Address::query()->whereIn('user_id', $userIds)->delete();
            foreach (['reviews', 'stock_notifications', 'product_contents', 'product_revisions', 'product_relations', 'product_attributes'] as $table) {
                if (Schema::hasTable($table)) {
                    $deleted += DB::table($table)->whereIn('product_id', $productIds)->delete();
                }
            }
            if (Schema::hasTable('variant_attributes')) {
                $variantIds = ProductVariant::query()->whereIn('product_id', $productIds)->pluck('id');
                $deleted += DB::table('variant_attributes')->whereIn('product_variant_id', $variantIds)->delete();
            }
            $deleted += Inventory::query()->whereIn('product_id', $productIds)->delete();
            $deleted += ProductImage::query()->whereIn('product_id', $productIds)->delete();
            $deleted += ProductVariant::query()->whereIn('product_id', $productIds)->delete();
            if (Schema::hasTable('product_category')) {
                $deleted += DB::table('product_category')->whereIn('product_id', $productIds)->delete();
            }
            if (Schema::hasTable('product_relations')) {
                $deleted += DB::table('product_relations')
                    ->whereIn('product_id', $productIds)
                    ->orWhereIn('related_product_id', $productIds)
                    ->delete();
            }
            $products->each->forceDelete();
            $deleted += $products->count();

            Category::query()->where('slug', 'like', 'qa-%')->delete();
            Brand::query()->where('slug', 'like', 'qa-%')->delete();
            $users->each->forceDelete();
            $deleted += $users->count();

            if ($fileIds->isNotEmpty()) {
                $deleted += StoredFile::query()->whereIn('id', $fileIds)->delete();
            }
        });

        $this->forgetCaches();

        return $deleted;
    }

    protected function ensureShipping(): void
    {
        $uk = ShippingZone::query()->updateOrCreate(
            ['code' => 'UK_DOMESTIC'],
            [
                'name' => 'United Kingdom (Domestic)',
                'type' => ShippingZoneType::UK_DOMESTIC,
                'countries' => ['GB'],
                'is_active' => true,
            ]
        );

        $standard = ShippingMethod::query()->updateOrCreate(
            ['code' => 'UK_STANDARD', 'shipping_zone_id' => $uk->id],
            [
                'name' => 'UK Standard Delivery',
                'rate_amount' => 10.00,
                'currency' => 'GBP',
                'estimated_days' => '2-4 business days',
                'is_discreet' => false,
                'is_active' => true,
                'display_order' => 1,
            ]
        );

        ShippingRule::query()->updateOrCreate(
            ['shipping_method_id' => $standard->id, 'rule_type' => 'FREE_SUBTOTAL_THRESHOLD'],
            [
                'min_subtotal' => 300.00,
                'is_free_shipping' => true,
                'override_rate_amount' => 0.00,
                'is_active' => true,
            ]
        );

        ShippingMethod::query()->updateOrCreate(
            ['code' => 'UK_EXPRESS', 'shipping_zone_id' => $uk->id],
            [
                'name' => 'UK Express Delivery',
                'rate_amount' => 15.00,
                'currency' => 'GBP',
                'estimated_days' => '1-2 business days',
                'is_discreet' => false,
                'is_active' => true,
                'display_order' => 2,
            ]
        );

        $europe = ShippingZone::query()->updateOrCreate(
            ['code' => 'EUROPE'],
            [
                'name' => 'European Union & EEA',
                'type' => ShippingZoneType::EUROPE,
                'countries' => ['FR', 'DE', 'IE', 'NL', 'ES', 'IT'],
                'is_active' => true,
            ]
        );

        ShippingMethod::query()->updateOrCreate(
            ['code' => 'EUROPE_STANDARD', 'shipping_zone_id' => $europe->id],
            [
                'name' => 'Europe Tracked Shipping',
                'rate_amount' => 35.00,
                'currency' => 'GBP',
                'estimated_days' => '5-9 business days',
                'is_discreet' => true,
                'is_active' => true,
                'display_order' => 1,
            ]
        );

        $international = ShippingZone::query()->updateOrCreate(
            ['code' => 'INTERNATIONAL'],
            [
                'name' => 'International',
                'type' => ShippingZoneType::INTERNATIONAL,
                'countries' => ['US', 'CA', 'AU'],
                'is_active' => true,
            ]
        );

        ShippingMethod::query()->updateOrCreate(
            ['code' => 'INTL_STANDARD', 'shipping_zone_id' => $international->id],
            [
                'name' => 'International Tracked Shipping',
                'rate_amount' => 45.00,
                'currency' => 'GBP',
                'estimated_days' => '7-14 business days',
                'is_discreet' => true,
                'is_active' => true,
                'display_order' => 1,
            ]
        );
    }

    protected function user(string $email, string $name, UserRole $role, string $password): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function product(array $attributes, Brand $brand, Category $category, int $stock, bool $attachBaseInventory = true): Product
    {
        $product = Product::query()->updateOrCreate(
            ['sku' => $attributes['sku']],
            array_merge([
                'brand_id' => $brand->id,
                'category_id' => $category->id,
                'currency' => 'GBP',
                'status' => ProductStatus::ACTIVE,
                'classification' => ProductClassification::OTHER_LAWFUL_PRODUCT,
                'compliance_verified_at' => now()->subDay(),
                'batch_number' => 'QA-BATCH-001',
                'lab_verification_reference' => 'QA-LAB-001',
                'description' => 'Fictional lawful sample used only for isolated quality-assurance tests.',
                'published_at' => now()->subDay(),
                'is_featured' => false,
                'is_demo' => false,
                'sort_order' => 10,
            ], $attributes)
        );

        $product->categories()->syncWithoutDetaching([$category->id => ['is_primary' => true]]);

        ProductImage::query()->updateOrCreate(
            ['product_id' => $product->id, 'display_order' => 0],
            [
                'url' => '/assets/branding/supreme-steroids-logo.png',
                'alt_text' => $product->name,
                'is_primary' => true,
            ]
        );

        if ($attachBaseInventory) {
            Inventory::query()->updateOrCreate(
                ['product_id' => $product->id, 'product_variant_id' => null],
                [
                    'quantity_on_hand' => $stock,
                    'quantity_reserved' => 0,
                    'safety_stock_threshold' => 1,
                ]
            );
        }

        return $product;
    }

    protected function variant(Product $product, string $sku, string $name, string $price, int $stock): ProductVariant
    {
        $variant = ProductVariant::query()->updateOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $product->id,
                'name' => $name,
                'price_amount' => $price,
                'is_active' => true,
            ]
        );

        Inventory::query()->updateOrCreate(
            ['product_id' => $product->id, 'product_variant_id' => $variant->id],
            [
                'quantity_on_hand' => $stock,
                'quantity_reserved' => 0,
                'safety_stock_threshold' => 1,
            ]
        );

        return $variant;
    }

    protected function forgetCaches(): void
    {
        Cache::forget(HomepageService::CACHE_PAGE);
        Cache::forget(HomepageService::CACHE_CHROME);
        Cache::forget('catalogue.navigation');
        Cache::forget('catalogue.navigation.v2');
        Cache::forget('catalogue.categories.visible');
        Cache::forget('catalogue.categories.visible.v2');
        Cache::forget('catalogue.brands.active');
        Cache::forget('catalogue.brands.active.v2');
    }
}
