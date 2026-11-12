<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BlogPost;
use App\Models\Product;
use App\Services\Catalogue\CataloguePresentation;
use App\Services\Catalogue\ProductPurchaseEligibilityService;
use App\Services\Pricing\PricingService;
use App\Services\Storefront\AnswerCapsuleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        $pricing = app(PricingService::class)->calculateProductPrice($product);
        $eligibility = app(ProductPurchaseEligibilityService::class)
            ->checkEligibility($product, null, 1, true);
        $presentation = app(CataloguePresentation::class);
        $content = $product->content;

        $highlights = collect($content?->highlights ?? [])
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->take(6)
            ->values()
            ->all();

        $faqs = collect($content?->faqs ?? [])
            ->filter(fn ($item) => is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null))
            ->values()
            ->all();

        $keyDifferences = collect($content?->key_differences ?? [])
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->take(4)
            ->values()
            ->all();

        $relatedReading = $this->resolveRelatedReading($content?->related_reading ?? []);

        $approvedReviews = $product->relationLoaded('reviews')
            ? $product->reviews
            : $product->reviews()->with('user')->latest()->get();

        return [
            'id' => $product->id,
            'sku' => $product->isInternalSku() ? null : $product->sku,
            'has_genuine_sku' => ! $product->isInternalSku(),
            'internal_identifier' => $product->isInternalSku() ? $product->sku : null,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status?->value,
            'classification' => $product->classification?->value,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'ingredients' => $product->ingredients,
            'safety_guidelines' => $product->safety_guidelines,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'images' => $product->images
                ->filter(fn ($image) => $presentation->isUsableCatalogueImageUrl($image->publicUrl() ?? $image->url ?? null))
                ->map(function ($image) {
                    $url = $image->publicUrl();

                    return [
                        'id' => $image->id,
                        'url' => $url,
                        'alt_text' => $image->alt_text,
                        'caption' => $image->caption,
                        'thumbnail_url' => $url,
                        'is_primary' => (bool) $image->is_primary,
                        'variant_id' => $image->product_variant_id,
                    ];
                })->values()->all(),
            'variants' => $product->variants->map(function ($variant) use ($product, $presentation) {
                $variantPricing = app(PricingService::class)->calculateProductPrice($product, $variant);
                $variantEligibility = app(ProductPurchaseEligibilityService::class)
                    ->checkEligibility($product, $variant, 1, true);

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'effective_price' => $variantPricing['effective_price_amount'],
                    'compare_at_price' => $variantPricing['sale_price_amount'] ? $variantPricing['base_price_amount'] : null,
                    'is_on_sale' => $variantPricing['is_on_sale'],
                    'is_active' => (bool) $variant->is_active,
                    'is_purchasable' => $variantEligibility['is_eligible'],
                    'stock' => $presentation->stock($product, $variant),
                    'attributes' => $variant->attributes->map(fn ($attribute) => [
                        'name' => $attribute->attribute_name,
                        'value' => $attribute->attribute_value,
                    ])->values()->all(),
                ];
            })->values()->all(),
            'pricing' => [
                'currency' => $pricing['currency'],
                'base_price' => $pricing['base_price_amount'],
                'sale_price' => $pricing['sale_price_amount'],
                'effective_price' => $pricing['effective_price_amount'],
                'is_on_sale' => $pricing['is_on_sale'],
                'discount_percent' => $pricing['discount_percent'] ?? null,
            ],
            'available_quantity' => config('store.catalogue.expose_exact_stock') ? $product->inventory?->available() : null,
            'is_purchasable' => $eligibility['is_eligible'],
            'ineligibility_reasons' => $eligibility['reasons'] ?? [],
            'stock' => $presentation->stock($product),
            'badges' => array_values($presentation->badges($product)),
            'highlights' => $highlights,
            'answer_capsule' => app(AnswerCapsuleService::class)->product(
                $product,
                filled($content?->answer_capsule) ? trim((string) $content->answer_capsule) : null
            ),
            'content' => $content ? array_filter([
                'overview' => $content->overview,
                'manufacturer_information' => $content->manufacturer_information,
                'packaging_information' => $content->packaging_information,
                'documentation' => $content->documentation,
                'storage_information' => $content->storage_information,
                'shipping_information' => $content->shipping_information,
                'important_information' => $content->important_information,
                'why_choose' => $content->why_choose,
                'how_supplied' => $content->how_supplied,
                'where_it_fits' => $content->where_it_fits,
                'who_its_for' => $content->who_its_for,
                'what_you_get' => $content->what_you_get,
                'answer_capsule' => $content->answer_capsule,
            ], fn ($value) => filled($value)) : [],
            'key_differences' => $keyDifferences,
            'related_reading' => $relatedReading,
            'faqs' => $faqs,
            'reviews' => [
                'average' => $approvedReviews->avg('rating'),
                'count' => $approvedReviews->count(),
                'items' => $approvedReviews->map(fn ($review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'body' => $review->body,
                    'display_name' => $review->user?->name,
                    'verified_purchase' => (bool) $review->is_verified_purchase,
                    'created_at' => $review->created_at?->toDateString(),
                ])->values()->all(),
            ],
            'specifications' => collect([
                ['name' => 'Brand', 'value' => $product->brand?->name],
                ['name' => 'Category', 'value' => $product->category?->name],
                ['name' => 'SKU', 'value' => $product->sku],
                ['name' => 'Batch', 'value' => $product->batch_number],
            ])->merge(
                $product->attributes
                    ->filter(fn ($attribute) => $attribute->is_public)
                    ->map(fn ($attribute) => [
                        'name' => $attribute->attribute_name,
                        'value' => $attribute->attribute_value,
                    ])
            )->filter(fn ($row) => filled($row['value'] ?? null))->values()->all(),
            'batch_number' => $product->batch_number,
            'lab_verification_reference' => $product->compliance_verified_at ? $product->lab_verification_reference : null,
            'allow_stock_notification' => (bool) config('store.catalogue.allow_stock_notifications', true),
        ];
    }

    /**
     * @param  mixed  $raw
     * @return list<array{title: string, slug: string, url: string}>
     */
    protected function resolveRelatedReading(mixed $raw): array
    {
        $entries = collect(is_array($raw) ? $raw : [])
            ->map(function ($item) {
                if (! is_array($item)) {
                    return null;
                }

                $slug = trim((string) ($item['slug'] ?? ''));
                $title = trim((string) ($item['title'] ?? ''));

                if ($slug === '') {
                    return null;
                }

                return [
                    'slug' => $slug,
                    'title' => $title,
                ];
            })
            ->filter()
            ->take(3)
            ->values();

        if ($entries->isEmpty()) {
            return [];
        }

        $slugs = $entries->pluck('slug')->all();
        $published = BlogPost::query()
            ->where('is_published', true)
            ->whereIn('slug', $slugs)
            ->get(['slug', 'title'])
            ->keyBy('slug');

        return $entries
            ->map(function (array $item) use ($published) {
                $post = $published->get($item['slug']);
                if (! $post) {
                    return null;
                }

                return [
                    'title' => $item['title'] !== '' ? $item['title'] : (string) $post->title,
                    'slug' => (string) $post->slug,
                    'url' => URL::to('/blog/'.$post->slug),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
