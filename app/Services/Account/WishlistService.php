<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Models\WishlistItem;

class WishlistService
{
    public function defaultWishlist(User $user): Wishlist
    {
        return Wishlist::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['name' => 'My Wishlist']
        );
    }

    public function productIds(User $user): array
    {
        $wishlist = Wishlist::query()->where('user_id', $user->id)->first();

        if (! $wishlist) {
            return [];
        }

        return $wishlist->items()->pluck('product_id')->all();
    }

    public function toggle(User $user, Product $product): bool
    {
        $wishlist = $this->defaultWishlist($user);
        $existing = WishlistItem::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
        ]);

        return true;
    }
}
