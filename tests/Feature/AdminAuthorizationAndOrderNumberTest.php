<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Order\OrderCreationService;
use Database\Seeders\InitialFoundationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class AdminAuthorizationAndOrderNumberTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialFoundationSeeder::class);
    }

    public function test_customer_cannot_view_another_customers_order_via_policy(): void
    {
        $product = $this->makeProduct();
        $customerA = $this->makeCustomer('a@auth.test', 'Customer A');
        $customerB = $this->makeCustomer('b@auth.test', 'Customer B');

        $order = app(OrderCreationService::class)->createOrder([
            'customer' => $customerA,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => $this->shippingDestination($customerA->name),
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);

        $this->assertTrue(Gate::forUser($customerA)->allows('view', $order));
        $this->assertTrue(Gate::forUser($customerB)->denies('view', $order));
        $this->assertTrue(Gate::forUser($this->makeAdmin('policy-admin@test.com'))->allows('view', $order));
    }

    public function test_staff_cannot_publish_products_without_catalogue_permission(): void
    {
        $staff = User::create([
            'name' => 'Staffer',
            'email' => 'staff-auth@test.com',
            'password' => bcrypt('Secret123!'),
            'role' => UserRole::STAFF,
            'is_active' => true,
        ]);
        $product = $this->makeProduct();

        $this->assertTrue(Gate::forUser($staff)->denies('update', $product));
        $this->assertTrue(Gate::forUser($staff)->denies('verifyCompliance', $product));
        $this->assertTrue(Gate::forUser($this->makeAdmin('cat-admin@test.com'))->allows('update', $product));
    }

    public function test_order_numbers_are_unique_and_human_friendly(): void
    {
        $product = $this->makeProduct(['price_amount' => 20.00], 50);
        $customer = $this->makeCustomer('numbers@test.com');
        $service = app(OrderCreationService::class);

        $first = $service->createOrder([
            'customer' => $customer,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => $this->shippingDestination(),
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);

        $second = $service->createOrder([
            'customer' => $customer,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => $this->shippingDestination(),
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::CRYPTOCURRENCY,
        ]);

        $this->assertMatchesRegularExpression('/^SS-\d{4}-\d{6}$/', $first->order_number);
        $this->assertMatchesRegularExpression('/^SS-\d{4}-\d{6}$/', $second->order_number);
        $this->assertNotEquals($first->order_number, $second->order_number);
        $this->assertEquals(2, Order::query()->count());
    }

    public function test_order_creation_rolls_back_when_inventory_is_insufficient(): void
    {
        $product = $this->makeProduct(['price_amount' => 30.00], 1);
        $customer = $this->makeCustomer('rollback@test.com');

        $this->expectException(\App\Exceptions\ProductNotPurchasableException::class);

        try {
            app(OrderCreationService::class)->createOrder([
                'customer' => $customer,
                'items' => [['product_id' => $product->id, 'quantity' => 5]],
                'shipping_destination' => $this->shippingDestination(),
                'shipping_method_code' => 'UK_STANDARD',
                'payment_method' => PaymentMethodType::BANK_TRANSFER,
            ]);
        } finally {
            $this->assertSame(0, Order::query()->count());
            $this->assertSame(0, \App\Models\Payment::query()->count());
        }
    }

    public function test_checkout_validation_rejects_invalid_country_and_empty_cart(): void
    {
        $customer = $this->makeCustomer('validate@test.com');

        $this->actingAs($customer)
            ->post(route('checkout.store'), [
                'items' => [],
                'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
                'shipping_destination' => [
                    'country_code' => 'United Kingdom',
                    'full_name' => 'A',
                    'address_line_1' => 'B',
                    'city' => 'London',
                    'postal_code' => 'SW1A 1AA',
                ],
            ])
            ->assertSessionHasErrors();
    }

    public function test_customer_http_authorization_hides_foreign_orders(): void
    {
        $product = $this->makeProduct();
        $customerA = $this->makeCustomer('http-a@test.com', 'Http A');
        $customerB = $this->makeCustomer('http-b@test.com', 'Http B');

        $order = app(OrderCreationService::class)->createOrder([
            'customer' => $customerA,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => $this->shippingDestination($customerA->name),
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);

        $this->actingAs($customerB)
            ->get(route('orders.show', $order->id))
            ->assertNotFound();
    }
}
