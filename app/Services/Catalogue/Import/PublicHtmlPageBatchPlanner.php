<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Models\CatalogueImportRecord;
use Illuminate\Support\Facades\DB;

/**
 * Selects the next product URLs that still lack verified public page retrieval evidence.
 */
class PublicHtmlPageBatchPlanner
{
    public function __construct(
        protected string $sitemapCachePath = '',
        protected string $ledgerPath = '',
        protected string $batchDirectory = '',
    ) {
        if ($this->sitemapCachePath === '') {
            $this->sitemapCachePath = storage_path('app/'.ltrim((string) config('catalogue.import.public_html.sitemap_cache'), '/\\'));
        }
        if ($this->ledgerPath === '') {
            $this->ledgerPath = storage_path('app/catalogue-import/audits/public-html-page-retrieval-ledger.json');
        }
        if ($this->batchDirectory === '') {
            $this->batchDirectory = storage_path('app/catalogue-import/audits');
        }
    }

    /**
     * @return array{
     *     sitemap_count: int,
     *     page_evidence_count: int,
     *     unfetched_count: int,
     *     uncertainty: list<string>,
     *     batch_urls: list<string>,
     *     batch_label: string,
     *     batch_path: string
     * }
     */
    public function planNextBatch(int $limit = 50, ?string $batchLabel = null): array
    {
        $limit = max(1, min(50, $limit));
        $allUrls = $this->sitemapUrls();
        $evidence = $this->pageEvidenceByReference();
        $uncertainty = [];

        if ($allUrls === []) {
            return [
                'sitemap_count' => 0,
                'page_evidence_count' => 0,
                'unfetched_count' => 0,
                'uncertainty' => ['Product sitemap cache missing or empty; cannot establish URL order.'],
                'batch_urls' => [],
                'batch_label' => $batchLabel ?? 'batch-01',
                'batch_path' => '',
            ];
        }

        if (count($evidence) === 0 && CatalogueImportRecord::query()->exists()) {
            $uncertainty[] = 'No import records carry page_fetched=true yet; earlier slug-only rows do not count as page retrieval.';
        }

        $unfetched = [];
        foreach ($allUrls as $url) {
            $ref = $this->referenceFromUrl($url);
            if (($evidence[$ref] ?? false) === true) {
                continue;
            }
            $unfetched[] = $url;
        }

        $batchUrls = array_slice($unfetched, 0, $limit);
        $label = $batchLabel ?? sprintf('prompt-29-page-batch-%02d', $this->nextBatchNumber());
        $path = $this->batchDirectory.DIRECTORY_SEPARATOR.$label.'-urls.json';
        if (! is_dir($this->batchDirectory)) {
            @mkdir($this->batchDirectory, 0775, true);
        }

        file_put_contents($path, json_encode([
            'planned_at' => gmdate('c'),
            'batch_label' => $label,
            'limit' => $limit,
            'sitemap_count' => count($allUrls),
            'page_evidence_count' => count(array_filter($evidence)),
            'unfetched_remaining' => count($unfetched),
            'uncertainty' => $uncertainty,
            'count' => count($batchUrls),
            'urls' => $batchUrls,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'sitemap_count' => count($allUrls),
            'page_evidence_count' => count(array_filter($evidence)),
            'unfetched_count' => count($unfetched),
            'uncertainty' => $uncertainty,
            'batch_urls' => $batchUrls,
            'batch_label' => $label,
            'batch_path' => $path,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    public function appendLedgerEntries(array $entries): void
    {
        $existing = [];
        if (is_file($this->ledgerPath)) {
            $decoded = json_decode((string) file_get_contents($this->ledgerPath), true);
            if (is_array($decoded['entries'] ?? null)) {
                $existing = $decoded['entries'];
            }
        }

        $merged = array_merge($existing, $entries);
        file_put_contents($this->ledgerPath, json_encode([
            'updated_at' => gmdate('c'),
            'entries' => $merged,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return list<string>
     */
    protected function sitemapUrls(): array
    {
        if (! is_file($this->sitemapCachePath)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($this->sitemapCachePath), true);
        if (! is_array($decoded['urls'] ?? null)) {
            return [];
        }

        return array_values(array_map('strval', $decoded['urls']));
    }

    /**
     * @return array<string, bool>
     */
    protected function pageEvidenceByReference(): array
    {
        $driver = DB::connection()->getDriverName();
        $evidence = [];

        if ($driver === 'sqlite') {
            $rows = DB::select(
                'select source_product_reference as ref, max(coalesce(json_extract(source_payload, \'$.page_fetched\'), 0)) as fetched
                 from catalogue_import_records
                 where source_product_reference is not null and source_product_reference != \'\'
                 group by source_product_reference'
            );
            foreach ($rows as $row) {
                $evidence[(string) $row->ref] = (int) ($row->fetched ?? 0) === 1;
            }

            return $evidence;
        }

        foreach (CatalogueImportRecord::query()->whereNotNull('source_product_reference')->get(['source_product_reference', 'source_payload']) as $record) {
            $ref = (string) $record->source_product_reference;
            $payload = is_array($record->source_payload) ? $record->source_payload : [];
            $fetched = ($payload['page_fetched'] ?? false) === true;
            $evidence[$ref] = ($evidence[$ref] ?? false) || $fetched;
        }

        return $evidence;
    }

    protected function referenceFromUrl(string $url): string
    {
        return basename((string) (parse_url($url, PHP_URL_PATH) ?: $url));
    }

    protected function nextBatchNumber(): int
    {
        $files = glob($this->batchDirectory.DIRECTORY_SEPARATOR.'prompt-29-page-batch-*-urls.json') ?: [];

        return count($files) + 1;
    }
}
