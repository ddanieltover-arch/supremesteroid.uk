<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\ProductContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;
use App\Http\Resources\ProductDetailResource;

class ProductGuidanceBackfillTest extends TestCase
{
    use CreatesCatalogueFixtures;
    use RefreshDatabase;

    public function test_backfill_fills_empty_guidance_without_overwriting(): void
    {
        $product = $this->makeProduct([
            'name' => 'Peptide Recovery Guide Product',
            'short_description' => 'Commercial listing.',
        ]);

        ProductContent::query()->create([
            'product_id' => $product->id,
            'overview' => 'Existing overview',
            'highlights' => ['Existing highlight'],
            'why_choose' => 'Keep this admin why',
            'how_supplied' => null,
            'where_it_fits' => null,
            'who_its_for' => null,
            'what_you_get' => null,
            'key_differences' => [],
            'answer_capsule' => null,
            'related_reading' => [],
        ]);

        $author = $this->makeAdmin('guidance-backfill-author@test.com');
        BlogPost::query()->create([
            'author_id' => $author->id,
            'title' => 'Peptide Recovery Basics',
            'slug' => 'peptide-recovery-basics',
            'excerpt' => 'Overview',
            'content' => 'Educational article.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->artisan('catalogue:backfill-product-guidance', ['--sku' => $product->sku])
            ->assertSuccessful();

        $content = $product->fresh('content')->content;
        $this->assertSame('Keep this admin why', $content->why_choose);
        $this->assertNotEmpty($content->how_supplied);
        $this->assertNotEmpty($content->where_it_fits);
        $this->assertNotEmpty($content->who_its_for);
        $this->assertNotEmpty($content->what_you_get);
        $this->assertNotEmpty($content->answer_capsule);
        $this->assertNotEmpty($content->key_differences);
        $this->assertNotEmpty($content->related_reading);
        $this->assertSame('peptide-recovery-basics', $content->related_reading[0]['slug'] ?? null);
    }

    public function test_product_detail_resource_exposes_guidance_fields(): void
    {
        $product = $this->makeProduct(['name' => 'Guidance Visible Product']);
        $product->load(['brand', 'category', 'attributes', 'images', 'variants', 'inventory']);

        ProductContent::query()->create([
            'product_id' => $product->id,
            'why_choose' => 'Clear brand specification.',
            'how_supplied' => 'Listed with verified packaging.',
            'where_it_fits' => 'Sits in the catalogue category.',
            'who_its_for' => 'Catalogue shoppers comparing listings.',
            'what_you_get' => 'Pack size as published.',
            'key_differences' => ['Brand of record listed'],
            'answer_capsule' => 'A commercial catalogue listing with published specs.',
            'related_reading' => [
                ['slug' => 'missing-post', 'title' => 'Missing'],
            ],
            'faqs' => [
                ['question' => 'Is dosing included?', 'answer' => 'No. This page shows commercial specifications only.'],
            ],
        ]);

        $author = $this->makeAdmin('guidance-resource-author@test.com');
        BlogPost::query()->create([
            'author_id' => $author->id,
            'title' => 'Visible Guide',
            'slug' => 'visible-guide',
            'excerpt' => 'Overview',
            'content' => 'Body',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $product->content->update([
            'related_reading' => [
                ['slug' => 'visible-guide', 'title' => 'Visible Guide'],
                ['slug' => 'missing-post', 'title' => 'Missing'],
            ],
        ]);

        $product->load(['content', 'reviews', 'attributes', 'brand', 'category', 'images', 'variants.attributes', 'inventory']);

        $payload = (new ProductDetailResource($product))->toArray(Request::create('/product/'.$product->slug, 'GET'));

        $this->assertSame('A commercial catalogue listing with published specs.', $payload['answer_capsule']);
        $this->assertSame('Clear brand specification.', $payload['content']['why_choose']);
        $this->assertSame(['Brand of record listed'], $payload['key_differences']);
        $this->assertCount(1, $payload['related_reading']);
        $this->assertSame('visible-guide', $payload['related_reading'][0]['slug']);
        $this->assertSame('Is dosing included?', $payload['faqs'][0]['question']);
    }

    public function test_product_show_includes_guidance_in_inertia_props(): void
    {
        $product = $this->makeProduct(['name' => 'Show Guidance Product']);

        ProductContent::query()->create([
            'product_id' => $product->id,
            'why_choose' => 'Why choose this listing.',
            'how_supplied' => 'How it is supplied.',
            'where_it_fits' => 'Where it fits in the catalogue.',
            'answer_capsule' => 'Quick answer for this product.',
            'key_differences' => ['Pack size published'],
        ]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Catalogue/Show')
                ->where('product.answer_capsule', 'Quick answer for this product.')
                ->where('product.content.why_choose', 'Why choose this listing.')
                ->where('product.key_differences.0', 'Pack size published'));
    }
}
