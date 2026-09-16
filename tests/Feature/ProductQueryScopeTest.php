<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class ProductQueryScopeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_public_scopes_hide_non_retail_statuses(): void
    {
        $active = $this->makeProduct(['name' => 'Active Item', 'status' => ProductStatus::ACTIVE]);
        $this->makeProduct(['name' => 'Draft Item', 'status' => ProductStatus::DRAFT, 'slug' => 'draft-item', 'sku' => 'DRAFT-1']);
        $this->makeProduct(['name' => 'Imported Item', 'status' => ProductStatus::IMPORTED, 'slug' => 'imported-item', 'sku' => 'IMP-1']);
        $this->makeProduct(['name' => 'Review Item', 'status' => ProductStatus::UNDER_REVIEW, 'slug' => 'review-item', 'sku' => 'REV-1']);
        $this->makeProduct(['name' => 'Suspended Item', 'status' => ProductStatus::SUSPENDED, 'slug' => 'suspended-item', 'sku' => 'SUS-1']);
        $this->makeProduct(['name' => 'Archived Item', 'status' => ProductStatus::ARCHIVED, 'slug' => 'archived-item', 'sku' => 'ARC-1']);

        $published = Product::query()->published()->pluck('id');
        $purchasable = Product::query()->purchasable()->pluck('id');
        $activeScope = Product::query()->active()->pluck('id');

        $this->assertTrue($published->contains($active->id));
        $this->assertEquals(1, $published->count());
        $this->assertTrue($purchasable->contains($active->id));
        $this->assertEquals(1, $purchasable->count());
        $this->assertTrue($activeScope->contains($active->id));
        $this->assertEquals(1, $activeScope->count());
    }

    public function test_in_stock_and_search_and_taxonomy_scopes(): void
    {
        $inStock = $this->makeProduct(['name' => 'Visible Cypionate', 'sku' => 'VIS-CYP-1'], 8);
        $out = $this->makeProduct(['name' => 'Empty Enanthate', 'sku' => 'EMPTY-E-1', 'slug' => 'empty-enanthate'], 0);

        $this->assertTrue(Product::query()->inStock()->pluck('id')->contains($inStock->id));
        $this->assertFalse(Product::query()->inStock()->pluck('id')->contains($out->id));

        $this->assertTrue(Product::query()->searchable('Visible')->pluck('id')->contains($inStock->id));
        $this->assertTrue(Product::query()->searchable('VIS-CYP')->pluck('id')->contains($inStock->id));
        $this->assertTrue(Product::query()->byCategory($inStock->category->slug)->pluck('id')->contains($inStock->id));
        $this->assertTrue(Product::query()->byBrand($inStock->brand->slug)->pluck('id')->contains($inStock->id));
    }

    public function test_storefront_catalogue_does_not_list_drafts(): void
    {
        $this->makeProduct(['name' => 'Live Product']);
        $this->makeProduct(['name' => 'Hidden Draft', 'status' => ProductStatus::DRAFT, 'sku' => 'HID-1', 'slug' => 'hidden-draft']);

        $this->get(route('catalogue.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Catalogue/Index')
                ->has('products.data', 1)
            );
    }

    public function test_unverified_products_are_not_purchasable(): void
    {
        $this->makeProduct([
            'name' => 'Unverified',
            'sku' => 'UNV-1',
            'slug' => 'unverified',
            'compliance_verified_at' => null,
            'classification' => ProductClassification::OTC_CONSUMER,
        ]);

        $this->assertSame(0, Product::query()->purchasable()->count());
    }
}
