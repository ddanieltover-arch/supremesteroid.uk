<?php

declare(strict_types=1);

use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\AnnouncementController;
use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CatalogueController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\ContentPageController;
use App\Http\Controllers\Customer\EmailVerificationController;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Customer\NewsletterController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\PasswordResetController;
use App\Http\Controllers\Customer\WishlistController;
use App\Http\Controllers\Internal\InventoryExpiryCronController;
use App\Http\Controllers\Internal\ReadinessController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/internal/cron/release-expired-inventory', InventoryExpiryCronController::class)
    ->middleware('throttle:12,1')
    ->name('internal.cron.inventory');

Route::get('/internal/readiness', ReadinessController::class)
    ->middleware('throttle:12,1')
    ->name('internal.readiness');

Route::get('/', HomeController::class)->name('home');
Route::post('/newsletter', NewsletterController::class)->middleware('throttle:8,1')->name('newsletter.subscribe');
Route::post('/announcement/dismiss', [AnnouncementController::class, 'dismiss'])->middleware('throttle:20,1')->name('announcement.dismiss');

Route::get('/design-system', function () {
    return Inertia::render('DesignSystem');
})->name('design-system');

Route::get('/shop', [CatalogueController::class, 'index'])->name('shop.index');
Route::get('/catalogue', [CatalogueController::class, 'index'])->name('catalogue.index');
Route::get('/search', [CatalogueController::class, 'search'])->name('search');
Route::get('/search/suggest', [CatalogueController::class, 'suggest'])->middleware('throttle:30,1')->name('search.suggest');
Route::get('/catalogue/cards', [CatalogueController::class, 'cards'])->middleware('throttle:30,1')->name('catalogue.cards');
Route::get('/compare', [CatalogueController::class, 'compare'])->name('catalogue.compare');
Route::get('/category/{slug}', [CatalogueController::class, 'category'])->name('category.show');
Route::get('/brand/{slug}', [CatalogueController::class, 'brand'])->name('brand.show');
Route::get('/product/{slug}/quick-view', [CatalogueController::class, 'quickView'])->middleware('throttle:30,1')->name('catalogue.quick-view');
Route::get('/product/{slug}', [CatalogueController::class, 'show'])->name('catalogue.show');
Route::post('/product/{slug}/notify', [CatalogueController::class, 'notify'])->middleware('throttle:6,1')->name('catalogue.notify');

Route::middleware(['auth', 'role:MANAGER,SUPER_ADMIN'])->group(function () {
    Route::get('/admin/catalogue/preview/{slug}', [CatalogueController::class, 'preview'])
        ->name('admin.catalogue.preview');
});
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'addItem'])->middleware('throttle:60,1')->name('cart.items.add');
Route::patch('/cart/items/{id}', [CartController::class, 'updateQuantity'])->middleware('throttle:60,1')->name('cart.items.update');
Route::delete('/cart/items/{id}', [CartController::class, 'removeItem'])->middleware('throttle:60,1')->name('cart.items.remove');
Route::delete('/cart', [CartController::class, 'clear'])->middleware('throttle:60,1')->name('cart.clear');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:8,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:8,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout/buy-now', [CheckoutController::class, 'buyNow'])->middleware('throttle:30,1')->name('checkout.buy-now');
Route::post('/checkout/details', [CheckoutController::class, 'saveDetails'])->middleware('throttle:30,1')->name('checkout.details.save');
Route::get('/checkout/delivery', [CheckoutController::class, 'delivery'])->name('checkout.delivery');
Route::post('/checkout/delivery', [CheckoutController::class, 'saveDelivery'])->middleware('throttle:30,1')->name('checkout.delivery.save');
Route::get('/checkout/payment', [CheckoutController::class, 'payment'])->name('checkout.payment');
Route::post('/checkout/payment', [CheckoutController::class, 'savePayment'])->middleware('throttle:30,1')->name('checkout.payment.save');
Route::post('/checkout/acknowledge', [CheckoutController::class, 'acknowledge'])->middleware('throttle:30,1')->name('checkout.acknowledge');
Route::get('/checkout/confirmation', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
Route::post('/checkout/prepare', [CheckoutController::class, 'prepare'])->middleware('throttle:30,1')->name('checkout.prepare');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:30,1')->name('checkout.store');

Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
Route::get('/orders/{orderId}/payments/{paymentId}/proof', [OrderController::class, 'downloadPaymentProof'])->name('orders.payment.proof.download');
Route::post('/orders/{orderId}/payments/{paymentId}/proof', [OrderController::class, 'submitPaymentProof'])->middleware('throttle:10,1')->name('orders.payment.proof');
Route::post('/orders/{orderId}/payments/{paymentId}/proof/bank', [OrderController::class, 'submitBankTransferProof'])->middleware('throttle:10,1')->name('orders.payment.proof.bank');
Route::post('/orders/{orderId}/payments/{paymentId}/proof/crypto', [OrderController::class, 'submitCryptoProof'])->middleware('throttle:10,1')->name('orders.payment.proof.crypto');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');

    Route::get('/account', [AccountController::class, 'profile'])->name('account.profile');
    Route::patch('/account', [AccountController::class, 'updateProfile'])->name('account.profile.update');
    Route::get('/account/addresses', [AccountController::class, 'addresses'])->name('account.addresses');
    Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
    Route::patch('/account/addresses/{id}', [AccountController::class, 'updateAddress'])->name('account.addresses.update');
    Route::delete('/account/addresses/{id}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');
    Route::get('/account/wishlist', [WishlistController::class, 'index'])->name('account.wishlist');
    Route::post('/wishlist', [WishlistController::class, 'toggle'])->middleware('throttle:30,1')->name('wishlist.toggle');
    Route::post('/product/{slug}/reviews', [CatalogueController::class, 'storeReview'])->name('catalogue.reviews.store');

    Route::get('/account/orders', [OrderController::class, 'index'])->name('account.orders');
    Route::get('/account/orders/{id}', [OrderController::class, 'show'])->name('account.orders.show');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
});

use App\Http\Controllers\Customer\BlogController;

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/{slug}', function (\Illuminate\Http\Request $request, string $slug) {
    // If it's a blog post accessed at root URL (as per reference site structure), redirect/forward to /blog/{slug}
    if (\App\Models\BlogPost::where('slug', $slug)->where('is_published', true)->exists()) {
        return redirect()->route('blog.show', ['slug' => $slug]);
    }

    return app(\App\Http\Controllers\Customer\ContentPageController::class)($request, $slug, app(\App\Services\Catalogue\SeoService::class));
})->where('slug', '[a-zA-Z0-9\-_]+')->name('content.show');

