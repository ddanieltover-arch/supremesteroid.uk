<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportMatchStatus;
use App\Enums\ImportWorkflowStatus;
use App\Enums\ProductStatus;
use App\Models\CatalogueImportBatch;
use App\Models\CatalogueImportImage;
use App\Models\CatalogueImportRecord;
use App\Models\Product;
use App\Services\Catalogue\CatalogueImportService;
use App\Services\Catalogue\Import\ArrayCatalogueSourceReader;
use App\Services\Catalogue\Import\CatalogueImportOrchestrator;
use App\Services\Catalogue\Import\JsonCatalogueSourceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class CatalogueImportOnboardingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_dry_run_reports_without_mutating_catalogue(): void
    {
        $existing = $this->makeProduct(['name' => 'Known Support Formula', 'sku' => 'KNOWN-1']);
        $beforeProducts = Product::query()->count();
        $beforeImports = CatalogueImportRecord::query()->count();

        $reader = new ArrayCatalogueSourceReader([
            [
                'name' => 'Vitamin C Tablets',
                'sku' => 'DRY-VITC',
                'brand' => 'North Harbour',
                'category' => 'Vitamins',
                'price' => 9.99,
                'classification' => 'OTC_CONSUMER',
            ],
            [
                'name' => 'Copy Exact',
                'sku' => 'KNOWN-1',
                'price' => 5,
            ],
            [
                'name' => 'Known Support Formula',
                'sku' => 'KNOWN-POSSIBLE',
                'brand' => $existing->brand->name,
                'price' => 12,
            ],
            [
                'name' => 'Anabolic Research Reagent',
                'sku' => 'HOLD-1',
                'price' => 1,
                'requires_compliance_review' => true,
            ],
        ], 'unit-dry-run', 'array');

        $report = app(CatalogueImportOrchestrator::class)->import($reader, dryRun: true, batchNumber: '2099-001');

        $this->assertTrue($report->dryRun);
        $this->assertSame(4, $report->sourceRecords);
        $this->assertSame(2, $report->new);
        $this->assertSame(1, $report->possibleDuplicates);
        $this->assertSame(1, $report->exactMatches);
        $this->assertSame(1, $report->complianceReview);
        $this->assertSame($beforeProducts, Product::query()->count());
        $this->assertSame($beforeImports, CatalogueImportRecord::query()->count());
        $this->assertSame(0, CatalogueImportBatch::query()->count());
    }

    public function test_sample_staging_creates_review_records_and_never_publishes(): void
    {
        $path = base_path('storage/app/catalogue-import/qa-sample.json');
        $this->assertFileExists($path);

        $report = app(CatalogueImportOrchestrator::class)->import(
            new JsonCatalogueSourceReader($path, 'qa-sample'),
            dryRun: false,
            batchNumber: '2099-010'
        );

        $this->assertFalse($report->dryRun);
        $this->assertSame(20, $report->sourceRecords);
        $this->assertGreaterThan(0, $report->new);
        $this->assertGreaterThan(0, $report->complianceReview);

        $this->assertDatabaseHas('catalogue_import_batches', [
            'batch_number' => '2099-010',
            'is_dry_run' => false,
            'source_records' => 20,
        ]);

        $wheyVariantImage = CatalogueImportImage::query()
            ->where('source_variant_sku', 'QA-IMP-WHEY-900-VAN')
            ->first();
        $this->assertNotNull($wheyVariantImage);
        $this->assertNotNull($wheyVariantImage->product_variant_id);
        $this->assertNotNull($wheyVariantImage->normalized_variant_candidate);
        $held = CatalogueImportRecord::query()
            ->where('source_product_reference', 'QA-IMP-HOLD-01')
            ->first();
        $this->assertNotNull($held);
        $this->assertTrue($held->requires_compliance_review);
        $this->assertSame(ImportWorkflowStatus::COMPLIANCE_REVIEW, $held->workflow_status);
        $this->assertNull($held->classification_status);
        $this->assertNotNull($held->product);
        $this->assertSame(ProductStatus::COMPLIANCE_REVIEW, $held->product->status);
        $this->assertNull($held->product->description);
        $this->assertSame(0, Product::query()->purchasable()->whereKey($held->product_id)->count());

        $images = CatalogueImportImage::query()->count();
        $this->assertGreaterThan(0, $images);

        $this->get('/shop')->assertOk();
        $this->assertSame(
            0,
            Product::query()->purchasable()->where('sku', 'like', 'QA-IMP-%')->count()
        );
    }

    public function test_artisan_dry_run_sample_command(): void
    {
        $this->makeProduct(['sku' => 'SEED-1']);

        $exit = Artisan::call('catalogue:import', [
            '--sample' => true,
            '--dry-run' => true,
            '--batch' => '2099-020',
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('Source records:', $output);
        $this->assertStringContainsString('dry-run', strtolower($output));
        $this->assertSame(0, CatalogueImportRecord::query()->where('import_batch_id', '2099-020')->count());
    }

    public function test_normalization_and_variant_mapping(): void
    {
        $normalized = app(CatalogueImportService::class)->normalize([
            'name' => '  Whey Protein  ',
            'sku' => 'wp-1',
            'brand' => 'Harbour Labs',
            'category' => 'Sports Nutrition',
            'price' => '32.5',
            'pack_size' => '900g',
            'variants' => [
                ['sku' => 'wp-1-v', 'name' => 'Vanilla', 'attributes' => ['flavour' => 'Vanilla', 'dosage' => 'hidden']],
            ],
            'images' => ['https://example.test/a.png'],
        ], 'json');

        $this->assertSame('Whey Protein', $normalized['name']);
        $this->assertSame('whey protein', $normalized['normalized_name']);
        $this->assertSame('WP-1', $normalized['sku']);
        $this->assertSame('32.50', $normalized['price_amount']);
        $this->assertArrayNotHasKey('dosage', $normalized['variants'][0]['attributes']);
        $this->assertSame('Vanilla', $normalized['variants'][0]['attributes']['flavour']);
        $this->assertCount(1, $normalized['image_references']);
    }

    public function test_publication_marks_import_record_published_only_after_checklist(): void
    {
        $record = app(CatalogueImportService::class)->stage([
            'name' => 'Publishable Import',
            'sku' => 'PUB-IMP-1',
            'brand' => 'North Harbour',
            'category' => 'Vitamins',
            'price' => 10,
            'classification' => 'OTC_CONSUMER',
            'images' => ['https://example.test/pending-only.png'],
        ], 'batch-pub', 'json');

        $this->assertSame(ImportMatchStatus::NEW, $record->match_status);
        $this->assertNotSame(ImportWorkflowStatus::PUBLISHED, $record->workflow_status);

        $product = $record->product;
        $this->assertSame(0, Product::query()->purchasable()->whereKey($product->id)->count());
    }
}
