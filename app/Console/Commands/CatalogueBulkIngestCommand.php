<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Catalogue\CatalogueSourceReader;
use App\Services\Catalogue\Import\CatalogueBulkIngestService;
use App\Services\Catalogue\Import\PublicCatalogueCapabilityReportService;
use App\Services\Catalogue\Import\PublicHtmlPageBatchPlanner;
use App\Services\Catalogue\Import\PublicHtmlCatalogueSourceReader;
use App\Services\Catalogue\Import\ReferenceSiteHttpClient;
use App\Services\Catalogue\Import\ResumableJsonCatalogueSourceReader;
use App\Services\Catalogue\Import\RestrictedProductDetector;
use App\Services\Catalogue\Import\WooCommerceRestCatalogueSourceReader;
use App\Support\QualityAssuranceDatabase;
use App\Support\QualityAssuranceGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class CatalogueBulkIngestCommand extends Command
{
    protected $signature = 'catalogue:bulk-ingest
        {--source= : Source type: woocommerce|json|public-html}
        {--path= : Path to an authorized JSON catalogue export}
        {--run= : Stable run key for resume (required unless --probe)}
        {--resume : Resume a paused/failed run from its checkpoint}
        {--limit= : Max records to process in this invocation}
        {--page-size=25 : Page size for paginated authorized sources}
        {--batch= : Optional import batch number override}
        {--dry-run : Analyse without mutating products or import tables}
        {--probe : Report configured sources and public-source capability}
        {--capability-report : Write the public-source capability report only}
        {--product-page-batch : Fetch the next unprocessed public product pages (up to --limit) and ingest evidence}';

    protected $description = 'Resumable bulk catalogue ingestion from authorized API/export or permitted public HTML/JSON-LD. Never auto-publishes.';

    protected ?string $publicHtmlSitemapCacheOverride = null;

    protected bool $publicHtmlFetchRestrictedOverride = false;

    protected int $publicHtmlMaxPageFetchesOverride = 0;

    public function handle(CatalogueBulkIngestService $ingest): int
    {
        QualityAssuranceDatabase::bindIsolatedSqliteIfNeeded();
        try {
            QualityAssuranceGuard::assertSafeEnvironment();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $connection = QualityAssuranceDatabase::describe();
        $this->line('Database: '.$connection['driver'].' / '.$connection['database']);
        if (str_contains(strtolower($connection['database']), 'neon')) {
            $this->error('Refusing to use a Neon database for catalogue bulk ingest.');

            return self::FAILURE;
        }

        if ((bool) $this->option('capability-report')) {
            return $this->writeCapabilityReport();
        }

        if ((bool) $this->option('probe')) {
            return $this->probeSources();
        }

        if ((bool) $this->option('product-page-batch')) {
            $batchLimit = $this->option('limit');
            $limit = $batchLimit !== null && $batchLimit !== '' ? (int) $batchLimit : 50;
            $plan = app(PublicHtmlPageBatchPlanner::class)->planNextBatch($limit);
            if ($plan['batch_urls'] === []) {
                $this->error('No product URLs remain for page retrieval, or the sitemap cache is missing.');
                foreach ($plan['uncertainty'] as $note) {
                    $this->line($note);
                }

                return self::FAILURE;
            }
            $this->publicHtmlSitemapCacheOverride = $plan['batch_path'];
            $this->publicHtmlFetchRestrictedOverride = true;
            $this->publicHtmlMaxPageFetchesOverride = count($plan['batch_urls']);
            $this->line('Product page batch: '.$plan['batch_label']);
            $this->line('Sitemap URLs: '.$plan['sitemap_count'].' · page evidence: '.$plan['page_evidence_count'].' · unfetched: '.$plan['unfetched_count']);
            foreach ($plan['uncertainty'] as $note) {
                $this->warn($note);
            }
            if ($this->option('run') === null || $this->option('run') === '') {
                $this->input->setOption('run', $plan['batch_label']);
            }
            if ($this->option('limit') === null || $this->option('limit') === '') {
                $this->input->setOption('limit', (string) count($plan['batch_urls']));
            }
            if ($this->option('source') === null || $this->option('source') === '') {
                $this->input->setOption('source', 'public-html');
            }
        }

        $runKey = (string) ($this->option('run') ?: '');
        if ($runKey === '') {
            $this->error('Provide --run= to identify this bulk ingest (required for resume).');

            return self::FAILURE;
        }

        try {
            $reader = $this->resolveReader();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->newLine();
            $this->line('A bulk source is required. Configure one of:');
            $this->line('  1) WooCommerce REST: CATALOGUE_WC_BASE_URL, CATALOGUE_WC_CONSUMER_KEY, CATALOGUE_WC_CONSUMER_SECRET');
            $this->line('  2) Official product export JSON via --source=json --path=storage/app/catalogue-import/exports/...');
            $this->line('  3) Permitted public HTML/JSON-LD via --source=public-html (sitemap + paced product pages)');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $report = $ingest->ingest($reader, [
            'run_key' => $runKey,
            'dry_run' => (bool) $this->option('dry-run'),
            'resume' => (bool) $this->option('resume'),
            'limit' => $limit !== null && $limit !== '' ? (int) $limit : null,
            'page_size' => (int) $this->option('page-size'),
            'batch_number' => $this->option('batch') ? (string) $this->option('batch') : null,
        ]);

        foreach ($report->lines() as $line) {
            $this->line($line);
        }

        if ((bool) $this->option('product-page-batch') && ! $report->dryRun) {
            $entries = [];
            foreach ($report->rows as $row) {
                $entries[] = [
                    'run_key' => $runKey,
                    'reference' => $row['reference'] ?? null,
                    'outcome' => $row['outcome'] ?? null,
                    'workflow_status' => $row['workflow_status'] ?? null,
                    'recorded_at' => gmdate('c'),
                ];
            }
            if ($entries !== []) {
                app(PublicHtmlPageBatchPlanner::class)->appendLedgerEntries($entries);
            }
        }

        if ($report->rows !== []) {
            $this->newLine();
            $this->table(
                ['Reference', 'Name', 'Match', 'Workflow', 'Outcome'],
                collect($report->rows)->take(30)->map(fn (array $row) => [
                    $row['reference'] ?? '',
                    Str::limit((string) ($row['name'] ?? ''), 36),
                    $row['match_status'] ?? '',
                    $row['workflow_status'] ?? '',
                    $row['outcome'] ?? '',
                ])->all()
            );
            if (count($report->rows) > 30) {
                $this->line('… '.(count($report->rows) - 30).' additional rows omitted from table.');
            }
        }

        return self::SUCCESS;
    }

    protected function probeSources(): int
    {
        $wcConfigured = filled(config('catalogue.import.woocommerce.consumer_key'))
            && filled(config('catalogue.import.woocommerce.consumer_secret'))
            && filled(config('catalogue.import.woocommerce.base_url'));

        $this->info('Bulk source probe');
        $this->line('WooCommerce REST credentials configured: '.($wcConfigured ? 'yes' : 'no'));
        $this->line('WooCommerce base URL: '.(string) (config('catalogue.import.woocommerce.base_url') ?: 'unset'));
        $this->line('Public HTML/JSON-LD source: available via --source=public-html');
        $exportDir = storage_path('app/catalogue-import/exports');
        if (! is_dir($exportDir)) {
            @mkdir($exportDir, 0775, true);
        }
        $exports = array_values(array_filter(scandir($exportDir) ?: [], fn ($f) => ! in_array($f, ['.', '..', '.gitignore'], true)));
        $this->line('Official export files in storage/app/catalogue-import/exports: '.count($exports));
        foreach (array_slice($exports, 0, 10) as $file) {
            $this->line('  - '.$file);
        }
        $sitemapCache = storage_path('app/'.ltrim((string) config('catalogue.import.public_html.sitemap_cache'), '/\\'));
        if (is_file($sitemapCache)) {
            $decoded = json_decode((string) file_get_contents($sitemapCache), true);
            $this->line('Cached product sitemap URLs: '.(int) ($decoded['count'] ?? 0));
        }
        $this->newLine();
        if (! $wcConfigured && $exports === []) {
            $this->warn('No credentialed WooCommerce/export source is configured.');
            $this->line('Public HTML/JSON-LD remains available: php artisan catalogue:bulk-ingest --source=public-html --run=...');
            $this->line('Capability report: php artisan catalogue:bulk-ingest --capability-report');
        }

        return self::SUCCESS;
    }

    protected function writeCapabilityReport(): int
    {
        $base = rtrim((string) config('catalogue.import.public_html.base_url', 'https://supremesteroids.co.uk'), '/');
        $host = (string) parse_url($base, PHP_URL_HOST);
        if ($host === '') {
            $this->error('Public HTML base URL is invalid.');

            return self::FAILURE;
        }

        $fetcher = new ReferenceSiteHttpClient(
            allowedHost: $host,
            delayMs: (int) config('catalogue.import.public_html.delay_ms', 2000),
            timeoutSeconds: (int) config('catalogue.import.public_html.timeout_seconds', 20),
        );

        $probes = [
            ['url' => $base.'/robots.txt', 'status' => 404, 'notes' => 'No published robots.txt', 'automated_access' => 'no-disallow-rules-published'],
            ['url' => $base.'/sitemap.xml', 'status' => 200, 'notes' => 'Sitemap index', 'automated_access' => 'permitted-as-public'],
            ['url' => $base.'/product-sitemap.xml', 'status' => 200, 'notes' => 'Product URL list only', 'automated_access' => 'permitted-as-public'],
            ['url' => $base.'/product_cat-sitemap.xml', 'status' => 200, 'notes' => 'Category URLs predominantly restricted', 'automated_access' => 'permitted-as-public'],
            ['url' => $base.'/feed', 'status' => 404, 'notes' => 'RSS unavailable', 'automated_access' => 'unavailable'],
            ['url' => $base.'/product/feed', 'status' => 404, 'notes' => 'Product RSS unavailable', 'automated_access' => 'unavailable'],
            ['url' => $base.'/wp-json', 'status' => 404, 'notes' => 'WP REST unavailable', 'automated_access' => 'unavailable'],
            ['url' => $base.'/wp-json/wp/v2/product', 'status' => 404, 'notes' => 'WP product REST unavailable', 'automated_access' => 'unavailable'],
            ['url' => $base.'/wp-json/wc/store/v1/products', 'status' => 404, 'notes' => 'Store API unavailable', 'automated_access' => 'unavailable'],
            ['url' => $base.'/wp-json/wc/v3/products', 'status' => 404, 'notes' => 'WC REST v3 unavailable without credentials', 'automated_access' => 'unavailable'],
            ['url' => $base.'/shop?search=vitamin', 'status' => 200, 'notes' => 'Public shop search HTML', 'automated_access' => 'permitted-as-public-with-pacing'],
        ];

        $service = new PublicCatalogueCapabilityReportService(
            fetcher: $fetcher,
            detector: app(RestrictedProductDetector::class),
            baseUrl: $base,
        );

        $report = $service->build(
            $probes,
            $base.'/product/himalaya-liv52-liver-care-tablets-100-tabs'
        );

        $this->info('Public catalogue capability report written');
        $this->line('Path: '.$report['path']);
        $this->line('Sitemap URLs discovered: '.$report['summary']['sitemap_urls_discovered']);
        $this->line('Slug-excluded without fetch: '.$report['summary']['slug_classified_excluded']);
        $this->line('Page-fetch candidates: '.$report['summary']['page_fetch_candidates']);
        $this->line('Authorized API available: '.($report['summary']['authorized_api_available'] ? 'yes' : 'no'));
        $this->line('Public HTML/JSON-LD available: '.($report['summary']['public_html_jsonld_available'] ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    protected function resolveReader(): CatalogueSourceReader
    {
        $source = strtolower((string) ($this->option('source') ?: ''));
        $path = $this->option('path');
        $pageSize = max(1, (int) $this->option('page-size'));

        if ($source === '' && $path) {
            $source = str_ends_with(strtolower((string) $path), '.json') ? 'json' : $source;
        }

        if ($source === '' || $source === 'auto') {
            if (
                filled(config('catalogue.import.woocommerce.consumer_key'))
                && filled(config('catalogue.import.woocommerce.consumer_secret'))
            ) {
                $source = 'woocommerce';
            }
        }

        if (in_array($source, ['woocommerce', 'wc'], true)) {
            return new WooCommerceRestCatalogueSourceReader(
                baseUrl: (string) config('catalogue.import.woocommerce.base_url'),
                consumerKey: (string) config('catalogue.import.woocommerce.consumer_key'),
                consumerSecret: (string) config('catalogue.import.woocommerce.consumer_secret'),
                pageSize: $pageSize,
                timeoutSeconds: (int) config('catalogue.import.woocommerce.timeout_seconds', 20),
            );
        }

        if ($source === 'json') {
            if (! is_string($path) || $path === '') {
                throw new InvalidArgumentException('JSON bulk ingest requires --path= to an authorized export file.');
            }

            return new ResumableJsonCatalogueSourceReader(
                path: $this->absolutePath($path),
                sourceName: 'json-feed',
                pageSize: $pageSize,
            );
        }

        if (in_array($source, ['public-html', 'public_html', 'html', 'reference-public'], true)) {
            return $this->publicHtmlReader();
        }

        throw new RuntimeException(
            'No bulk catalogue source selected. Use --source=woocommerce, --source=json --path=..., or --source=public-html.'
        );
    }

    protected function publicHtmlReader(): PublicHtmlCatalogueSourceReader
    {
        $base = rtrim((string) config('catalogue.import.public_html.base_url', 'https://supremesteroids.co.uk'), '/');
        $host = (string) parse_url($base, PHP_URL_HOST);
        if ($host === '') {
            throw new InvalidArgumentException('Public HTML base URL is invalid.');
        }

        $cacheRelative = (string) config('catalogue.import.public_html.sitemap_cache');
        $cachePath = $this->publicHtmlSitemapCacheOverride
            ?: storage_path('app/'.ltrim($cacheRelative, '/\\'));

        $fetchRestricted = $this->publicHtmlFetchRestrictedOverride
            || (bool) config('catalogue.import.public_html.fetch_restricted_pages', false);
        $maxFetches = $this->publicHtmlMaxPageFetchesOverride > 0
            ? $this->publicHtmlMaxPageFetchesOverride
            : (int) config('catalogue.import.public_html.max_page_fetches', 100);

        return new PublicHtmlCatalogueSourceReader(
            fetcher: new ReferenceSiteHttpClient(
                allowedHost: $host,
                delayMs: (int) config('catalogue.import.public_html.delay_ms', 2000),
                timeoutSeconds: (int) config('catalogue.import.public_html.timeout_seconds', 20),
            ),
            detector: app(RestrictedProductDetector::class),
            baseUrl: $base,
            sitemapUrlCachePath: $cachePath,
            fetchRestrictedPages: $fetchRestricted,
            maxPageFetches: $maxFetches,
        );
    }

    protected function absolutePath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }
        $fromBase = base_path($path);
        if (is_file($fromBase)) {
            return $fromBase;
        }
        $fromStorage = storage_path('app/'.ltrim($path, '/\\'));
        if (is_file($fromStorage)) {
            return $fromStorage;
        }

        throw new InvalidArgumentException('Authorized catalogue export not found: '.$path);
    }
}
