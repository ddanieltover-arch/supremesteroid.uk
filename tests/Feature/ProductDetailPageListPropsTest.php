<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class ProductDetailPageListPropsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_product_page_returns_list_shaped_images_and_variants_when_empty(): void
    {
        $product = $this->makeProduct(['name' => 'List Props Empty Media']);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Show')
                ->where('product.sku', $product->sku)
                ->where('product.images', [])
                ->where('product.variants', [])
                ->where('product.badges', fn (Collection $badges) => array_is_list($badges->all()))
                ->where('product.highlights', fn (Collection $highlights) => array_is_list($highlights->all()))
                ->where('product.structured_data', fn (Collection $schemas) => array_is_list($schemas->all()) && $schemas->isNotEmpty())
                ->where('product.breadcrumbs', fn (Collection $crumbs) => array_is_list($crumbs->all()) && $crumbs->isNotEmpty())
            );
    }

    public function test_product_page_keeps_variants_as_json_lists_when_present(): void
    {
        $product = $this->makeProduct(['name' => 'List Props With Variant']);
        $variant = $this->makeVariant($product, '50mg', '48.00', 12);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Show')
                ->has('product.variants', 1)
                ->where('product.variants.0.id', $variant->id)
                ->where('product.variants', fn (Collection $variants) => array_is_list($variants->all()))
                ->where('product.images', fn (Collection $images) => array_is_list($images->all()))
            );
    }

    public function test_quick_view_returns_list_shaped_images_and_variants(): void
    {
        $product = $this->makeProduct(['name' => 'Quick View List Props']);

        $this->getJson('/product/'.$product->slug.'/quick-view')
            ->assertOk()
            ->assertJsonPath('product.images', [])
            ->assertJsonPath('product.variants', [])
            ->assertJson(fn ($json) => $json
                ->whereType('product.images', 'array')
                ->whereType('product.variants', 'array')
                ->etc()
            );
    }
}
