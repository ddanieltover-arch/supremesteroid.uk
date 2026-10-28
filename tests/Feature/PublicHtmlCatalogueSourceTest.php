<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Catalogue\ReferencePageFetcher;
use App\Enums\ImportWorkflowStatus;
use App\Enums\ProductStatus;
use App\Models\CatalogueImportRecord;
use App\Models\Product;
use App\Services\Catalogue\Import\CatalogueBulkIngestService;
use App\Services\Catalogue\Import\PublicHtmlCatalogueSourceReader;
use App\Services\Catalogue\Import\PublicProductPageParser;
use App\Services\Catalogue\Import\RestrictedProductDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PublicHtmlCatalogueSourceTest extends TestCase
{
    use RefreshDatabase;

    protected string $sitemapCache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sitemapCache = storage_path('app/testing/public-html-sitemap-'.uniqid().'.json');
        @mkdir(dirname($this->sitemapCache), 0775, true);
        file_put_contents($this->sitemapCache, json_encode([
            'fetched_at' => gmdate('c'),
            'source' => 'https://catalogue.test/product-sitemap.xml',
            'count' => 3,
            'urls' => [
                'https://catalogue.test/product/anavar-10mg-proper-labs',
                'https://catalogue.test/product/himalaya-liv52-liver-care-tablets-100-tabs',
                'https://catalogue.test/product/ambiguous-research-compound-xyz',
            ],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        if (is_file($this->sitemapCache)) {
            @unlink($this->sitemapCache);
        }
        parent::tearDown();
    }

    public function test_parser_extracts_json_ld_and_ignores_placeholder_brand(): void
    {
        $html = <<<'HTML'
<html><head>
<script type="application/ld+json">
{"@context":"https://schema.org/","@type":"Product","name":"Himalaya Liv.52","sku":"78088819","description":"Liver care tablets","brand":{"@type":"Brand","name":"Brand"},"image":["https://cdn.test/liv52.webp"],"offers":{"@type":"Offer","price":"8.90","priceCurrency":"GBP","availability":"https://schema.org/InStock"}}
</script>
</head><body><h1>Himalaya Liv.52 – Liver care Tablets [100tabs]</h1>
<p class="price">£8.90</p>
<img id="mainProductImage" src="https://cdn.test/liv52.webp" />
</body></html>
HTML;

        $parsed = (new PublicProductPageParser)->parse($html, 'https://catalogue.test/product/liv52');
        $this->assertSame('Himalaya Liv.52', $parsed['name']);
        $this->assertSame('78088819', $parsed['sku']);
        $this->assertSame('8.90', $parsed['price']);
        $this->assertSame('GBP', $parsed['currency']);
        $this->assertNull($parsed['brand']);
        $this->assertContains('https://cdn.test/liv52.webp', $parsed['images']);
        $this->assertContains('json-ld', $parsed['fields_from']);
    }

    public function test_unavailable_endpoints_do_not_fabricate_records(): void
    {
        @unlink($this->sitemapCache);
        $fetcher = new class implements ReferencePageFetcher
        {
            public function get(string $url): string
            {
                throw new RuntimeException('Reference page request failed (HTTP 404): '.$url);
            }
        };

        $reader = new PublicHtmlCatalogueSourceReader(
            fetcher: $fetcher,
            detector: app(RestrictedProductDetector::class),
            baseUrl: 'https://catalogue.test',
            sitemapUrlCachePath: $this->sitemapCache,
        );

        $records = iterator_to_array($reader->records());
        $this->assertSame([], $records);
        $stats = $reader->retrievalStats();
        $this->assertSame(0, $stats['discovered']);
        $this->assertGreaterThanOrEqual(1, $stats['retrieval_failures']);
        $this->assertTrue($stats['exhausted']);
    }

    public function test_public_html_bulk_excludes_restricted_holds_ambiguous_and_is_resumable(): void
    {
        $pages = [
            'https://catalogue.test/product/himalaya-liv52-liver-care-tablets-100-tabs' => <<<'HTML'
<html><head><script type="application/ld+json">
{"@type":"Product","name":"Himalaya Liv.52 Liver Care","sku":"78088819","description":"Herbal liver care supplement","brand":{"name":"Himalaya"},"image":["https://cdn.test/liv52.webp"],"offers":{"price":"8.90","priceCurrency":"GBP","availability":"https://schema.org/InStock"}}
</script></head><body><h1>Himalaya Liv.52 Liver Care</h1><a href="/product-category/supplements">Supplements</a></body></html>
HTML,
            'https://catalogue.test/product/ambiguous-research-compound-xyz' => <<<'HTML'
<html><body><h1>Ambiguous Research Compound XYZ</h1><p>£12.00</p></body></html>
HTML,
        ];

        $fetcher = new class($pages) implements ReferencePageFetcher
        {
            /** @param array<string, string> $pages */
            public function __construct(protected array $pages) {}

            public function get(string $url): string
            {
                if (! isset($this->pages[$url])) {
                    throw new RuntimeException('Reference page request failed (HTTP 404): '.$url);
                }

                return $this->pages[$url];
            }
        };

        $reader = new PublicHtmlCatalogueSourceReader(
            fetcher: $fetcher,
            detector: app(RestrictedProductDetector::class),
            baseUrl: 'https://catalogue.test',
            sitemapUrlCachePath: $this->sitemapCache,
            maxPageFetches: 10,
        );

        $service = app(CatalogueBulkIngestService::class);
        $first = $service->ingest($reader, [
            'run_key' => 'public-html-test',
            'dry_run' => false,
            'limit' => 2,
            'page_size' => 1,
        ]);

        $this->assertSame(2, $first->sourceRecordsRetrieved);
        $this->assertFalse($first->complete);
        $this->assertSame(1, $first->restrictedExcluded);

        $reader2 = new PublicHtmlCatalogueSourceReader(
            fetcher: $fetcher,
            detector: app(RestrictedProductDetector::class),
            baseUrl: 'https://catalogue.test',
            sitemapUrlCachePath: $this->sitemapCache,
            maxPageFetches: 10,
        );
        $second = $service->ingest($reader2, [
            'run_key' => 'public-html-test',
            'dry_run' => false,
            'resume' => true,
            'limit' => 10,
            'page_size' => 1,
        ]);

        $this->assertTrue($second->complete);
        $this->assertSame(0, Product::query()->where('status', ProductStatus::ACTIVE)->count());
        $this->assertSame(0, Product::query()->purchasable()->count());

        $anavar = CatalogueImportRecord::query()->where('source_product_reference', 'anavar-10mg-proper-labs')->first();
        $this->assertNotNull($anavar);
        $this->assertSame(ImportWorkflowStatus::REJECTED, $anavar->workflow_status);
        $this->assertNull($anavar->product_id);

        $liv = CatalogueImportRecord::query()->where('source_product_reference', 'himalaya-liv52-liver-care-tablets-100-tabs')->first();
        $this->assertNotNull($liv);
        $this->assertNotNull($liv->product_id);
        $this->assertNotSame(ImportWorkflowStatus::PUBLISHED, $liv->workflow_status);

        $held = CatalogueImportRecord::query()->where('source_product_reference', 'ambiguous-research-compound-xyz')->first();
        $this->assertNotNull($held);
        $this->assertTrue(
            (bool) $held->requires_compliance_review
            || $held->workflow_status === ImportWorkflowStatus::COMPLIANCE_REVIEW,
            'Ambiguous public-html records must remain on a compliance hold path.'
        );

        $before = Product::query()->count();
        $rerunReader = new PublicHtmlCatalogueSourceReader(
            fetcher: $fetcher,
            detector: app(RestrictedProductDetector::class),
            baseUrl: 'https://catalogue.test',
            sitemapUrlCachePath: $this->sitemapCache,
            maxPageFetches: 10,
        );
        $service->ingest($rerunReader, [
            'run_key' => 'public-html-idempotent',
            'dry_run' => false,
        ]);
        $this->assertSame($before, Product::query()->count());
    }

    public function test_malformed_product_html_preserves_missing_fields(): void
    {
        $parser = new PublicProductPageParser;
        $parsed = $parser->parse('<html><body><p>No product schema</p></body></html>', 'https://catalogue.test/product/empty');
        $this->assertArrayNotHasKey('name', $parsed);
        $this->assertArrayNotHasKey('sku', $parsed);
        $this->assertArrayNotHasKey('price', $parsed);
        $this->assertSame([], $parsed['images']);
    }
}
