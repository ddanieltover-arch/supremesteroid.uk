<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Resources\ProductCardResource;
use App\Models\Product;
use App\Services\Account\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    public function __construct(
        protected WishlistService $wishlistService
    ) {}

    public function index(Request $request): Response
    {
        $wishlist = $this->wishlistService->defaultWishlist($request->user());
        $items = $wishlist->items()->with('product.brand', 'product.category', 'product.images', 'product.inventory')->get();

        $products = $items
            ->map(fn ($item) => $item->product)
            ->filter()
            ->values();

        return Inertia::render('Account/Wishlist', [
            'products' => ProductCardResource::collection($products)->resolve(),
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'string', 'exists:products,id'],
        ]);

        $product = Product::query()->purchasable()->findOrFail($validated['product_id']);
        $added = $this->wishlistService->toggle($request->user(), $product);

        return back()->with('success', $added ? 'Added to wishlist.' : 'Removed from wishlist.');
    }
}
