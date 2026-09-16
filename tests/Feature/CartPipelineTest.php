<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected CartService $cartService;
    protected Brand $brand;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartService = app(CartService::class);

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

    protected function createProduct(string $name, float $price, int $stock = 10): Product
    {
        $product = Product::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
            'sku' => strtoupper(substr($name, 0, 4)) . '-' . uniqid(),
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => $price,
            'currency' => 'GBP',
            'status' => ProductStatus::ACTIVE,
            'classification' => ProductClassification::RESEARCH_USE,
            'compliance_verified_at' => Carbon::now()->subDay(),
            'batch_number' => 'BATCH-CART-001',
            'lab_verification_reference' => 'LAB-CART-001',
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'quantity_on_hand' => $stock,
            'quantity_reserved' => 0,
            'safety_stock_threshold' => 2,
        ]);

        return $product;
    }

    public function test_guest_cart_creation_and_item_addition(): void
    {
        $sessionId = 'sess_' . uniqid();
        $cart = $this->cartService->getOrCreateCart(user: null, sessionId: $sessionId);

        $this->assertNotNull($cart);
        $this->assertEquals($sessionId, $cart->session_id);
        $this->assertNull($cart->user_id);

        $product = $this->createProduct('Anavar 10mg', 35.00, 15);

        $item = $this->cartService->addItem($cart, $product, null, 2, null, $sessionId);

        $this->assertNotNull($item);
        $this->assertEquals(2, $item->quantity);

        $calculated = $this->cartService->calculateCart($cart);
        $this->assertCount(1, $calculated['items']);
        $this->assertEquals('70.00', $calculated['totals']['subtotal_amount']);
        $this->assertTrue($calculated['is_checkout_ready']);
    }

    public function test_cart_item_quantity_update_and_removal(): void
    {
        $sessionId = 'sess_' . uniqid();
        $cart = $this->cartService->getOrCreateCart(user: null, sessionId: $sessionId);
        $product = $this->createProduct('Winstrol 50mg', 40.00, 10);

        $item = $this->cartService->addItem($cart, $product, null, 1, null, $sessionId);

        // Update quantity to 3
        $updated = $this->cartService->updateQuantity($item, 3, null, $sessionId);
        $this->assertEquals(3, $updated->quantity);

        // Update quantity to 0 removes item
        $removed = $this->cartService->updateQuantity($item, 0, null, $sessionId);
        $this->assertNull($removed);
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_cart_merge_combines_quantities_and_caps_at_available_inventory(): void
    {
        $product = $this->createProduct('Deca Durabolin', 60.00, 5); // Max stock is 5

        $guestSessionId = 'guest_sess_' . uniqid();
        $guestCart = $this->cartService->getOrCreateCart(user: null, sessionId: $guestSessionId);
        $this->cartService->addItem($guestCart, $product, null, 3, null, $guestSessionId);

        $user = User::create([
            'name' => 'John Customer',
            'email' => 'john@customer.test',
            'password' => bcrypt('Secret123!'),
            'role' => 'CUSTOMER',
        ]);

        $userCart = $this->cartService->getOrCreateCart(user: $user);
        $this->cartService->addItem($userCart, $product, null, 3, $user); // 3 already in user cart

        // Merging 3 (guest) + 3 (user) = 6, but max available stock is 5
        $mergedCart = $this->cartService->mergeGuestCart($guestCart, $user);

        $mergedItem = $mergedCart->items->firstWhere('product_id', $product->id);
        $this->assertNotNull($mergedItem);
        $this->assertEquals(5, $mergedItem->quantity, 'Merged quantity must be capped at max available inventory (5).');

        // Verify guest cart was deleted
        $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);
    }

    public function test_authenticated_cart_persists_on_the_server(): void
    {
        $user = User::create([
            'name' => 'Persisted Customer',
            'email' => 'persist@customer.test',
            'password' => bcrypt('Secret123!'),
            'role' => 'CUSTOMER',
        ]);

        $product = $this->createProduct('Sustanon 250', 42.00, 8);
        $cart = $this->cartService->getOrCreateCart(user: $user);
        $this->cartService->addItem($cart, $product, null, 2, $user);

        $reloaded = $this->cartService->getOrCreateCart(user: $user);
        $this->assertEquals($cart->id, $reloaded->id);
        $this->assertEquals(1, $reloaded->items()->count());
        $this->assertEquals(2, $reloaded->items()->first()->quantity);
    }

    public function test_negative_and_zero_add_quantities_are_rejected(): void
    {
        $sessionId = 'sess_' . uniqid();
        $cart = $this->cartService->getOrCreateCart(user: null, sessionId: $sessionId);
        $product = $this->createProduct('Anadrol 50mg', 38.00, 6);

        $this->expectException(\InvalidArgumentException::class);
        $this->cartService->addItem($cart, $product, null, 0, null, $sessionId);
    }

    public function test_unauthorized_user_cannot_mutate_another_customers_cart(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@customer.test',
            'password' => bcrypt('Secret123!'),
            'role' => 'CUSTOMER',
        ]);
        $intruder = User::create([
            'name' => 'Intruder',
            'email' => 'intruder@customer.test',
            'password' => bcrypt('Secret123!'),
            'role' => 'CUSTOMER',
        ]);

        $product = $this->createProduct('Masteron 100', 70.00, 5);
        $cart = $this->cartService->getOrCreateCart(user: $owner);
        $item = $this->cartService->addItem($cart, $product, null, 1, $owner);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->cartService->updateQuantity($item, 2, $intruder);
    }

    public function test_inactive_product_cannot_be_added_to_cart(): void
    {
        $sessionId = 'sess_' . uniqid();
        $cart = $this->cartService->getOrCreateCart(user: null, sessionId: $sessionId);
        $product = $this->createProduct('Draft Compound', 20.00, 5);
        $product->status = ProductStatus::DRAFT;
        $product->save();

        $this->expectException(\App\Exceptions\ProductNotPurchasableException::class);
        $this->cartService->addItem($cart, $product, null, 1, null, $sessionId);
    }
}
