<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CatalogueAuditClassification;
use App\Services\Catalogue\Import\CatalogueReferenceUrlAuditService;
use App\Services\Catalogue\Import\RestrictedProductDetector;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CatalogueReferenceUrlAuditTest extends TestCase
{
    protected string $auditDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditDir = storage_path('app/testing/catalogue-audits-'.uniqid());
        File::ensureDirectoryExists($this->auditDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->auditDir)) {
            File::deleteDirectory($this->auditDir);
        }
        parent::tearDown();
    }

    public function test_restricted_slug_is_excluded_without_fetching_the_product_page(): void
    {
        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/product-sitemap.xml' => <<<'XML'
                <urlset>
                    <url><loc>https://catalogue.test/product/trenbolone-acetate-100</loc></url>
                    <url><loc>https://catalogue.test/product/yohimbine-hcl-10mg</loc></url>
                </urlset>
                XML,
            'https://catalogue.test/product/yohimbine-hcl-10mg' => '<h1>Yohimbine HCl 10mg</h1><p>£12.00</p>',
        ]);

        $service = new CatalogueReferenceUrlAuditService(
            fetcher: $fetcher,
            detector: new RestrictedProductDetector,
            baseUrl: 'https://catalogue.test',
            auditDirectory: $this->auditDir,
        );
        $service->syncProductSitemapUrls();
        $result = $service->auditRange(1, 2, 'batch-test-01');

        $this->assertSame(2, $result['audited']);
        $this->assertSame(1, $result['excluded']);
        $this->assertSame(1, $result['held']);
        $this->assertSame(0, $result['lawful_candidates']);
        $this->assertSame(CatalogueAuditClassification::EXCLUDED_RESTRICTED->value, $result['records'][0]['classification']);
        $this->assertFalse($result['records'][0]['page_fetched']);
        $this->assertNotContains('https://catalogue.test/product/trenbolone-acetate-100', $fetcher->urls);
        $this->assertContains('https://catalogue.test/product/yohimbine-hcl-10mg', $fetcher->urls);
        $this->assertFileExists($this->auditDir.DIRECTORY_SEPARATOR.'batch-test-01.json');
        $summary = $service->refreshCumulativeSummary();
        $this->assertTrue($summary['reconciles']);
        $this->assertSame(2, $summary['unique_urls_audited']);
    }

    public function test_held_review_can_reclassify_clear_restricted_products(): void
    {
        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/product-sitemap.xml' => '<urlset><url><loc>https://catalogue.test/product/mystery-item</loc></url></urlset>',
            'https://catalogue.test/product/mystery-item' => '<h1>Mystery Item</h1>',
        ]);
        $service = new CatalogueReferenceUrlAuditService(
            fetcher: $fetcher,
            detector: new RestrictedProductDetector,
            baseUrl: 'https://catalogue.test',
            auditDirectory: $this->auditDir,
        );
        $service->syncProductSitemapUrls();
        $service->auditRange(1, 1, 'batch-test-hold');

        // Simulate clearer evidence appearing on a later review fetch.
        $fetcher->map['https://catalogue.test/product/mystery-item'] = '<h1>Trenbolone Acetate 100</h1><p>SKU: TRE-100</p>';
        $review = $service->reviewHeldRecords(fetchPages: true);

        $this->assertSame(1, $review['reviewed']);
        $this->assertSame(1, $review['reclassified_excluded']);
        $this->assertSame(0, $review['remain_held']);

        $batch = json_decode((string) file_get_contents($this->auditDir.DIRECTORY_SEPARATOR.'batch-test-hold.json'), true);
        $this->assertSame(CatalogueAuditClassification::EXCLUDED_RESTRICTED->value, $batch['records'][0]['classification']);
    }

    public function test_steroid_category_on_fetched_page_excludes_ambiguous_slug(): void
    {
        $fetcher = new MapReferenceFetcher([
            'https://catalogue.test/product-sitemap.xml' => '<urlset><url><loc>https://catalogue.test/product/xxx-tabs-pharmaqo-labs</loc></url></urlset>',
            'https://catalogue.test/product/xxx-tabs-pharmaqo-labs' => <<<'HTML'
                <h1>XXX Tabs – PharmaQo Labs</h1>
                <a href="https://catalogue.test/product-category/oral-steroids">Oral Steroids</a>
                <p>£54.00</p>
                HTML,
        ]);
        $service = new CatalogueReferenceUrlAuditService(
            fetcher: $fetcher,
            detector: new RestrictedProductDetector,
            baseUrl: 'https://catalogue.test',
            auditDirectory: $this->auditDir,
        );
        $service->syncProductSitemapUrls();
        $result = $service->auditRange(1, 1, 'batch-test-category');

        $this->assertSame(1, $result['excluded']);
        $this->assertSame(0, $result['held']);
        $this->assertTrue($result['records'][0]['page_fetched']);
        $this->assertSame(CatalogueAuditClassification::EXCLUDED_RESTRICTED->value, $result['records'][0]['classification']);
        $this->assertSame('Oral Steroids', $result['records'][0]['category']);
    }
}
