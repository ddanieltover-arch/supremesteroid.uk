<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Catalogue\Import\CatalogueReferenceUrlAuditService;
use App\Services\Catalogue\Import\ReferenceSiteHttpClient;
use App\Services\Catalogue\Import\RestrictedProductDetector;
use App\Support\QualityAssuranceDatabase;
use App\Support\QualityAssuranceGuard;
use Illuminate\Console\Command;
use Throwable;

class CatalogueAuditReferenceUrlsCommand extends Command
{
    protected $signature = 'catalogue:audit-reference-urls
        {--from=1 : Inclusive 1-based sitemap URL index}
        {--to= : Inclusive 1-based sitemap URL index}
        {--batch= : Audit batch label, e.g. batch-05-urls-209-260}
        {--sync-sitemap : Refresh the local product sitemap URL list}
        {--review-held : Re-evaluate existing COMPLIANCE_HOLD audit records}
        {--fetch-ambiguous : Fetch product pages when slug evidence is not a clear exclusion (default true)}
        {--no-fetch-ambiguous : Classify from slug/detector only; do not open product pages}';

    protected $description = 'Audit reference-site product sitemap URLs into the local catalogue audit ledger without staging or publishing products.';

    public function handle(): int
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
            $this->error('Refusing to use a Neon database for catalogue audits.');

            return self::FAILURE;
        }

        $base = rtrim((string) config('catalogue.import.reference_site.base_url', 'https://supremesteroids.co.uk'), '/');
        $host = (string) parse_url($base, PHP_URL_HOST);
        $service = new CatalogueReferenceUrlAuditService(
            fetcher: new ReferenceSiteHttpClient(
                allowedHost: $host,
                delayMs: (int) config('catalogue.import.reference_site.delay_ms', 2000),
                timeoutSeconds: (int) config('catalogue.import.reference_site.timeout_seconds', 20),
            ),
            detector: app(RestrictedProductDetector::class),
            baseUrl: $base,
        );

        if ((bool) $this->option('sync-sitemap') || ! is_file(storage_path('app/catalogue-import/audits/product-sitemap-urls.json'))) {
            $synced = $service->syncProductSitemapUrls();
            $this->info('Synced product sitemap: '.$synced['count'].' URLs → '.$synced['path']);
        }

        if ((bool) $this->option('review-held')) {
            $fetch = ! (bool) $this->option('no-fetch-ambiguous');
            $review = $service->reviewHeldRecords(fetchPages: $fetch);
            $this->info('Held review: reviewed='.$review['reviewed']
                .' reclassified_excluded='.$review['reclassified_excluded']
                .' remain_held='.$review['remain_held']);
            $this->line('Wrote '.$review['path']);
            $summary = $service->refreshCumulativeSummary();
            $this->printSummary($summary);

            return self::SUCCESS;
        }

        $from = max(1, (int) $this->option('from'));
        $toOption = $this->option('to');
        if ($toOption === null || $toOption === '') {
            $this->error('Provide --to= for the inclusive end index, or use --review-held / --sync-sitemap.');

            return self::FAILURE;
        }
        $to = (int) $toOption;
        $batch = (string) ($this->option('batch') ?: sprintf('batch-%03d-%03d', $from, $to));
        $fetchAmbiguous = ! (bool) $this->option('no-fetch-ambiguous');

        $this->info("Auditing sitemap URLs #{$from}–#{$to} as {$batch} (fetch_ambiguous=".($fetchAmbiguous ? 'yes' : 'no').')');
        $result = $service->auditRange($from, $to, $batch, $fetchAmbiguous);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Audited', $result['audited']],
                ['EXCLUDED_RESTRICTED', $result['excluded']],
                ['COMPLIANCE_HOLD', $result['held']],
                ['LAWFUL_CANDIDATE_REVIEW', $result['lawful_candidates']],
                ['Access errors', $result['access_errors']],
            ]
        );
        $this->line('Wrote '.$result['path']);

        $rows = [];
        foreach ($result['records'] as $record) {
            $rows[] = [
                $record['index'],
                $record['classification'],
                \Illuminate\Support\Str::limit((string) $record['product_name'], 42),
                $record['page_fetched'] ? 'yes' : 'no',
            ];
        }
        $this->table(['#', 'Classification', 'Name', 'Fetched'], $rows);

        $summary = $service->refreshCumulativeSummary();
        $this->printSummary($summary);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function printSummary(array $summary): void
    {
        $this->newLine();
        $this->info('Cumulative audit summary');
        $this->line('Unique URLs audited: '.$summary['unique_urls_audited']);
        $this->line('Sitemap count: '.$summary['sitemap_count']);
        $this->line('URLs remaining: '.$summary['urls_remaining']);
        $this->line('Excluded: '.$summary['excluded']);
        $this->line('Compliance holds: '.$summary['compliance_holds']);
        $this->line('Lawful candidates: '.$summary['lawful_candidates']);
        $this->line('Reconciles: '.(($summary['reconciles'] ?? false) ? 'yes' : 'no'));
        if (isset($summary['path'])) {
            $this->line('Summary file: '.$summary['path']);
        }
    }
}
