<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CatalogueImportRecord;
use App\Services\Catalogue\Import\PublicHtmlPageBatchPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicHtmlPageBatchPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected string $auditDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditDir = storage_path('app/testing/page-batch-'.uniqid());
        File::ensureDirectoryExists($this->auditDir);
        file_put_contents($this->auditDir.'/product-sitemap-urls.json', json_encode([
            'count' => 4,
            'urls' => [
                'https://catalogue.test/product/alpha',
                'https://catalogue.test/product/beta',
                'https://catalogue.test/product/gamma',
                'https://catalogue.test/product/delta',
            ],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->auditDir)) {
            File::deleteDirectory($this->auditDir);
        }
        parent::tearDown();
    }

    public function test_planner_selects_urls_without_page_evidence(): void
    {
        CatalogueImportRecord::query()->create([
            'import_batch_id' => 'B1',
            'source_name' => 'public-html',
            'source_product_reference' => 'alpha',
            'source_payload' => ['page_fetched' => true, 'name' => 'Alpha'],
            'normalized_payload' => ['name' => 'Alpha'],
            'workflow_status' => 'REJECTED',
            'match_status' => 'NEW',
            'imported_at' => now(),
        ]);

        $planner = new PublicHtmlPageBatchPlanner(
            sitemapCachePath: $this->auditDir.'/product-sitemap-urls.json',
            ledgerPath: $this->auditDir.'/ledger.json',
            batchDirectory: $this->auditDir,
        );

        $plan = $planner->planNextBatch(2, 'test-batch');
        $this->assertSame(4, $plan['sitemap_count']);
        $this->assertSame(1, $plan['page_evidence_count']);
        $this->assertSame(3, $plan['unfetched_count']);
        $this->assertCount(2, $plan['batch_urls']);
        $this->assertSame('https://catalogue.test/product/beta', $plan['batch_urls'][0]);
        $this->assertFileExists($plan['batch_path']);
    }
}
