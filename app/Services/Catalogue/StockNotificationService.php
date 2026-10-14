<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Models\Product;
use App\Models\StockNotification;
use App\Models\User;

class StockNotificationService
{
    public function subscribe(Product $product, string $email, ?User $user = null, ?string $variantId = null): StockNotification
    {
        return StockNotification::query()->firstOrCreate(
            [
                'email' => strtolower(trim($email)),
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
            ],
            [
                'user_id' => $user?->id,
            ]
        );
    }
}
