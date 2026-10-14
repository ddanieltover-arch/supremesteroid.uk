<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Catalogue\ApproveCatalogueImageAction;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportImageStatus;
use App\Enums\ImportWorkflowStatus;
use App\Enums\ProductStatus;
use App\Models\CatalogueImportImage;
use App\Models\CatalogueImportRecord;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalogue\CatalogueOnboardingBatchService;
use App\Services\Catalogue\Import\CatalogueImportOrchestrator;
use App\Services\Catalogue\Import\JsonCatalogueSourceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class VariantMediaAndOnboardingBatchTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_product_and_variant_images_with_storefront_fallback(): void
    {
        $product = $this->makeProduct([
            'name' => 'Variant Gallery Product',
            'sku' => 'VAR-GAL-1',
            'slug' => 'var-gal-1',
        ]);
        $vanilla = $this->makeVariant($product, 'Vanilla', '32.00');
        $chocolate = $this->makeVariant($product, 'Chocolate', '32.00');

        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/assets/demo/product-level.jpg',
            'alt_text' => 'Product gallery',
            'is_primary' => true,
            'display_order' => 0,
            'product_variant_id' => null,
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/assets/demo/vanilla.jpg',
            'alt_text' => 'Vanilla',
            'is_primary' => true,
            'display_order' => 0,
            'product_variant_id' => $vanilla->id,
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/assets/demo/chocolate.jpg',
            'alt_text' => 'Chocolate',
            'is_primary' => true,
            'display_order' => 0,
            'product_variant_id' => $chocolate->id,
        ]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Show')
                ->has('product.images', 3)
                ->where('product.images.0.variant_id', null)
            );
    }

    public function test_cross_product_variant_assignment_is_rejected(): void
    {
        $productA = $this->makeProduct(['sku' => 'IMG-A', 'slug' => 'img-a']);
        $productB = $this->makeProduct(['sku' => 'IMG-B', 'slug' => 'img-b']);
        $foreignVariant = $this->makeVariant($productB, 'Foreign');

        $this->expectException(InvalidArgumentException::class);
        ProductImage::query()->create([
            'product_id' => $productA->id,
            'url' => '/assets/demo/bad.jpg',
            'alt_text' => 'Bad bind',
            'is_primary' => true,
            'product_variant_id' => $foreignVariant->id,
        ]);
    }

    public function test_variant_media_approval_is_idempotent_and_binds_variant(): void
    {
        Storage::fake((string) config('filesystems.cloud', 'local'));

        $admin = $this->makeAdmin('variant-media@test.com');
        $product = $this->makeProduct([
            'status' => ProductStatus::DRAFT,
            'sku' => 'VAR-MEDIA-1',
            'slug' => 'var-media-1',
            'description' => 'Lawful product with variants.',
            'short_description' => 'Variant media.',
        ]);
        $variant = $this->makeVariant($product, 'Blue');

        $record = CatalogueImportRecord::query()->create([
            'import_batch_id' => '2099-var-media',
            'source_name' => 'qa-fixture',
            'source_product_reference' => 'VAR-MEDIA-1',
            'workflow_status' => ImportWorkflowStatus::MEDIA_REVIEW,
            'product_id' => $product->id,
            'imported_at' => now(),
        ]);

        $fixture = $this->makePngFixture('variant-blue.png');
        $image = CatalogueImportImage::query()->create([
            'catalogue_import_record_id' => $record->id,
            'source_url' => $fixture,
            'source_name' => 'qa-fixture',
            'source_variant_sku' => $variant->sku,
            'source_variant_name' => 'Blue',
            'normalized_variant_candidate' => ['sku' => $variant->sku, 'name' => 'Blue'],
            'product_variant_id' => $variant->id,
            'alt_text' => 'Blue variant',
            'is_primary' => true,
            'status' => ImportImageStatus::PENDING,
        ]);

        $action = app(ApproveCatalogueImageAction::class);
        $action->approve($image, $admin);
        $first = $action->fulfill($image->fresh(), $admin);

        $this->assertNotNull($first->product_image_id);
        $productImage = ProductImage::query()->findOrFail($first->product_image_id);
        $this->assertSame($variant->id, $productImage->product_variant_id);
        $this->assertSame(1, ProductImage::query()->where('product_id', $product->id)->count());

        $second = $action->fulfill($image->fresh(), $admin);
        $this->assertSame($first->product_image_id, $second->product_image_id);
        $this->assertSame(1, ProductImage::query()->where('product_id', $product->id)->count());
    }

    public function test_fulfill_rejects_foreign_variant_binding(): void
    {
        Storage::fake((string) config('filesystems.cloud', 'local'));
        $admin = $this->makeAdmin('foreign-bind@test.com');
        $product = $this->makeProduct(['status' => ProductStatus::DRAFT, 'sku' => 'OWN-1', 'slug' => 'own-1']);
        $other = $this->makeProduct(['sku' => 'OTH-1', 'slug' => 'oth-1']);
        $foreign = $this->makeVariant($other, 'Foreign');

        $record = CatalogueImportRecord::query()->create([
            'import_batch_id' => '2099-foreign',
            'source_name' => 'qa',
            'source_product_reference' => 'OWN-1',
            'workflow_status' => ImportWorkflowStatus::MEDIA_REVIEW,
            'product_id' => $product->id,
            'imported_at' => now(),
        ]);

        $image = CatalogueImportImage::query()->create([
            'catalogue_import_record_id' => $record->id,
            'source_url' => $this->makePngFixture('foreign.png'),
            'source_name' => 'qa',
            'product_variant_id' => $foreign->id,
            'alt_text' => 'Foreign',
            'is_primary' => true,
            'status' => ImportImageStatus::APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        app(ApproveCatalogueImageAction::class)->fulfill($image, $admin);
    }

    public function test_twenty_record_qa_sample_batch_preview_blocks_held_products(): void
    {
        $path = base_path('storage/app/catalogue-import/qa-sample.json');
        $report = app(CatalogueImportOrchestrator::class)->import(
            new JsonCatalogueSourceReader($path, 'qa-sample'),
            dryRun: false,
            batchNumber: '2099-020',
            limit: 20,
        );

        $this->assertSame(20, $report->sourceRecords);
        $this->assertNotNull($report->batch);
        $this->assertSame(ImportBatchStatus::UNDER_REVIEW, $report->batch->fresh()->status);

        $preview = app(CatalogueOnboardingBatchService::class)->preview($report->batch->fresh());
        $this->assertCount(20, $preview);

        $held = collect($preview)->first(fn (array $row) => ($row['sku'] ?? '') === 'QA-IMP-HOLD-01');
        $this->assertNotNull($held);
        $this->assertSame('BLOCKED', $held['status']);
        $this->assertSame('HELD', $held['compliance']);

        $this->assertSame(
            0,
            Product::query()->purchasable()->where('sku', 'like', 'QA-IMP-%')->count()
        );

        $this->artisan('catalogue:batch-preview', ['batch' => '2099-020'])
            ->assertSuccessful()
            ->expectsOutputToContain('QA-IMP-HOLD-01');
    }

    protected function makePngFixture(string $name = 'qa-product.png'): string
    {
        $path = storage_path('app/testing/'.$name);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        file_put_contents($path, $png);

        return $path;
    }
}
