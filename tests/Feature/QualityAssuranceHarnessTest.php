<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Models\Order;
use App\Models\Product;
use App\Services\QualityAssurance\QualityAssuranceFixtureService;
use App\Support\QualityAssuranceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class QualityAssuranceHarnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_guard_refuses_production_environment(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('QA fixtures cannot run when APP_ENV=production.');
        QualityAssuranceGuard::assertSafeEnvironment();
    }

    public function test_qa_seeder_creates_identifiable_local_fixtures(): void
    {
        $result = app(QualityAssuranceFixtureService::class)->seed();

        $this->assertSame('qa-customer@example.test', $result['customer_a']['email']);
        $this->assertSame('qa-category', $result['category']['slug']);
        $this->assertDatabaseHas('products', ['sku' => 'QA-SKU-001', 'is_demo' => false]);
        $this->assertDatabaseHas('products', ['sku' => 'QA-SKU-DEMO', 'is_demo' => true]);

        $this->get('/category/qa-category')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('heading', 'QA Category')
            );

        $this->get('/brand/qa-brand')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('heading', 'QA Sample Brand')
            );

        $this->get('/shop')
            ->assertOk()
            ->assertDontSee('QA Demo Sample');

        $this->get('/product/qa-demo-sample')->assertNotFound();
    }

    public function test_qa_out_of_stock_product_cannot_enter_the_cart(): void
    {
        app(QualityAssuranceFixtureService::class)->seed();
        $product = Product::query()->where('sku', 'QA-SKU-004')->firstOrFail();

        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_qa_customer_b_cannot_open_customer_a_order_or_address(): void
    {
        $fixtures = app(QualityAssuranceFixtureService::class)->seed();
        $owner = \App\Models\User::query()->where('email', 'qa-customer@example.test')->firstOrFail();
        $other = \App\Models\User::query()->where('email', 'qa-customer-b@example.test')->firstOrFail();
        $product = Product::query()->where('sku', 'QA-SKU-001')->firstOrFail();

        $order = app(\App\Services\Order\OrderCreationService::class)->createOrder([
            'customer' => $owner,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => [
                'country_code' => 'GB',
                'full_name' => 'QA Customer A',
                'address_line_1' => '10 QA Sample Street',
                'city' => 'London',
                'postal_code' => 'W6 9HW',
            ],
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);
        $payment = $order->payments()->firstOrFail();

        $this->actingAs($other)->get('/account/orders/'.$order->id)->assertNotFound();
        $this->actingAs($other)->get('/orders/'.$order->id)->assertNotFound();
        $this->actingAs($other)->get('/orders/'.$order->id.'/payments/'.$payment->id.'/proof')->assertNotFound();
        $this->actingAs($other)
            ->from('/account/addresses')
            ->patch('/account/addresses/'.$fixtures['address_id'], [
                'full_name' => 'Hijacked',
                'address_line_1' => '1 Stolen Road',
                'city' => 'London',
                'postal_code' => 'E1 6AN',
                'country_code' => 'GB',
                'type' => 'shipping',
            ]);

        $this->assertSame('10 QA Sample Street', \App\Models\Address::query()->findOrFail($fixtures['address_id'])->address_line_1);
    }

    public function test_qa_customer_can_authenticate_with_local_password(): void
    {
        app(QualityAssuranceFixtureService::class)->seed();

        $this->post('/login', [
            'email' => 'qa-customer@example.test',
            'password' => 'QaLocalOnly-123!',
        ])->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame('qa-customer@example.test', auth()->user()?->email);
    }

    public function test_qa_reset_removes_only_qa_fixtures(): void
    {
        app(QualityAssuranceFixtureService::class)->seed();
        $this->assertDatabaseHas('users', ['email' => 'qa-customer@example.test']);

        app(QualityAssuranceFixtureService::class)->reset();

        $this->assertDatabaseMissing('users', ['email' => 'qa-customer@example.test']);
        $this->assertDatabaseMissing('products', ['sku' => 'QA-SKU-001']);
        $this->assertDatabaseMissing('categories', ['slug' => 'qa-category']);
    }
}
