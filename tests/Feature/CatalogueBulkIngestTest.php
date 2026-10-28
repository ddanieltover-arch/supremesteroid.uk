<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BulkIngestRunStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportWorkflowStatus;
use App\Enums\ProductStatus;
use App\Models\CatalogueBulkIngestRun;
use App\Models\CatalogueImportRecord;
use App\Models\Product;
use App\Services\Catalogue\Import\CatalogueBulkIngestService;
use App\Services\Catalogue\Import\ResumableJsonCatalogueSourceReader;
use App\Services\Catalogue\Import\WooCommerceRestCatalogueSourceReader;
use App\Support\QualityAssuranceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class CatalogueBulkIngestTest extends TestCase
{
    use RefreshDatabase;

    protected string $feedPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feedPath = storage_path('app/testing/bulk-feed-'.uniqid().'.json');
        File::ensureDirectoryExists(dirname($this->feedPath));
    }

    protected function tearDown(): void
    {
        if (is_file($this->feedPath)) {
            @unlink($this->feedPath);
        }
        parent::tearDown();
    }

    public function test_woocommerce_reader_requires_credentials(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CATALOGUE_WC_CONSUMER_KEY');
        new WooCommerceRestCatalogueSourceReader(
            baseUrl: 'https://catalogue.test',
            consumerKey: '',
            consumerSecret: '',
        );
    }

    public function test_woocommerce_reader_paginates_and_maps_authorized_payloads(): void
    {
        $pages = [
            1 => [
                [
                    'id' => 11,
                    'name' => 'Vitamin C Tablets',
                    'slug' => 'vitamin-c-tablets',
                    'permalink' => 'https://catalogue.test/product/vitamin-c-tablets',
                    'sku' => 'VITC-100',
                    'price' => '9.50',
                    'regular_price' => '12.00',
                    'status' => 'publish',
                    'type' => 'simple',
                    'stock_status' => 'instock',
                    'categories' => [['name' => 'Vitamins']],
                    'brands' => [['name' => 'North Harbour']],
                    'images' => [['src' => 'https://catalogue.test/images/vitc.png', 'alt' => 'Vitamin C']],
                    'attributes' => [],
                ],
            ],
            2 => [
                [
                    'id' => 12,
                    'name' => 'Anavar 10mg',
                    'slug' => 'anavar-10mg',
                    'permalink' => 'https://catalogue.test/product/anavar-10mg',
                    'sku' => 'ANA-10',
                    'price' => '40.00',
                    'status' => 'publish',
                    'type' => 'simple',
                    'categories' => [['name' => 'Oral Steroids']],
                    'images' => [],
                    'attributes' => [],
                ],
            ],
        ];

        $reader = new WooCommerceRestCatalogueSourceReader(
            baseUrl: 'https://catalogue.test',
            consumerKey: 'ck_test',
            consumerSecret: 'cs_test',
            pageSize: 1,
            httpGet: function (string $url) use ($pages): string {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $page = (int) ($query['page'] ?? 1);
                $this->assertSame('ck_test', $query['consumer_key'] ?? null);
                if (! isset($pages[$page])) {
                    return '[]';
                }

                return json_encode($pages[$page], JSON_THROW_ON_ERROR);
            },
        );

        $records = iterator_to_array($reader->records());
        $this->assertCount(2, $records);
        $this->assertSame('Vitamin C Tablets', $records[0]['name']);
        $this->assertSame('VITC-100', $records[0]['sku']);
        $this->assertSame('wc-11', $records[0]['source_product_reference']);
        $this->assertSame('North Harbour', $records[0]['brand']);
        $stats = $reader->retrievalStats();
        $this->assertTrue($stats['exhausted']);
        $this->assertSame(2, $stats['retrieved']);
    }

    public function test_bulk_json_ingest_excludes_restricted_is_idempotent_and_resumable(): void
    {
        File::put($this->feedPath, json_encode([
            [
                'name' => 'Vitamin D3 Tablets',
                'sku' => 'VITD-30',
                'price' => '8.00',
                'currency' => 'GBP',
                'brand' => 'North Harbour',
                'category' => 'Vitamins',
                'source_product_reference' => 'vitamin-d3-tablets',
                'source_url' => 'https://catalogue.test/product/vitamin-d3-tablets',
                'images' => ['https://catalogue.test/images/vitd.png'],
            ],
            [
                'name' => 'Anavar 10mg Pharmaqo',
                'sku' => 'ANA-10',
                'price' => '40.00',
                'currency' => 'GBP',
                'category' => 'Oral Steroids',
                'source_product_reference' => 'anavar-10mg',
                'source_url' => 'https://catalogue.test/product/anavar-10mg',
            ],
            [
                'name' => 'Zinc Tablets',
                'sku' => null,
                'price' => null,
                'currency' => 'GBP',
                'brand' => 'North Harbour',
                'category' => 'Minerals',
                'source_product_reference' => 'zinc-tablets',
                'source_url' => 'https://catalogue.test/product/zinc-tablets',
            ],
        ], JSON_THROW_ON_ERROR));

        $service = app(CatalogueBulkIngestService::class);
        $first = $service->ingest(new ResumableJsonCatalogueSourceReader($this->feedPath, 'json-feed', 1), [
            'run_key' => 'bulk-test-resume',
            'dry_run' => false,
            'limit' => 1,
            'page_size' => 1,
        ]);

        $this->assertFalse($first->complete);
        $this->assertSame(1, $first->sourceRecordsRetrieved);
        $this->assertSame(1, $first->newStagingRecords);
        $this->assertSame(1, Product::query()->count());
        $run = CatalogueBulkIngestRun::query()->where('run_key', 'bulk-test-resume')->first();
        $this->assertNotNull($run);
        $this->assertSame(BulkIngestRunStatus::PAUSED, $run->status);
        $this->assertSame(1, (int) ($run->cursor['offset'] ?? 0));

        $second = $service->ingest(new ResumableJsonCatalogueSourceReader($this->feedPath, 'json-feed', 1), [
            'run_key' => 'bulk-test-resume',
            'dry_run' => false,
            'resume' => true,
            'limit' => 10,
            'page_size' => 1,
        ]);

        $this->assertSame(2, $second->sourceRecordsRetrieved);
        $this->assertContains('anavar-10mg', collect($second->rows)->pluck('reference')->all());
        $this->assertSame(1, $second->restrictedExcluded);
        $this->assertGreaterThanOrEqual(1, $second->missingSkus + $second->missingPrices);
        $this->assertTrue($second->complete);
        $this->assertSame(0, Product::query()->purchasable()->count());
        $this->assertSame(0, Product::query()->where('status', ProductStatus::ACTIVE)->count());
        $this->assertSame(
            0,
            CatalogueImportRecord::query()->where('source_product_reference', 'anavar-10mg')->whereNotNull('product_id')->count()
        );
        $this->assertSame(
            ImportWorkflowStatus::REJECTED,
            CatalogueImportRecord::query()->where('source_product_reference', 'anavar-10mg')->value('workflow_status')
        );

        $before = Product::query()->count();
        $rerun = $service->ingest(new ResumableJsonCatalogueSourceReader($this->feedPath, 'json-feed'), [
            'run_key' => 'bulk-test-idempotent',
            'dry_run' => false,
            'limit' => 10,
        ]);
        $this->assertSame($before, Product::query()->count());
        $this->assertGreaterThanOrEqual(1, $rerun->existingMatched + $rerun->duplicateConflicts + $rerun->restrictedExcluded);
    }

    public function test_bulk_dry_run_does_not_mutate_and_preserves_publication_gates(): void
    {
        File::put($this->feedPath, json_encode([
            [
                'name' => 'Magnesium Tablets',
                'sku' => 'MAG-60',
                'price' => '7.50',
                'currency' => 'GBP',
                'brand' => 'North Harbour',
                'category' => 'Minerals',
                'source_product_reference' => 'magnesium-tablets',
            ],
        ], JSON_THROW_ON_ERROR));

        $beforeProducts = Product::query()->count();
        $beforeRecords = CatalogueImportRecord::query()->count();
        $beforeRuns = CatalogueBulkIngestRun::query()->count();

        $report = app(CatalogueBulkIngestService::class)->ingest(
            new ResumableJsonCatalogueSourceReader($this->feedPath),
            ['run_key' => 'bulk-dry', 'dry_run' => true, 'limit' => 5]
        );

        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->sourceRecordsRetrieved);
        $this->assertSame($beforeProducts, Product::query()->count());
        $this->assertSame($beforeRecords, CatalogueImportRecord::query()->count());
        $this->assertSame($beforeRuns, CatalogueBulkIngestRun::query()->count());
        $this->assertSame(0, $report->approvedForPublication);
        $this->assertSame(0, $report->actuallyPublished);
    }

    public function test_bulk_command_probe_and_missing_source_failure(): void
    {
        $probe = Artisan::call('catalogue:bulk-ingest', ['--probe' => true]);
        $probeOutput = Artisan::output();
        $this->assertSame(0, $probe);
        $this->assertStringContainsString('Bulk source probe', $probeOutput);
        $this->assertStringContainsString('--source=public-html', $probeOutput);

        $missing = Artisan::call('catalogue:bulk-ingest', [
            '--run' => 'no-source',
            '--dry-run' => true,
        ]);
        $missingOutput = Artisan::output();
        $this->assertSame(1, $missing);
        $this->assertStringContainsString('bulk source is required', $missingOutput);
    }

    public function test_source_failure_is_recorded_without_fabricated_products(): void
    {
        $reader = new WooCommerceRestCatalogueSourceReader(
            baseUrl: 'https://catalogue.test',
            consumerKey: 'ck_test',
            consumerSecret: 'cs_test',
            pageSize: 10,
            httpGet: function (): string {
                throw new RuntimeException('WooCommerce API request failed (HTTP 401)');
            },
        );

        $report = app(CatalogueBulkIngestService::class)->ingest($reader, [
            'run_key' => 'bulk-auth-fail',
            'dry_run' => true,
            'limit' => 10,
        ]);

        $this->assertSame(0, $report->productsCreated);
        $this->assertSame(0, $report->newStagingRecords);
        $this->assertGreaterThanOrEqual(1, $report->retrievalFailures);
        $this->assertNotNull($report->blocker);
    }

    public function test_bulk_ingest_refuses_neon(): void
    {
        $original = config('database.default');
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.driver' => 'pgsql',
            'database.connections.pgsql.host' => 'ep-example.eu-west-2.aws.neon.tech',
            'database.connections.pgsql.database' => 'neondb',
            'database.connections.pgsql.url' => 'postgres://user:secret@ep-example.eu-west-2.aws.neon.tech/neondb',
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Neon');
            QualityAssuranceGuard::assertSafeEnvironment();
        } finally {
            config(['database.default' => $original]);
        }
    }

    public function test_classification_separation_keeps_audit_and_staging_distinct(): void
    {
        File::put($this->feedPath, json_encode([
            [
                'name' => 'Omega 3 Softgels',
                'sku' => 'OMG-90',
                'price' => '14.00',
                'currency' => 'GBP',
                'brand' => 'North Harbour',
                'category' => 'Supplements',
                'source_product_reference' => 'omega-3-softgels',
            ],
        ], JSON_THROW_ON_ERROR));

        $report = app(CatalogueBulkIngestService::class)->ingest(
            new ResumableJsonCatalogueSourceReader($this->feedPath),
            ['run_key' => 'bulk-class-sep', 'dry_run' => false]
        );

        $product = Product::query()->where('sku', 'OMG-90')->first();
        $this->assertNotNull($product);
        $this->assertNotSame(ProductStatus::ACTIVE, $product->status);
        $this->assertSame(0, Product::query()->purchasable()->whereKey($product->id)->count());
        $this->assertSame(0, $report->actuallyPublished);
        $record = CatalogueImportRecord::query()->where('source_product_reference', 'omega-3-softgels')->first();
        $this->assertNotNull($record);
        $this->assertSame(ImportMatchStatus::NEW, $record->match_status);
        $this->assertNotSame(ImportWorkflowStatus::PUBLISHED, $record->workflow_status);
    }
}
