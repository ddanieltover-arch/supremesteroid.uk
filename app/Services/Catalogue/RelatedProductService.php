<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\ProductRelationType;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class RelatedProductService
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * @return array{related: Collection<int, Product>, alternative: Collection<int, Product>, complementary: Collection<int, Product>}
     */
    public function grouped(Product $product, int $limit = 8): array
    {
        return [
            'related' => $this->purchasableRelated($product, ProductRelationType::RELATED, $limit),
            'alternative' => $this->purchasableRelated($product, ProductRelationType::ALTERNATIVE, $limit),
            'complementary' => $this->purchasableRelated($product, ProductRelationType::COMPLEMENTARY, $limit),
        ];
    }

    /**
     * @return Collection<int, Product>
     */
    public function purchasableRelated(Product $product, ?ProductRelationType $type = null, int $limit = 8): Collection
    {
        $query = $product->relations()
            ->with([
                'relatedProduct' => function ($relatedQuery): void {
                    $relatedQuery
                        ->with(['brand', 'category', 'images', 'inventory'])
                        ->withCount(['variants as variant_count' => fn ($variantQuery) => $variantQuery->where('is_active', true)]);
                },
            ])
            ->orderBy('display_order');

        if ($type !== null) {
            $query->where('relation_type', $type->value);
        }

        $related = $query->get()
            ->map(fn ($relation) => $relation->relatedProduct)
            ->filter(fn ($item) => $item !== null)
            ->unique('id')
            ->values();

        if ($related->isEmpty()) {
            return $related;
        }

        $purchasableIds = Product::query()
            ->purchasable()
            ->whereKey($related->modelKeys())
            ->pluck('id')
            ->all();

        return $related
            ->filter(fn (Product $item) => in_array($item->id, $purchasableIds, true))
            ->take($limit)
            ->values();
    }

    public function attach(Product $product, Product $related, ProductRelationType $type = ProductRelationType::RELATED): void
    {
        if ($product->id === $related->id) {
            return;
        }

        if ($product->is_demo || $related->is_demo) {
            throw new InvalidArgumentException('Demo products cannot participate in public relationships.');
        }

        $relation = $product->relations()->firstOrCreate(
            [
                'related_product_id' => $related->id,
                'relation_type' => $type->value,
            ],
            ['display_order' => (int) $product->relations()->max('display_order') + 1]
        );

        if ($relation->wasRecentlyCreated) {
            $this->auditLogger->log(
                eventType: 'product_relation_attached',
                auditable: $product,
                newValues: [
                    'related_product_id' => $related->id,
                    'relation_type' => $type->value,
                    'related_purchasable' => Product::query()->purchasable()->whereKey($related->id)->exists(),
                ],
                reason: 'Related product relationship created.'
            );
            Cache::forget('catalogue.related.'.$product->id);
        }
    }

    /**
     * @return Collection<int, Product>
     */
    public function purchasableCandidates(Product $exclude, int $limit = 100): Collection
    {
        return Product::query()
            ->purchasable()
            ->whereKeyNot($exclude->id)
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
