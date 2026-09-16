<?php

declare(strict_types=1);

use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CatalogueController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\EmailVerificationController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\PasswordResetController;
use App\Http\Controllers\Customer\WishlistController;
use App\Http\Controllers\Internal\InventoryExpiryCronController;
use App\Http\Controllers\Internal\ReadinessController;
use App\Http\Resources\ProductCardResource;
use App\Models\Product;
use App\Services\Catalogue\SeoService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/internal/cron/release-expired-inventory', InventoryExpiryCronController::class)
    ->middleware('throttle:12,1')
    ->name('internal.cron.inventory');

Route::get('/internal/readiness', ReadinessController::class)
    ->middleware('throttle:12,1')
    ->name('internal.readiness');

Route::get('/', function () {
    $featured = Product::query()
        ->purchasable()
        ->with(['brand', 'category', 'images', 'inventory'])
        ->orderByDesc('is_featured')
        ->limit(8)
        ->get();

    return Inertia::render('Home', [
        'featuredProducts' => ProductCardResource::collection($featured)->resolve(),
        'seo' => app(SeoService::class)->forPage(
            request(),
            config('store.name', 'Supreme Steroids'),
            'Lawful sports nutrition, wellness, and certified research formulations.'
        ),
    ]);
})->name('home');

Route::get('/design-system', function () {
    return Inertia::render('DesignSystem');
})->name('design-system');

Route::get('/shop', [CatalogueController::class, 'index'])->name('shop.index');
Route::get('/catalogue', [CatalogueController::class, 'index'])->name('catalogue.index');
Route::get('/search', [CatalogueController::class, 'search'])->name('search');
Route::get('/category/{slug}', [CatalogueController::class, 'category'])->name('category.show');
Route::get('/brand/{slug}', [CatalogueController::class, 'brand'])->name('brand.show');
Route::get('/product/{slug}', [CatalogueController::class, 'show'])->name('catalogue.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.items.add');
Route::patch('/cart/items/{id}', [CartController::class, 'updateQuantity'])->name('cart.items.update');
Route::delete('/cart/items/{id}', [CartController::class, 'removeItem'])->name('cart.items.remove');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('/account', [AccountController::class, 'profile'])->name('account.profile');
    Route::patch('/account', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::get('/account/addresses', [AccountController::class, 'addresses'])->name('account.addresses');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
    Route::patch('/account/addresses/{id}', [AccountController::class, 'updateAddress'])->name('account.addresses.update');
    Route::delete('/account/addresses/{id}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');
    Route::get('/account/wishlist', [WishlistController::class, 'index'])->name('account.wishlist');
    Route::post('/wishlist', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/prepare', [CheckoutController::class, 'prepare'])->name('checkout.prepare');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::get('/account/orders', [OrderController::class, 'index'])->name('account.orders');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{orderId}/payments/{paymentId}/proof', [OrderController::class, 'downloadPaymentProof'])->name('orders.payment.proof.download');
    Route::post('/orders/{orderId}/payments/{paymentId}/proof', [OrderController::class, 'submitPaymentProof'])->name('orders.payment.proof');
    Route::post('/orders/{orderId}/payments/{paymentId}/proof/bank', [OrderController::class, 'submitBankTransferProof'])->name('orders.payment.proof.bank');
    Route::post('/orders/{orderId}/payments/{paymentId}/proof/crypto', [OrderController::class, 'submitCryptoProof'])->name('orders.payment.proof.crypto');
});
