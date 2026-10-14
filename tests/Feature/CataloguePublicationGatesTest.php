<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Catalogue\ApproveCatalogueImageAction;
use App\Enums\ImportImageStatus;
use App\Enums\ImportWorkflowStatus;
use App\Enums\ProductRelationType;
use App\Enums\ProductStatus;
use App\Exceptions\ProductNotPurchasableException;
use App\Models\CatalogueImportImage;
use App\Models\CatalogueImportRecord;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRelation;
use App\Models\StoredFile;
use App\Services\Catalogue\CataloguePublicationChecklistService;
use App\Services\Catalogue\CataloguePublicationService;
use App\Services\Catalogue\RelatedProductService;
use App\Services\Catalogue\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class CataloguePublicationGatesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_media_approval_copies_to_object_storage_idempotently(): void
    {
        Storage::fake((string) config('filesystems.cloud', 'local'));

        $admin = $this->makeAdmin('media-admin@test.com');
        $product = $this->makeProduct([
            'status' => ProductStatus::DRAFT,
            'name' => 'QA Vitamin C',
            'sku' => 'QA-MEDIA-1',
            'slug' => 'qa-media-1',
            'description' => 'Lawful vitamin C tablets.',
            'short_description' => 'Vitamin C tablets.',
        ]);

        $record = CatalogueImportRecord::query()->create([
            'import_batch_id' => '2099-media',
            'source_name' => 'qa-fixture',
            'source_product_reference' => 'QA-MEDIA-1',
            'workflow_status' => ImportWorkflowStatus::MEDIA_REVIEW,
            'product_id' => $product->id,
            'imported_at' => now(),
        ]);

        $fixture = $this->makePngFixture();
        $image = CatalogueImportImage::query()->create([
            'catalogue_import_record_id' => $record->id,
            'source_url' => $fixture,
            'source_name' => 'qa-fixture',
            'alt_text' => 'Vitamin C bottle',
            'sort_order' => 0,
            'is_primary' => true,
            'status' => ImportImageStatus::PENDING,
        ]);

        $action = app(ApproveCatalogueImageAction::class);
        $action->approve($image, $admin);
        $this->assertSame(ImportImageStatus::APPROVED, $image->fresh()->status);

        $first = $action->fulfill($image->fresh(), $admin);
        $this->assertNotNull($first->product_image_id);
        $this->assertNotNull($first->copied_at);
        $this->assertNull($first->transfer_failure_reason);
        $this->assertSame(1, ProductImage::query()->where('product_id', $product->id)->count());
        $this->assertSame(1, StoredFile::query()->count());

        $second = $action->fulfill($image->fresh(), $admin);
        $this->assertSame($first->product_image_id, $second->product_image_id);
        $this->assertSame(1, ProductImage::query()->where('product_id', $product->id)->count());
    }

    public function test_media_transfer_failure_does_not_create_product_image(): void
    {
        $admin = $this->makeAdmin('media-fail@test.com');
        $product = $this->makeProduct(['status' => ProductStatus::DRAFT, 'sku' => 'QA-MEDIA-FAIL', 'slug' => 'qa-media-fail']);
        $record = CatalogueImportRecord::query()->create([
            'import_batch_id' => '2099-media-fail',
            'source_name' => 'qa-fixture',
            'source_product_reference' => 'QA-MEDIA-FAIL',
            'workflow_status' => ImportWorkflowStatus::MEDIA_REVIEW,
            'product_id' => $product->id,
            'imported_at' => now(),
        ]);

        $image = CatalogueImportImage::query()->create([
            'catalogue_import_record_id' => $record->id,
            'source_url' => storage_path('app/does-not-exist-image.png'),
            'source_name' => 'qa-fixture',
            'alt_text' => 'Broken',
            'is_primary' => true,
            'status' => ImportImageStatus::APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        try {
            app(ApproveCatalogueImageAction::class)->fulfill($image, $admin);
            $this->fail('Expected transfer failure');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Image transfer failed', $exception->getMessage());
        }

        $fresh = $image->fresh();
        $this->assertNull($fresh->product_image_id);
        $this->assertNull($fresh->copied_at);
        $this->assertNotNull($fresh->transfer_failure_reason);
        $this->assertSame(0, ProductImage::query()->where('product_id', $product->id)->count());
    }

    public function test_publication_checklist_blocks_demo_compliance_and_seo(): void
    {
        $checklist = app(CataloguePublicationChecklistService::class);

        $demo = $this->makeProduct([
            'is_demo' => true,
            'status' => ProductStatus::DRAFT,
            'description' => 'Demo copy',
            'short_description' => 'Demo',
            'sku' => 'DEMO-GATE',
            'slug' => 'demo-gate',
        ]);
        ProductImage::query()->create([
            'product_id' => $demo->id,
            'url' => '/assets/demo/product.jpg',
            'alt_text' => 'Demo',
            'is_primary' => true,
        ]);
        app(SeoService::class)->ensureDefaultsForProduct($demo->fresh(['brand', 'category', 'images']));
        $demoResult = $checklist->evaluate($demo->fresh());
        $this->assertSame('BLOCKED', $demoResult['status']);
        $this->assertFalse(collect($demoResult['items'])->firstWhere('key', 'demo')['passed']);

        $held = $this->makeProduct([
            'status' => ProductStatus::COMPLIANCE_REVIEW,
            'compliance_verified_at' => null,
            'description' => 'Held item',
            'short_description' => 'Held',
            'sku' => 'HOLD-GATE',
            'slug' => 'hold-gate',
        ]);
        ProductImage::query()->create([
            'product_id' => $held->id,
            'url' => '/assets/demo/hold.jpg',
            'alt_text' => 'Held',
            'is_primary' => true,
        ]);
        $this->expectException(ProductNotPurchasableException::class);
        app(CataloguePublicationService::class)->publish($held);
    }

    public function test_publish_requires_checklist_and_invalidates_catalogue_caches(): void
    {
        Cache::put('catalogue.navigation', ['x'], 60);
        Cache::put('catalogue.facets', ['y'], 60);

        $product = $this->makeProduct([
            'status' => ProductStatus::DRAFT,
            'description' => 'Eligible lawful wellness product.',
            'short_description' => 'Eligible summary.',
            'sku' => 'ELIG-GATE',
            'slug' => 'elig-gate',
            'compare_at_price_amount' => 29.99,
            'price_amount' => 19.99,
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/assets/demo/elig.jpg',
            'alt_text' => $product->name,
            'is_primary' => true,
        ]);

        $published = app(CataloguePublicationService::class)->publish($product->fresh());
        $this->assertSame(ProductStatus::ACTIVE, $published->status);
        $this->assertNotNull($published->seoMetadata?->meta_title);
        $this->assertNull(Cache::get('catalogue.navigation'));
        $this->assertNull(Cache::get('catalogue.facets'));
    }

    public function test_related_products_hide_when_target_becomes_inactive(): void
    {
        $a = $this->makeProduct(['name' => 'Related A', 'sku' => 'REL-A', 'slug' => 'rel-a']);
        $b = $this->makeProduct(['name' => 'Related B', 'sku' => 'REL-B', 'slug' => 'rel-b']);

        app(RelatedProductService::class)->attach($a, $b, ProductRelationType::RELATED);
        $this->assertTrue(app(RelatedProductService::class)->purchasableRelated($a)->contains('id', $b->id));

        $b->update(['status' => ProductStatus::SUSPENDED]);
        $this->assertFalse(app(RelatedProductService::class)->purchasableRelated($a->fresh())->contains('id', $b->id));
        $this->assertTrue(ProductRelation::query()
            ->where('product_id', $a->id)
            ->where('related_product_id', $b->id)
            ->exists());
    }

    public function test_demo_and_compliance_products_are_not_publicly_discoverable(): void
    {
        $demo = $this->makeProduct([
            'is_demo' => true,
            'sku' => 'DEMO-DISC',
            'slug' => 'demo-disc',
            'name' => 'Demo Discover',
        ]);
        $held = $this->makeProduct([
            'status' => ProductStatus::COMPLIANCE_REVIEW,
            'compliance_verified_at' => null,
            'sku' => 'HOLD-DISC',
            'slug' => 'hold-disc',
            'name' => 'Held Discover',
        ]);

        $this->assertSame(0, Product::query()->purchasable()->whereKey([$demo->id, $held->id])->count());
        $this->get('/shop')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->get('/product/'.$demo->slug)->assertNotFound();
        $this->get('/product/'.$held->slug)->assertNotFound();
        $this->get('/search?q=Demo')->assertOk();
    }

    public function test_admin_preview_is_isolated_from_cart(): void
    {
        $admin = $this->makeAdmin('preview-admin@test.com');
        $product = $this->makeProduct([
            'status' => ProductStatus::DRAFT,
            'sku' => 'PREV-1',
            'slug' => 'prev-1',
            'description' => 'Draft preview product',
        ]);
        $this->makeVariant($product);

        $this->actingAs($admin)
            ->get(route('admin.catalogue.preview', $product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Show')
                ->where('preview', true)
                ->where('product.is_purchasable', false)
                ->where('product.is_preview', true)
            );

        $this->actingAs($admin)
            ->post('/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertSessionHasErrors()
            ->assertRedirect();
    }

    public function test_seo_defaults_and_cli_publication_report(): void
    {
        $product = $this->makeProduct([
            'status' => ProductStatus::DRAFT,
            'name' => 'North Harbour Zinc',
            'sku' => 'SEO-ZINC',
            'slug' => 'seo-zinc',
            'description' => 'Zinc tablets.',
            'short_description' => 'Zinc tablets.',
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/assets/demo/zinc.jpg',
            'alt_text' => 'Zinc',
            'is_primary' => true,
        ]);

        $seo = app(SeoService::class)->ensureDefaultsForProduct($product->fresh(['brand', 'category', 'images']));
        $this->assertStringContainsString('North Harbour Zinc', (string) $seo->meta_title);
        $this->assertStringContainsString('Supreme Steroids', (string) $seo->meta_title);
        $this->assertNotEmpty($seo->meta_description);
        $this->assertNotEmpty($seo->canonical_url);

        $this->artisan('catalogue:check-publication', ['product' => 'SEO-ZINC'])
            ->assertSuccessful();

        $this->artisan('catalogue:publication-report', ['--sku' => 'SEO-ZINC'])
            ->assertSuccessful()
            ->expectsOutputToContain('Publication: READY');
    }

    public function test_image_not_required_override_allows_publication_without_media(): void
    {
        $product = $this->makeProduct([
            'status' => ProductStatus::DRAFT,
            'image_not_required' => true,
            'description' => 'Text-only lawful listing.',
            'short_description' => 'Text only.',
            'sku' => 'NOIMG-1',
            'slug' => 'noimg-1',
        ]);

        $published = app(CataloguePublicationService::class)->publish($product->fresh());
        $this->assertSame(ProductStatus::ACTIVE, $published->status);
    }

    protected function makePngFixture(): string
    {
        $path = storage_path('app/testing/qa-product.png');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        file_put_contents($path, $png);

        return $path;
    }
}
