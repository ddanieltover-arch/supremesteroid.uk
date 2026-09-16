<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesCatalogueFixtures
{
    protected function makeBrand(string $name = 'Apex Pharma'): Brand
    {
        return Brand::firstOrCreate(['slug' => Str::slug($name)], [
            'name' => $name,
            'is_active' => true,
        ]);
    }

    protected function makeCategory(string $name = 'Injectables'): Category
    {
        return Category::firstOrCreate(['slug' => Str::slug($name)], [
            'name' => $name,
            'is_visible' => true,
        ]);
    }

    protected function makeCustomer(string $email = 'alice@customer.test', string $name = 'Alice Customer'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('Secret123!'),
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
        ]);
    }

    protected function makeAdmin(string $email = 'admin@supreme.test'): User
    {
        return User::create([
            'name' => 'Admin Boss',
            'email' => $email,
            'password' => bcrypt('AdminSecret123!'),
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeProduct(array $attributes = [], int $stock = 20): Product
    {
        $brand = $this->makeBrand();
        $category = $this->makeCategory();
        $name = $attributes['name'] ?? 'Testosterone Cypionate';

        $product = Product::create(array_merge([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(6)),
            'sku' => 'SKU-' . strtoupper(Str::random(8)),
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'price_amount' => 55.00,
            'currency' => 'GBP',
            'status' => ProductStatus::ACTIVE,
            'classification' => ProductClassification::OTC_CONSUMER,
            'compliance_verified_at' => now()->subDay(),
            'batch_number' => 'BATCH-TEST-001',
            'lab_verification_reference' => 'LAB-REF-001',
        ], $attributes));

        Inventory::create([
            'product_id' => $product->id,
            'quantity_on_hand' => $stock,
            'quantity_reserved' => 0,
            'safety_stock_threshold' => 2,
        ]);

        return $product->fresh(['inventory']);
    }

    protected function makeVariant(Product $product, string $name = '10ml', string $price = '55.00', int $stock = 10): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $product->sku . '-' . strtoupper(Str::random(4)),
            'name' => $name,
            'price_amount' => $price,
            'is_active' => true,
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => $stock,
            'quantity_reserved' => 0,
            'safety_stock_threshold' => 1,
        ]);

        return $variant->fresh(['inventory']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function shippingDestination(string $name = 'Alice Customer'): array
    {
        return [
            'country_code' => 'GB',
            'full_name' => $name,
            'address_line_1' => '10 Downing Street',
            'city' => 'London',
            'postal_code' => 'SW1A 2AA',
        ];
    }
}
