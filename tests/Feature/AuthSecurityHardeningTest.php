<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_login(): void
    {
        User::forceCreate([
            'name' => 'Inactive Customer',
            'email' => 'inactive@customer.test',
            'password' => bcrypt('Secret123!'),
            'role' => UserRole::CUSTOMER,
            'is_active' => false,
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'inactive@customer.test',
                'password' => 'Secret123!',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_cannot_mutate_foreign_guest_cart_item(): void
    {
        $brand = Brand::create([
            'name' => 'Security Brand',
            'slug' => 'security-brand',
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Security Category',
            'slug' => 'security-category',
            'is_visible' => true,
        ]);
        $product = Product::create([
            'name' => 'Hardened Product',
            'slug' => 'hardened-product',
            'sku' => 'SEC-HARD-1',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'price_amount' => 25.00,
            'currency' => 'GBP',
            'status' => ProductStatus::ACTIVE,
            'classification' => ProductClassification::OTHER_LAWFUL_PRODUCT,
            'compliance_verified_at' => now()->subDay(),
        ]);
        Inventory::create([
            'product_id' => $product->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'safety_stock_threshold' => 1,
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => 'https://cdn.example.test/hardened.webp',
            'alt_text' => 'Hardened Product',
            'display_order' => 1,
            'is_primary' => true,
        ]);

        $guestSessionId = 'guest-session-foreign';
        $cartService = app(CartService::class);
        $guestCart = $cartService->getOrCreateCart(user: null, sessionId: $guestSessionId);
        $item = $cartService->addItem($guestCart, $product, null, 1, null, $guestSessionId);

        $attacker = User::forceCreate([
            'name' => 'Attacker',
            'email' => 'attacker@customer.test',
            'password' => bcrypt('Secret123!'),
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
        ]);

        $this->actingAs($attacker)
            ->patch('/cart/items/'.$item->id, ['quantity' => 3])
            ->assertForbidden();

        $this->assertSame(1, (int) $item->fresh()->quantity);

        $this->actingAs($attacker)
            ->delete('/cart/items/'.$item->id)
            ->assertForbidden();

        $this->assertDatabaseHas('cart_items', ['id' => $item->id]);
        $this->assertTrue(Cart::query()->whereKey($guestCart->id)->whereNull('user_id')->exists());
    }

    public function test_sensitive_mutation_routes_are_throttled(): void
    {
        $this->assertTrue(collect(\Illuminate\Support\Facades\Route::getRoutes())->contains(
            fn ($route) => $route->getName() === 'cart.items.add'
                && collect($route->gatherMiddleware())->contains('throttle:60,1')
        ));
        $this->assertTrue(collect(\Illuminate\Support\Facades\Route::getRoutes())->contains(
            fn ($route) => $route->getName() === 'checkout.prepare'
                && collect($route->gatherMiddleware())->contains('throttle:30,1')
        ));
        $this->assertTrue(collect(\Illuminate\Support\Facades\Route::getRoutes())->contains(
            fn ($route) => $route->getName() === 'orders.payment.proof'
                && collect($route->gatherMiddleware())->contains('throttle:10,1')
        ));
        $this->assertTrue(collect(\Illuminate\Support\Facades\Route::getRoutes())->contains(
            fn ($route) => $route->getName() === 'wishlist.toggle'
                && collect($route->gatherMiddleware())->contains('throttle:30,1')
        ));
    }

    public function test_privilege_fields_are_not_mass_assignable_on_user(): void
    {
        $user = User::create([
            'name' => 'Mass Assign',
            'email' => 'mass@customer.test',
            'password' => bcrypt('Secret123!'),
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => false,
            'email_verified_at' => now(),
        ])->fresh();

        // Privilege fields are ignored on mass assignment; DB defaults apply instead.
        $this->assertSame(UserRole::CUSTOMER, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->email_verified_at);
    }
}
