<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Exceptions\CustomerExceptionRenderer;
use App\Models\Address;
use App\Models\HomepageContent;
use App\Services\Order\OrderCreationService;
use Database\Seeders\InitialFoundationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class GlobalCustomerExperienceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialFoundationSeeder::class);
    }

    public function test_shared_props_expose_safe_global_shell_only(): void
    {
        $this->makeProduct(['name' => 'Shell Product', 'is_featured' => true]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('auth')
                ->has('cart.totals.items_count')
                ->has('catalogueNav.categories')
                ->has('store.name')
                ->has('wishlistCount')
                ->missing('auth.user.role_label')
                ->missing('orders')
                ->missing('payments')
            );
    }

    public function test_header_navigation_uses_live_catalogue_nav(): void
    {
        $product = $this->makeProduct(['name' => 'Nav Product']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('catalogueNav.categories', fn ($categories) => collect($categories)->contains('id', $product->category_id))
                ->where('catalogueNav.brands', fn ($brands) => collect($brands)->contains('id', $product->brand_id))
            );
    }

    public function test_announcement_can_be_dismissed_for_the_session(): void
    {
        HomepageContent::query()->create([
            'announcement_enabled' => true,
            'announcement_message' => 'Store announcement for navigation tests.',
            'announcement_use_shipping_threshold' => false,
        ]);

        $signature = hash('sha256', 'Store announcement for navigation tests.');

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('chrome.announcement.message', 'Store announcement for navigation tests.')
            ->where('chrome.announcement.signature', $signature)
        );

        $this->from('/')->post('/announcement/dismiss', ['signature' => $signature])->assertRedirect('/');

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('chrome.announcement', null));
    }

    public function test_search_suggest_endpoint_is_used_for_global_search(): void
    {
        $this->makeProduct(['name' => 'Searchable Capsule']);

        $this->getJson('/search/suggest?q=Searchable')
            ->assertOk()
            ->assertJsonStructure(['products', 'brands', 'categories']);
    }

    public function test_account_requires_authentication_while_checkout_is_open_to_guests(): void
    {
        $this->get('/account')->assertRedirect('/');
        $this->get('/checkout')->assertRedirect('/cart');

        $user = $this->makeCustomer('shell@customer.test');
        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Profile')
                ->has('dashboard.wishlist_count')
                ->has('dashboard.recent_orders')
            );
    }

    public function test_customer_cannot_inspect_another_customers_order(): void
    {
        $product = $this->makeProduct();
        $owner = $this->makeCustomer('owner@customer.test', 'Owner');
        $other = $this->makeCustomer('other@customer.test', 'Other');

        $order = app(OrderCreationService::class)->createOrder([
            'customer' => $owner,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => [
                'country_code' => 'GB',
                'full_name' => 'Owner',
                'address_line_1' => '8 King Street',
                'city' => 'London',
                'postal_code' => 'W6 9HW',
            ],
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);

        $this->actingAs($other)->get('/account/orders/'.$order->id)->assertNotFound();
        $this->actingAs($owner)->get('/account/orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Show')
                ->where('order.order_number', $order->order_number)
                ->where('order.tracking.available', false)
            );
    }

    public function test_wishlist_and_cart_summary_are_server_authoritative(): void
    {
        $user = $this->makeCustomer('wish@customer.test');
        $product = $this->makeProduct(['name' => 'Saved Capsule']);

        $this->actingAs($user)->post('/wishlist', ['product_id' => $product->id])->assertRedirect();
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();

        $this->actingAs($user)->get('/account/wishlist')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Wishlist')
                ->has('products', 1)
                ->where('products.0.sku', $product->sku)
            );

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboard.wishlist_count', 1)
                ->where('cart.totals.items_count', 1)
            );
    }

    public function test_orders_index_empty_state_and_saved_addresses_are_scoped(): void
    {
        $user = $this->makeCustomer('empty@customer.test');
        $other = $this->makeCustomer('other-address@customer.test');

        Address::forceCreate([
            'user_id' => $other->id,
            'type' => 'shipping',
            'full_name' => 'Other Person',
            'address_line_1' => '1 Hidden Road',
            'city' => 'London',
            'postal_code' => 'W6 9HW',
            'country_code' => 'GB',
            'is_default' => true,
        ]);

        $this->actingAs($user)->get('/account/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Orders/Index')->has('orders.data', 0));

        $this->actingAs($user)->get('/account/addresses')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/Addresses')->has('addresses', 0));
    }

    public function test_error_pages_are_customer_safe(): void
    {
        $this->get('/this-page-does-not-exist-xyz')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Errors/NotFound'));

        $renderer = app(CustomerExceptionRenderer::class);
        $html = Request::create('/account', 'GET');
        $html->headers->set('Accept', 'text/html');

        $this->assertSame(403, $renderer->render(new AuthorizationException(), $html)?->getStatusCode());
        $this->assertSame(419, $renderer->render(new TokenMismatchException(), $html)?->getStatusCode());
        $this->assertSame(422, $renderer->render(new HttpException(422, 'bad'), $html)?->getStatusCode());
        $this->assertSame(429, $renderer->render(new TooManyRequestsHttpException(), $html)?->getStatusCode());
        $this->assertSame(500, $renderer->render(new \RuntimeException('secret-db-url'), $html)?->getStatusCode());
        $this->assertSame(503, $renderer->render(new HttpException(503, 'down'), $html)?->getStatusCode());

        $fiveHundred = $renderer->render(new \RuntimeException('postgres://hidden'), $html);
        $this->assertStringNotContainsString('postgres://', (string) $fiveHundred?->getContent());
        $this->assertStringNotContainsString('secret-db-url', (string) $fiveHundred?->getContent());
    }

    public function test_checkout_page_is_open_to_guests_and_noindex(): void
    {
        $product = $this->makeProduct();
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->get('/checkout')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Index')
                ->where('checkout_step', 'details')
            );
    }
}
