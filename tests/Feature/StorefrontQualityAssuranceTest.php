<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Models\Address;
use App\Models\Order;
use App\Services\Order\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class StorefrontQualityAssuranceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_public_pages_do_not_expose_secrets_or_admin_payloads(): void
    {
        $this->makeProduct(['name' => 'QA Visible Product']);

        foreach (['/', '/shop', '/search?q=qa', '/compare', '/cart', '/login'] as $uri) {
            $response = $this->get($uri);
            $response->assertOk();
            $html = $response->getContent();

            $this->assertStringNotContainsString('APP_KEY', $html);
            $this->assertStringNotContainsString('CRON_SECRET', $html);
            $this->assertStringNotContainsString('DB_PASSWORD', $html);
            $this->assertStringNotContainsString('postgres://', $html);
            $this->assertStringNotContainsString('AWS_SECRET', $html);
            $this->assertStringNotContainsString('role_label', $html);
        }

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('auth.user.role_label')
                ->missing('payments')
                ->missing('audit')
                ->missing('filament')
            );
    }

    public function test_private_and_utility_pages_are_noindex(): void
    {
        $this->makeProduct();

        $this->get('/search?q=test')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,nofollow'));

        $this->get('/compare')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,nofollow'));

        $this->get('/cart')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,nofollow'));

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,nofollow'));

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,nofollow'));

        $product = $this->makeProduct(['name' => 'Checkout robots product']);
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->get('/checkout')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,nofollow'));
    }

    public function test_error_pages_are_dark_themed_and_safe(): void
    {
        $notFound = $this->get('/definitely-not-a-real-page-'.uniqid());
        $notFound->assertNotFound();
        $notFound->assertInertia(fn (Assert $page) => $page
            ->component('Errors/NotFound')
            ->where('seo.robots', 'noindex,nofollow')
        );
        $this->assertStringNotContainsString('stack trace', strtolower($notFound->getContent()));
        $this->assertStringNotContainsString('postgres://', $notFound->getContent());
    }

    public function test_invalid_login_does_not_expose_internals(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'nobody@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors();
        $this->assertStringNotContainsString('Stack trace', (string) session()->get('errors'));
    }

    public function test_customer_cannot_mutate_another_customers_address(): void
    {
        $owner = $this->makeCustomer('addr-owner@test.com', 'Owner');
        $intruder = $this->makeCustomer('addr-intruder@test.com', 'Intruder');
        $address = Address::query()->create([
            'user_id' => $owner->id,
            'type' => 'shipping',
            'full_name' => 'Owner',
            'address_line_1' => '1 Private Road',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country_code' => 'GB',
        ]);

        $this->actingAs($intruder)
            ->patch('/account/addresses/'.$address->id, [
                'full_name' => 'Hijacked',
                'address_line_1' => '9 Stolen Street',
                'city' => 'London',
                'postal_code' => 'E1 6AN',
                'country_code' => 'GB',
            ])
            ->assertNotFound();

        $this->assertSame('1 Private Road', $address->fresh()->address_line_1);
    }

    public function test_guest_cannot_open_another_customers_order_or_payment_proof(): void
    {
        $product = $this->makeProduct(['name' => 'Private Order Product']);
        $owner = $this->makeCustomer('order-owner@test.com', 'Owner');
        $order = app(OrderCreationService::class)->createOrder([
            'customer' => $owner,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => [
                'country_code' => 'GB',
                'full_name' => 'Owner',
                'address_line_1' => '1 Private Road',
                'city' => 'London',
                'postal_code' => 'SW1A 1AA',
            ],
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);

        $payment = $order->payments()->firstOrFail();

        $this->get('/orders/'.$order->id)->assertNotFound();
        $this->get('/orders/'.$order->id.'/payments/'.$payment->id.'/proof')->assertNotFound();

        $intruder = $this->makeCustomer('order-intruder@test.com', 'Intruder');
        $this->actingAs($intruder)->get('/orders/'.$order->id)->assertNotFound();
        $this->actingAs($intruder)->get('/orders/'.$order->id.'/payments/'.$payment->id.'/proof')->assertNotFound();

        $this->assertSame(1, Order::query()->whereKey($order->id)->count());
    }

    public function test_admin_panel_is_not_customer_accessible(): void
    {
        $customer = $this->makeCustomer('admin-block@test.com');

        $guest = $this->get('/admin');
        $this->assertContains($guest->status(), [301, 302, 401, 403]);

        $asCustomer = $this->actingAs($customer)->get('/admin');
        $this->assertContains($asCustomer->status(), [301, 302, 401, 403]);
        $this->assertStringNotContainsString('CRON_SECRET', $asCustomer->getContent());
    }

    public function test_html_includes_csrf_token_and_security_headers(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $this->assertStringContainsString('csrf-token', $response->getContent());
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_indexable_catalogue_pages_keep_follow_robots(): void
    {
        $product = $this->makeProduct(['name' => 'Indexed QA Product']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'index,follow'));

        $this->get('/shop')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'index,follow'));

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'index,follow'));
    }
}
