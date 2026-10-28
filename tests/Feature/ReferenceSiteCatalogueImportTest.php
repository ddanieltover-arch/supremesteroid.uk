<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Catalogue\ReferencePageFetcher;
use App\Enums\ImportMatchStatus;
use App\Enums\ProductStatus;
use App\Exceptions\ProductNotPurchasableException;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Models\Brand;
use App\Models\CatalogueBrandMapping;
use App\Models\CatalogueCategoryMapping;
use App\Models\CatalogueImportBatch;
use App\Models\CatalogueImportImage;
use App\Models\CatalogueImportRecord;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoredFile;
use App\Services\Catalogue\CatalogueImportService;
use App\Services\Catalogue\CataloguePublicationChecklistService;
use App\Services\Catalogue\CataloguePublicationService;
use App\Services\Catalogue\Import\CatalogueImportOrchestrator;
use App\Services\Catalogue\Import\CatalogueMappingService;
use App\Services\Catalogue\Import\ReferenceSiteCatalogueSourceReader;
use App\Services\Catalogue\Import\ReferenceSiteHttpClient;
use App\Services\Catalogue\Import\RestrictedProductDetector;
use App\Support\QualityAssuranceGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReferenceSiteCatalogueImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_restricted_categories_are_not_fetched_or_staged(): void
    {
        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/sitemap.xml' => <<<'XML'
                <sitemapindex>
                    <sitemap><loc>https://catalogue.test/product_cat-sitemap.xml</loc></sitemap>
                </sitemapindex>
                XML,
            'https://catalogue.test/product_cat-sitemap.xml' => <<<'XML'
                <urlset>
                    <url><loc>https://catalogue.test/product-category/oral-steroids</loc></url>
                    <url><loc>https://catalogue.test/product-category/injectable-steroids/testosterone</loc></url>
                    <url><loc>https://catalogue.test/product-category/kamagra</loc></url>
                </urlset>
                XML,
        ]);

        $reader = $this->reader($fetcher);
        $records = iterator_to_array($reader->records());
        $stats = $reader->discoveryStats();

        $this->assertSame([], $records);
        $this->assertSame(3, $stats['categories_discovered']);
        $this->assertSame(3, $stats['categories_excluded']);
        $this->assertSame(0, $stats['product_candidates_discovered']);
        $this->assertSame(0, $stats['eligible_for_staging']);
        $this->assertNotContains('https://catalogue.test/product-category/oral-steroids', $fetcher->urls);
        $this->assertNotContains('https://catalogue.test/product-category/injectable-steroids/testosterone', $fetcher->urls);
        $this->assertNotContains('https://catalogue.test/product-category/kamagra', $fetcher->urls);
        $this->assertContains('https://catalogue.test/sitemap.xml', $fetcher->urls);
        $this->assertContains('https://catalogue.test/product_cat-sitemap.xml', $fetcher->urls);
    }

    public function test_only_lawful_candidates_are_staged_and_images_are_not_downloaded(): void
    {
        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/sitemap.xml' => '<sitemapindex><sitemap><loc>https://catalogue.test/product_cat-sitemap.xml</loc></sitemap></sitemapindex>',
            'https://catalogue.test/product_cat-sitemap.xml' => '<urlset><url><loc>https://catalogue.test/product-category/oral-steroids</loc></url><url><loc>https://catalogue.test/product-category/vitamins</loc></url></urlset>',
            'https://catalogue.test/product-category/vitamins' => <<<'HTML'
                <a href="https://catalogue.test/product/vitamin-c-tablets">Vitamin C Tablets</a>
                <a href="https://catalogue.test/product/anavar-10mg">Anavar 10mg</a>
                <a href="https://catalogue.test/product/vitamin-c-tablets">Vitamin C Tablets</a>
                HTML,
            'https://catalogue.test/product/vitamin-c-tablets' => <<<'HTML'
                <p>SKU: VITC-100</p>
                <span class="brand">North Harbour</span>
                <p>£9.50</p>
                <p>£12.00</p>
                <p>In stock</p>
                <img src="https://catalogue.test/images/vitamin-c.png" />
                <select name="attribute_pack"><option value="">Choose</option><option value="100">100 tablets</option></select>
                HTML,
            'https://catalogue.test/product/anavar-10mg' => '<p>SKU: ANA-10</p><p>£40</p>',
        ]);

        $before = Product::query()->count();
        $reader = $this->reader($fetcher);
        $report = app(CatalogueImportOrchestrator::class)->import($reader, dryRun: false, batchNumber: '2099-ref', limit: 20);

        $this->assertSame(1, $report->sourceRecords);
        $this->assertSame(1, $reader->discoveryStats()['excluded_candidates']);
        $this->assertNotContains('https://catalogue.test/product/anavar-10mg', $fetcher->urls);
        $this->assertNotContains('https://catalogue.test/images/vitamin-c.png', $fetcher->urls);
        $this->assertNotContains('https://catalogue.test/product-category/oral-steroids', $fetcher->urls);

        $product = Product::query()->where('sku', 'VITC-100')->first();
        $this->assertNotNull($product);
        $this->assertNotSame(ProductStatus::ACTIVE, $product->status);
        $this->assertSame(0, Product::query()->purchasable()->whereKey($product->id)->count());
        $this->assertSame(1, CatalogueImportImage::query()->where('source_url', 'https://catalogue.test/images/vitamin-c.png')->count());
        $this->assertSame(0, StoredFile::query()->count());
        $this->assertSame($before + 1, Product::query()->count());
        $this->assertNotNull(CatalogueImportBatch::query()->where('batch_number', '2099-ref')->first());
    }

    public function test_dry_run_does_not_mutate_and_missing_price_is_not_invented(): void
    {
        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/sitemap.xml' => '<sitemapindex><sitemap><loc>https://catalogue.test/product_cat-sitemap.xml</loc></sitemap></sitemapindex>',
            'https://catalogue.test/product_cat-sitemap.xml' => '<urlset><url><loc>https://catalogue.test/product-category/supplements</loc></url></urlset>',
            'https://catalogue.test/product-category/supplements' => '<a href="https://catalogue.test/product/zinc-tablets">Zinc Tablets</a>',
            'https://catalogue.test/product/zinc-tablets' => '<p>Bottle listed without a price.</p>',
        ]);

        $beforeProducts = Product::query()->count();
        $beforeBatches = CatalogueImportBatch::query()->count();
        $reader = $this->reader($fetcher);
        $report = app(CatalogueImportOrchestrator::class)->import($reader, dryRun: true, batchNumber: '2099-dry', limit: 20);

        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->sourceRecords);
        $this->assertSame($beforeProducts, Product::query()->count());
        $this->assertSame($beforeBatches, CatalogueImportBatch::query()->count());

        $normalized = app(\App\Services\Catalogue\CatalogueImportService::class)->normalize([
            'name' => 'Zinc Tablets',
            'source_url' => 'https://catalogue.test/product/zinc-tablets',
        ]);
        $this->assertNull($normalized['price_amount']);
        $this->assertSame('Zinc Tablets', $normalized['name']);
    }

    public function test_access_errors_stop_the_run_without_fabricated_records(): void
    {
        $fetcher = new MapReferenceFetcher([]);
        $reader = $this->reader($fetcher);
        $records = iterator_to_array($reader->records());
        $stats = $reader->discoveryStats();

        $this->assertSame([], $records);
        $this->assertSame(0, $stats['eligible_for_staging']);
        $this->assertGreaterThan(0, $stats['access_errors']);
    }

    public function test_repeat_reads_reuse_discovered_pages(): void
    {
        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/sitemap.xml' => '<sitemapindex><sitemap><loc>https://catalogue.test/product_cat-sitemap.xml</loc></sitemap></sitemapindex>',
            'https://catalogue.test/product_cat-sitemap.xml' => '<urlset><url><loc>https://catalogue.test/product-category/oral-steroids</loc></url></urlset>',
        ]);
        $reader = $this->reader($fetcher);
        iterator_to_array($reader->records());
        $fetched = count($fetcher->urls);
        $reader->discoveryLines();

        $this->assertSame($fetched, count($fetcher->urls));
        $this->assertNotContains('https://catalogue.test/product-category/oral-steroids', $fetcher->urls);
    }

    public function test_http_client_paces_requests_and_refuses_other_hosts(): void
    {
        $slept = 0;
        $client = new ReferenceSiteHttpClient(
            allowedHost: 'catalogue.test',
            delayMs: 25,
            timeoutSeconds: 1,
            sleeper: function () use (&$slept): void {
                $slept++;
            }
        );

        try {
            $client->get('https://catalogue.test/sitemap.xml');
        } catch (RuntimeException) {
        }
        try {
            $client->get('https://catalogue.test/product_cat-sitemap.xml');
        } catch (RuntimeException) {
        }

        $this->assertSame(1, $slept);
        $this->expectException(RuntimeException::class);
        $client->get('http://evil.test/sitemap.xml');
    }

    public function test_targeted_discovery_skips_restricted_pages_and_ambiguous_sitemap_urls(): void
    {
        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/' => <<<'HTML'
                <a href="https://catalogue.test/product-category/oral-steroids">Oral Steroids</a>
                <script type="application/ld+json">
                {"@type":"ListItem","url":"https://catalogue.test/product/anavar-10mg","name":"Anavar 10mg"}
                </script>
                HTML,
            'https://catalogue.test/shop?search=vitamin' => <<<'HTML'
                {"@type":"ListItem","url":"https://catalogue.test/product/anavar-10mg","name":"Anavar 10mg"}
                HTML,
            'https://catalogue.test/shop?search=supplement' => '<p>No supplement names</p>',
            'https://catalogue.test/shop?search=mineral' => '<p>No mineral names</p>',
            'https://catalogue.test/sitemap.xml' => <<<'XML'
                <sitemapindex>
                    <sitemap><loc>https://catalogue.test/product-sitemap.xml</loc></sitemap>
                    <sitemap><loc>https://catalogue.test/product_cat-sitemap.xml</loc></sitemap>
                </sitemapindex>
                XML,
            'https://catalogue.test/product-sitemap.xml' => <<<'XML'
                <urlset>
                    <url><loc>https://catalogue.test/product/anavar-10mg</loc></url>
                    <url><loc>https://catalogue.test/product/deus-medical-livamed-1-pitavastatin-calcium</loc></url>
                    <url><loc>https://catalogue.test/product/vitamin-d3-tablets</loc></url>
                </urlset>
                XML,
            'https://catalogue.test/product_cat-sitemap.xml' => '<urlset><url><loc>https://catalogue.test/product-category/oral-steroids</loc></url></urlset>',
            'https://catalogue.test/product/vitamin-d3-tablets' => '<p>SKU: VITD-30</p><p>£8.00</p><p>In stock</p><img src="https://catalogue.test/images/vitd.png" />',
            'https://catalogue.test/product/anavar-10mg' => '<p>SKU: ANA-10</p>',
            'https://catalogue.test/product/deus-medical-livamed-1-pitavastatin-calcium' => '<p>SKU: STATIN</p>',
        ]);

        $reader = $this->reader($fetcher);
        $records = iterator_to_array($reader->records());
        $stats = $reader->discoveryStats();

        $this->assertCount(1, $records);
        $this->assertSame('Vitamin D3 Tablets', $records[0]['name']);
        $this->assertSame('VITD-30', $records[0]['sku']);
        $this->assertSame(1, $stats['eligible_for_staging']);
        $this->assertGreaterThanOrEqual(2, $stats['excluded_candidates']);
        $this->assertNotContains('https://catalogue.test/product-category/oral-steroids', $fetcher->urls);
        $this->assertNotContains('https://catalogue.test/product/anavar-10mg', $fetcher->urls);
        $this->assertNotContains('https://catalogue.test/product/deus-medical-livamed-1-pitavastatin-calcium', $fetcher->urls);
        $this->assertNotContains('https://catalogue.test/images/vitd.png', $fetcher->urls);
        $lines = implode("\n", $reader->discoveryLines());
        $this->assertStringContainsString('Confirmed supplement/vitamin/accessory category URLs: none', $lines);
    }

    public function test_reference_import_refuses_neon_connections(): void
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

    public function test_reference_import_refuses_production_environment(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('production');
            QualityAssuranceGuard::assertSafeEnvironment();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_duplicate_skus_are_detected_and_existing_products_are_preserved(): void
    {
        $existingProduct = Product::query()->create([
            'sku' => 'VITC-100',
            'name' => 'Original Vitamin C',
            'slug' => 'original-vitamin-c',
            'status' => ProductStatus::DRAFT,
            'price_amount' => 15.00,
            'currency' => 'GBP',
        ]);

        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/sitemap.xml' => '<sitemapindex><sitemap><loc>https://catalogue.test/product_cat-sitemap.xml</loc></sitemap></sitemapindex>',
            'https://catalogue.test/product_cat-sitemap.xml' => '<urlset><url><loc>https://catalogue.test/product-category/vitamins</loc></url></urlset>',
            'https://catalogue.test/product-category/vitamins' => '<a href="https://catalogue.test/product/vitamin-c-tablets">Vitamin C Tablets</a>',
            'https://catalogue.test/product/vitamin-c-tablets' => '<p>SKU: VITC-100</p><p>£9.50</p><p>In stock</p>',
        ]);

        $initialProductCount = Product::query()->count();
        $reader = $this->reader($fetcher);
        $report = app(CatalogueImportOrchestrator::class)->import($reader, dryRun: false, batchNumber: 'dup-test', limit: 20);

        $this->assertSame(1, $report->sourceRecords);
        $this->assertSame(1, $report->exactMatches);
        $this->assertSame($initialProductCount, Product::query()->count(), 'No new product created for exact SKU duplicate.');

        $existingProduct->refresh();
        $this->assertSame('Original Vitamin C', $existingProduct->name, 'Existing product must not be overwritten.');
    }

    public function test_candidates_with_missing_or_incomplete_data_are_handled_safely(): void
    {
        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/sitemap.xml' => '<sitemapindex><sitemap><loc>https://catalogue.test/product_cat-sitemap.xml</loc></sitemap></sitemapindex>',
            'https://catalogue.test/product_cat-sitemap.xml' => '<urlset><url><loc>https://catalogue.test/product-category/vitamins</loc></url></urlset>',
            'https://catalogue.test/product-category/vitamins' => '<a href="https://catalogue.test/product/"> </a><a href="https://catalogue.test/product/valid-vitamin">Valid Vitamin</a>',
            'https://catalogue.test/product/valid-vitamin' => '<p>SKU: VAL-VIT</p><p>£5.00</p>',
        ]);

        $reader = $this->reader($fetcher);
        $records = iterator_to_array($reader->records());
        $stats = $reader->discoveryStats();

        $this->assertCount(1, $records);
        $this->assertSame('Valid Vitamin', $records[0]['name']);
        $this->assertSame(1, $stats['eligible_for_staging']);
    }

    public function test_catalogue_artisan_alias_runs_successfully(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('catalogue', [
            '--sample' => true,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
    }

    public function test_herbal_supplements_in_misfiled_categories_are_eligible_while_syringes_remain_excluded(): void
    {
        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/sitemap.xml' => <<<'XML'
                <sitemapindex>
                    <sitemap><loc>https://catalogue.test/product-sitemap.xml</loc></sitemap>
                    <sitemap><loc>https://catalogue.test/product_cat-sitemap.xml</loc></sitemap>
                </sitemapindex>
                XML,
            'https://catalogue.test/product_cat-sitemap.xml' => <<<'XML'
                <urlset>
                    <url><loc>https://catalogue.test/product-category/needles-and-syringes</loc></url>
                </urlset>
                XML,
            'https://catalogue.test/product-sitemap.xml' => <<<'XML'
                <urlset>
                    <url><loc>https://catalogue.test/product/himalaya-liv52-liver-care-tablets-100-tabs</loc></url>
                    <url><loc>https://catalogue.test/product/deus-medical-follistatin-activin-binding-protein</loc></url>
                    <url><loc>https://catalogue.test/product/3ml-syringes-pack-of-10</loc></url>
                    <url><loc>https://catalogue.test/product/anavar-10mg</loc></url>
                </urlset>
                XML,
            'https://catalogue.test/product/deus-medical-follistatin-activin-binding-protein' => '<p>SKU: FOLL</p>',
            'https://catalogue.test/product/himalaya-liv52-liver-care-tablets-100-tabs' => <<<'HTML'
                <div>
                    <h1>Himalaya Liv.52 – Liver care Tablets [100tabs]</h1>
                    <p>£8.90</p>
                    <div class="text-sm">Categories: <a href="/product-category/needles-and-syringes">Needles and Syringes</a></div>
                    <img id="mainProductImage" src="https://catalogue.test/images/liv52.webp" />
                    <!-- Related Products -->
                    <section>
                        <p>Related products</p>
                        <del>£35.90</del>
                    </section>
                </div>
                HTML,
            'https://catalogue.test/product/3ml-syringes-pack-of-10' => '<p>SKU: SYR-3</p><p>£4.00</p>',
            'https://catalogue.test/product/anavar-10mg' => '<p>SKU: ANA-10</p><p>£40.00</p>',
        ]);

        $reader = $this->reader($fetcher);
        $records = iterator_to_array($reader->records());
        $stats = $reader->discoveryStats();

        $this->assertCount(1, $records);
        $this->assertSame('Himalaya Liv.52 – Liver care Tablets [100tabs]', $records[0]['name']);
        $this->assertSame('8.90', $records[0]['price']);
        $this->assertNull($records[0]['compare_at_price'] ?? null, 'Compare-at price must not be contaminated by related products.');
        $this->assertSame('Himalaya', $records[0]['brand']);
        $this->assertSame('Dietary Supplements', $records[0]['category']);
        $this->assertSame(['https://catalogue.test/images/liv52.webp'], $records[0]['images']);
        $this->assertSame(1, $stats['eligible_for_staging']);
        $this->assertGreaterThanOrEqual(1, $stats['excluded_candidates']);
    }

    public function test_staged_image_url_with_spaces_is_safely_handled(): void
    {
        $image = new CatalogueImportImage([
            'source_url' => 'https://supremestd.b-cdn.net/wp-content/uploads/himalaya-liv52-liver-care-tablets-100-tabs (1).webp',
        ]);

        $safeUrl = $image->safeSourceUrl();
        $this->assertSame(
            'https://supremestd.b-cdn.net/wp-content/uploads/himalaya-liv52-liver-care-tablets-100-tabs%20(1).webp',
            $safeUrl
        );
        $this->assertNotFalse(filter_var($safeUrl, FILTER_VALIDATE_URL));
    }

    public function test_staged_product_is_blocked_from_publication_and_non_purchasable(): void
    {
        $product = Product::query()->create([
            'name' => 'Himalaya Liv52 Liver Care Tablets 100 Tabs',
            'slug' => 'himalaya-liv52-liver-care-tablets-100-tabs',
            'sku' => 'IMP-TEST-LIV52',
            'status' => ProductStatus::IMPORTED,
            'price_amount' => '8.90',
            'currency' => 'GBP',
            'is_purchasable' => false,
            'compliance_verified_at' => null,
        ]);

        $eval = app(CataloguePublicationChecklistService::class)->evaluate($product);
        $this->assertSame('BLOCKED', $eval['status']);
        $this->assertFalse($eval['can_publish']);
        $this->assertContains('Compliance review incomplete.', $eval['failures']);
        $this->assertContains('Missing primary image.', $eval['failures']);

        $this->expectException(ProductNotPurchasableException::class);
        app(CataloguePublicationService::class)->publish($product);
    }

    public function test_brand_and_category_mapping_updates_sync_correctly(): void
    {
        $brand = Brand::query()->create(['name' => 'Himalaya', 'slug' => 'himalaya', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Dietary Supplements', 'slug' => 'dietary-supplements', 'is_visible' => true]);

        $catMapping = CatalogueCategoryMapping::query()->create([
            'source_category' => 'Dietary Supplements',
            'normalized_source_category' => 'dietary supplements',
            'status' => \App\Enums\ImportMappingStatus::PENDING,
        ]);

        $brandMapping = CatalogueBrandMapping::query()->create([
            'source_brand' => 'Himalaya',
            'normalized_source_brand' => 'himalaya',
            'status' => \App\Enums\ImportMappingStatus::PENDING,
        ]);

        $this->assertSame(\App\Enums\ImportMappingStatus::PENDING, $catMapping->status);
        $this->assertSame(\App\Enums\ImportMappingStatus::PENDING, $brandMapping->status);

        $catMapping->update([
            'target_category_id' => $category->id,
            'status' => \App\Enums\ImportMappingStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $brandMapping->update([
            'target_brand_id' => $brand->id,
            'status' => \App\Enums\ImportMappingStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $this->assertSame(\App\Enums\ImportMappingStatus::APPROVED, $catMapping->fresh()->status);
        $this->assertSame($category->id, $catMapping->fresh()->target_category_id);
        $this->assertSame(\App\Enums\ImportMappingStatus::APPROVED, $brandMapping->fresh()->status);
        $this->assertSame($brand->id, $brandMapping->fresh()->target_brand_id);
    }

    public function test_product_with_missing_source_sku_is_assigned_internal_placeholder(): void
    {
        $service = app(CatalogueImportService::class);
        $record = $service->stage([
            'name' => 'Himalaya Liv52',
            'sku' => null,
            'price_amount' => '8.90',
            'currency' => 'GBP',
            'url' => 'https://catalogue.test/liv52',
            'category' => 'Dietary Supplements',
        ], 'BATCH-TEST', 'test_source');

        $product = $record->product;

        $this->assertNotNull($product);
        $this->assertNotNull($product->sku);
        $this->assertStringStartsWith('IMP-', $product->sku);
        $this->assertTrue($product->isInternalSku());
        $this->assertFalse($product->hasGenuineSku());
        $this->assertNull($product->genuineSku());
        $this->assertStringContainsString('None (Internal: IMP-', $product->displaySku());
    }

    public function test_product_with_genuine_sku_is_preserved_and_distinguished(): void
    {
        $service = app(CatalogueImportService::class);
        $record = $service->stage([
            'name' => 'Himalaya Liv52 Real SKU',
            'sku' => 'HIM-LIV-001',
            'price_amount' => '8.90',
            'currency' => 'GBP',
            'url' => 'https://catalogue.test/liv52-sku',
            'category' => 'Dietary Supplements',
        ], 'BATCH-TEST', 'test_source');

        $product = $record->product;

        $this->assertNotNull($product);
        $this->assertSame('HIM-LIV-001', $product->sku);
        $this->assertFalse($product->isInternalSku());
        $this->assertTrue($product->hasGenuineSku());
        $this->assertSame('HIM-LIV-001', $product->genuineSku());
        $this->assertSame('HIM-LIV-001', $product->displaySku());
    }

    public function test_api_resources_mask_internal_placeholder_sku(): void
    {
        $productWithInternalSku = Product::query()->create([
            'name' => 'Himalaya Liv52',
            'slug' => 'himalaya-liv52',
            'sku' => 'IMP-ABC1234567',
            'status' => ProductStatus::IMPORTED,
            'price_amount' => '8.90',
            'currency' => 'GBP',
            'is_purchasable' => false,
        ]);

        $productWithGenuineSku = Product::query()->create([
            'name' => 'Himalaya Cystone',
            'slug' => 'himalaya-cystone',
            'sku' => 'HIM-CYS-100',
            'status' => ProductStatus::IMPORTED,
            'price_amount' => '9.50',
            'currency' => 'GBP',
            'is_purchasable' => false,
        ]);

        $request = request();

        $detailInternal = (new ProductDetailResource($productWithInternalSku))->toArray($request);
        $this->assertNull($detailInternal['sku']);
        $this->assertFalse($detailInternal['has_genuine_sku']);
        $this->assertSame('IMP-ABC1234567', $detailInternal['internal_identifier']);

        $detailGenuine = (new ProductDetailResource($productWithGenuineSku))->toArray($request);
        $this->assertSame('HIM-CYS-100', $detailGenuine['sku']);
        $this->assertTrue($detailGenuine['has_genuine_sku']);
        $this->assertNull($detailGenuine['internal_identifier']);

        $cardInternal = (new ProductCardResource($productWithInternalSku))->toArray($request);
        $this->assertNull($cardInternal['sku']);
        $this->assertFalse($cardInternal['has_genuine_sku']);
        $this->assertSame('IMP-ABC1234567', $cardInternal['internal_identifier']);

        $cardGenuine = (new ProductCardResource($productWithGenuineSku))->toArray($request);
        $this->assertSame('HIM-CYS-100', $cardGenuine['sku']);
        $this->assertTrue($cardGenuine['has_genuine_sku']);
        $this->assertNull($cardGenuine['internal_identifier']);
    }

    public function test_duplicate_detection_by_source_product_reference_avoids_duplicate_draft(): void
    {
        $service = app(CatalogueImportService::class);
        $firstRecord = $service->stage([
            'name' => 'Himalaya Liv52 Duplicate Test',
            'sku' => null,
            'price_amount' => '8.90',
            'currency' => 'GBP',
            'url' => 'https://catalogue.test/liv52-dup',
            'category' => 'Dietary Supplements',
        ], 'BATCH-TEST', 'test_source');

        $product = $firstRecord->product;
        $this->assertNotNull($product);

        // Next import of the same source URL / reference
        $normalized = $service->normalize([
            'name' => 'Himalaya Liv52 Duplicate Test',
            'sku' => null,
            'price_amount' => '8.90',
            'currency' => 'GBP',
            'url' => 'https://catalogue.test/liv52-dup',
            'category' => 'Dietary Supplements',
        ], 'test_source');

        $matchResult = $service->detectMatch($normalized);
        $this->assertSame(ImportMatchStatus::POSSIBLE_DUPLICATE, $matchResult['status']);
        $this->assertSame($product->id, $matchResult['product']?->id);
    }

    public function test_publication_checklist_warns_on_internal_placeholder_sku(): void
    {
        $product = Product::query()->create([
            'name' => 'Himalaya Liv52',
            'slug' => 'himalaya-liv52-checklist',
            'sku' => 'IMP-PLACEHOLDER',
            'status' => ProductStatus::IMPORTED,
            'price_amount' => '8.90',
            'currency' => 'GBP',
            'is_purchasable' => false,
        ]);

        $eval = app(CataloguePublicationChecklistService::class)->evaluate($product);
        $supplierSkuItem = collect($eval['items'])->firstWhere('key', 'supplier_sku');

        $this->assertNotNull($supplierSkuItem);
        $this->assertFalse($supplierSkuItem['passed']);
        $this->assertSame('warning', $supplierSkuItem['severity']);
        $this->assertStringContainsString('No genuine supplier SKU documented', $supplierSkuItem['message']);
    }

    public function test_mapping_workflow_end_to_end_and_publication_gate_preservation(): void
    {
        $category = Category::query()->create(['name' => 'Dietary Supplements', 'slug' => 'dietary-supplements', 'is_visible' => true]);
        $brand = Brand::query()->create(['name' => 'Himalaya', 'slug' => 'himalaya', 'is_active' => true]);

        $service = app(CatalogueImportService::class);
        $record = $service->stage([
            'name' => 'Himalaya Liv52 Staged',
            'sku' => null,
            'price_amount' => '8.90',
            'currency' => 'GBP',
            'url' => 'https://catalogue.test/liv52-staged',
            'category' => 'Ayurvedic Herbal Formulas',
            'brand' => 'Himalaya Wellness Herbal',
        ], 'BATCH-MAPPING', 'test_source');

        $mappingService = app(CatalogueMappingService::class);

        // Verify initial pending state
        $catMapping = CatalogueCategoryMapping::query()->where('source_category', 'Ayurvedic Herbal Formulas')->first();
        $this->assertNotNull($catMapping);
        $this->assertSame(\App\Enums\ImportMappingStatus::PENDING, $catMapping->status);
        $this->assertNull($record->target_category_id);

        $brandMapping = CatalogueBrandMapping::query()->where('source_brand', 'Himalaya Wellness Herbal')->first();
        $this->assertNotNull($brandMapping);
        $this->assertSame(\App\Enums\ImportMappingStatus::PENDING, $brandMapping->status);
        $this->assertNull($record->target_brand_id);

        // Apply category mapping
        $mappingService->applyCategoryMapping($record, (string) $category->id);
        $record->refresh();
        $catMapping->refresh();

        $this->assertSame(\App\Enums\ImportMappingStatus::APPROVED, $catMapping->status);
        $this->assertSame($category->id, $catMapping->target_category_id);
        $this->assertSame($category->id, $record->target_category_id);
        $this->assertSame($category->id, $record->product?->category_id);

        // Apply brand mapping
        $mappingService->applyBrandMapping($record, (string) $brand->id);
        $record->refresh();
        $brandMapping->refresh();

        $this->assertSame(\App\Enums\ImportMappingStatus::APPROVED, $brandMapping->status);
        $this->assertSame($brand->id, $brandMapping->target_brand_id);
        $this->assertSame($brand->id, $record->target_brand_id);
        $this->assertSame($brand->id, $record->product?->brand_id);

        // Idempotent repeated mapping
        $mappingService->applyCategoryMapping($record, (string) $category->id);
        $mappingService->applyBrandMapping($record, (string) $brand->id);
        $this->assertSame(\App\Enums\ImportMappingStatus::APPROVED, $catMapping->fresh()->status);
        $this->assertSame(\App\Enums\ImportMappingStatus::APPROVED, $brandMapping->fresh()->status);

        // Confirm mapping completion did NOT make product purchasable or published
        $product = $record->product->fresh();
        $this->assertFalse($product->isPurchasable());
        $this->assertNull($product->compliance_verified_at);
        $checklist = app(CataloguePublicationChecklistService::class)->evaluate($product);
        $this->assertSame('BLOCKED', $checklist['status']);
        $this->assertFalse($checklist['can_publish']);
    }

    public function test_mapping_workflow_rejects_invalid_target_ids_safely(): void
    {
        $service = app(CatalogueImportService::class);
        $record = $service->stage([
            'name' => 'Himalaya Liv52 Invalid ID Test',
            'sku' => null,
            'price_amount' => '8.90',
            'currency' => 'GBP',
            'url' => 'https://catalogue.test/liv52-invalid-id',
            'category' => 'Dietary Supplements',
            'brand' => 'Himalaya',
        ], 'BATCH-MAPPING', 'test_source');

        $mappingService = app(CatalogueMappingService::class);

        $this->expectException(\InvalidArgumentException::class);
        $mappingService->applyCategoryMapping($record, '00000000-0000-0000-0000-000000000000');
    }

    protected function reader(ReferencePageFetcher $fetcher): ReferenceSiteCatalogueSourceReader
    {
        return new ReferenceSiteCatalogueSourceReader(
            fetcher: $fetcher,
            detector: new RestrictedProductDetector,
            baseUrl: 'https://catalogue.test',
            maxPages: 30,
            maxEligible: 20,
        );
    }
}

class MapReferenceFetcher implements ReferencePageFetcher
{
    /** @var list<string> */
    public array $urls = [];

    /**
     * @param  array<string, string>  $map
     */
    public function __construct(public array $map) {}

    public function get(string $url): string
    {
        $this->urls[] = $url;
        if (! isset($this->map[$url])) {
            throw new RuntimeException('Reference page request failed (HTTP 404): '.$url);
        }

        return $this->map[$url];
    }
}
