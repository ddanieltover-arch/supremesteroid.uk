<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\InvalidPriceException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PricingService $pricingService;
    protected Brand $brand;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService();

        $this->brand = Brand::create([
            'name' => 'Apex Pharma',
            'slug' => 'apex-pharma',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Injectables',
            'slug' => 'injectables',
            'is_visible' => true,
        ]);
    }

    public function test_normal_product_price_calculation(): void
    {
        $product = Product::create([
            'name' => 'Testosterone Enanthate',
            'slug' => 'test-e-250',
            'sku' => 'APEX-TE250',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 45.00,
            'compare_at_price_amount' => null,
            'currency' => 'GBP',
            'status' => 'ACTIVE',
            'classification' => 'RESEARCH_USE',
        ]);

        $pricing = $this->pricingService->calculateProductPrice($product);

        $this->assertEquals('45.00', $pricing['base_price_amount']);
        $this->assertEquals(4500, $pricing['base_price_pence']);
        $this->assertEquals('45.00', $pricing['effective_price_amount']);
        $this->assertEquals(4500, $pricing['effective_price_pence']);
        $this->assertNull($pricing['sale_price_amount']);
        $this->assertFalse($pricing['is_on_sale']);
    }

    public function test_sale_pricing_calculates_correctly(): void
    {
        $product = Product::create([
            'name' => 'Trenbolone Acetate Promo',
            'slug' => 'tren-a-promo',
            'sku' => 'APEX-TA100',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 50.00,
            'compare_at_price_amount' => 65.00,
            'currency' => 'GBP',
            'status' => 'ACTIVE',
            'classification' => 'RESEARCH_USE',
        ]);

        $pricing = $this->pricingService->calculateProductPrice($product);

        $this->assertTrue($pricing['is_on_sale']);
        $this->assertEquals('65.00', $pricing['base_price_amount']);
        $this->assertEquals(6500, $pricing['base_price_pence']);
        $this->assertEquals('50.00', $pricing['sale_price_amount']);
        $this->assertEquals(5000, $pricing['sale_price_pence']);
        $this->assertEquals('50.00', $pricing['effective_price_amount']);
        $this->assertEquals(5000, $pricing['effective_price_pence']);
    }

    public function test_invalid_sale_price_where_compare_at_is_lower_than_price_throws_exception(): void
    {
        $product = Product::create([
            'name' => 'Faulty Promo Product',
            'slug' => 'faulty-promo',
            'sku' => 'FAULT-01',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 60.00,
            'compare_at_price_amount' => 40.00, // Invalid: compare_at < price
            'currency' => 'GBP',
            'status' => 'ACTIVE',
            'classification' => 'RESEARCH_USE',
        ]);

        $this->expectException(InvalidPriceException::class);
        $this->pricingService->calculateProductPrice($product);
    }

    public function test_zero_or_negative_price_is_strictly_rejected(): void
    {
        $product = Product::create([
            'name' => 'Zero Price Glitch Product',
            'slug' => 'zero-price',
            'sku' => 'ZERO-01',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 0.00,
            'currency' => 'GBP',
            'status' => 'ACTIVE',
            'classification' => 'RESEARCH_USE',
        ]);

        $this->expectException(InvalidPriceException::class);
        $this->pricingService->calculateProductPrice($product);
    }

    public function test_decimal_values_calculate_without_floating_point_imprecision(): void
    {
        $product = Product::create([
            'name' => 'Odd Cent Item',
            'slug' => 'odd-cent',
            'sku' => 'ODD-01',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 29.99,
            'currency' => 'GBP',
            'status' => 'ACTIVE',
            'classification' => 'RESEARCH_USE',
        ]);

        // 29.99 * 3 = 89.97 exactly
        $line = $this->pricingService->calculateLineItem($product, null, 3, 0);

        $this->assertEquals(8997, $line['line_subtotal_pence']);
        $this->assertEquals('89.97', $line['line_subtotal_amount']);
        $this->assertEquals('89.97', $line['line_total_amount']);
    }

    public function test_discount_boundaries_are_enforced(): void
    {
        $product = Product::create([
            'name' => 'Discount Test Product',
            'slug' => 'discount-test',
            'sku' => 'DISC-01',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 100.00,
            'currency' => 'GBP',
            'status' => 'ACTIVE',
            'classification' => 'RESEARCH_USE',
        ]);

        // Negative discount throws exception
        $this->expectException(InvalidPriceException::class);
        $this->pricingService->calculateLineItem($product, null, 1, -10.00);
    }

    public function test_excessive_discount_is_capped_at_subtotal(): void
    {
        $product = Product::create([
            'name' => 'Cap Test Product',
            'slug' => 'cap-test',
            'sku' => 'CAP-01',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 50.00,
            'currency' => 'GBP',
            'status' => 'ACTIVE',
            'classification' => 'RESEARCH_USE',
        ]);

        // Discount of 100 on 50 item should cap at 50, resulting in 0 total
        $line = $this->pricingService->calculateLineItem($product, null, 1, 100.00);

        $this->assertEquals('50.00', $line['discount_amount']);
        $this->assertEquals('0.00', $line['line_total_amount']);
        $this->assertEquals(0, $line['line_total_pence']);
    }

    public function test_order_totals_aggregate_shipping_tax_and_discounts(): void
    {
        $p1 = Product::create([
            'name' => 'Item 1',
            'slug' => 'item-1',
            'sku' => 'ITM-01',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 49.99,
            'currency' => 'GBP',
            'status' => 'ACTIVE',
            'classification' => 'RESEARCH_USE',
        ]);

        $p2 = Product::create([
            'name' => 'Item 2',
            'slug' => 'item-2',
            'sku' => 'ITM-02',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 100.00,
            'currency' => 'GBP',
            'status' => 'ACTIVE',
            'classification' => 'RESEARCH_USE',
        ]);

        $items = [
            ['product' => $p1, 'quantity' => 2, 'discount' => 0], // 99.98
            ['product' => $p2, 'quantity' => 1, 'discount' => 10.00], // 100.00 - 10.00 = 90.00
        ];

        // Subtotal = 199.98
        // Discount = 10.00
        // Shipping = 15.00
        // Grand Total = 199.98 - 10.00 + 15.00 = 204.98
        $totals = $this->pricingService->calculateTotals(
            items: $items,
            shippingAmount: 15.00,
            orderDiscount: 0,
            taxAmount: 0,
            currency: 'GBP'
        );

        $this->assertEquals('199.98', $totals['subtotal_amount']);
        $this->assertEquals('10.00', $totals['discount_amount']);
        $this->assertEquals('15.00', $totals['shipping_amount']);
        $this->assertEquals('204.98', $totals['grand_total_amount']);
        $this->assertEquals(20498, $totals['grand_total_pence']);
        $this->assertEquals(3, $totals['items_count']);
    }

    public function test_quantity_multiplication_uses_integer_pence(): void
    {
        $product = Product::create([
            'name' => 'Qty Product',
            'slug' => 'qty-product',
            'sku' => 'QTY-01',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 12.50,
            'currency' => 'GBP',
            'status' => 'ACTIVE',
            'classification' => 'RESEARCH_USE',
        ]);

        $line = $this->pricingService->calculateLineItem($product, null, 4, 0);

        $this->assertEquals(5000, $line['line_subtotal_pence']);
        $this->assertEquals('50.00', $line['line_total_amount']);
    }
}
