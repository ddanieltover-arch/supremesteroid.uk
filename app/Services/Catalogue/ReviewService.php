<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Audit\AuditLogger;

class ReviewService
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function submit(Product $product, User $user, int $rating, ?string $title = null, ?string $body = null): Review
    {
        return Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'status' => ReviewStatus::PENDING,
            'is_verified_purchase' => $this->hasVerifiedPurchase($user, $product),
        ]);
    }

    public function hasVerifiedPurchase(User $user, Product $product): bool
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                OrderStatus::PAID,
                OrderStatus::PROCESSING,
                OrderStatus::SHIPPED,
                OrderStatus::DELIVERED,
            ])
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->exists();
    }

    public function moderate(Review $review, ReviewStatus $status, User $moderator): Review
    {
        $old = $review->status?->value;
        $review->status = $status;
        $review->moderated_by = $moderator->id;
        $review->moderated_at = now();
        $review->save();

        $this->auditLogger->log(
            eventType: 'review_moderated',
            auditable: $review,
            oldValues: ['status' => $old],
            newValues: ['status' => $status->value],
            reason: 'Review moderation.'
        );

        return $review->fresh();
    }
}
