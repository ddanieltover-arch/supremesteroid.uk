<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\ReferencePageFetcher;
use App\Enums\CatalogueAuditClassification;
use RuntimeException;

/**
 * Audits public reference-site product URLs into a durable local ledger.
 * Does not stage, approve, or publish catalogue products.
 */
class CatalogueReferenceUrlAuditService
{
    public function __construct(
        protected ReferencePageFetcher $fetcher,
        protected RestrictedProductDetector $detector,
        protected string $baseUrl = 'https://supremesteroids.co.uk',
        protected string $auditDirectory = '',
    ) {
        if ($this->auditDirectory === '') {
            $this->auditDirectory = storage_path('app/catalogue-import/audits');
        }
    }

    /**
     * @return array{count: int, urls: list<string>, path: string, fetched_at: string}
     */
    public function syncProductSitemapUrls(): array
    {
        $this->ensureAuditDirectory();
        $sitemapUrl = rtrim($this->baseUrl, '/').'/product-sitemap.xml';
        $xml = $this->fetcher->get($sitemapUrl);
        preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $xml, $matches);
        $urls = array_values(array_unique(array_map(
            static function (string $value): string {
                $url = html_entity_decode(trim($value), ENT_QUOTES);
                $url = strtok($url, '#') ?: $url;
                $url = strtok($url, '?') ?: $url;

                return rtrim($url, '/');
            },
            $matches[1] ?? []
        )));

        $payload = [
            'fetched_at' => gmdate('c'),
            'source' => $sitemapUrl,
            'count' => count($urls),
            'urls' => $urls,
        ];
        $path = $this->auditDirectory.DIRECTORY_SEPARATOR.'product-sitemap-urls.json';
        $this->writeJson($path, $payload);

        return [
            'count' => count($urls),
            'urls' => $urls,
            'path' => $path,
            'fetched_at' => $payload['fetched_at'],
        ];
    }

    /**
     * @return list<string>
     */
    public function productUrls(): array
    {
        $path = $this->auditDirectory.DIRECTORY_SEPARATOR.'product-sitemap-urls.json';
        if (! is_file($path)) {
            return $this->syncProductSitemapUrls()['urls'];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ! is_array($decoded['urls'] ?? null)) {
            return $this->syncProductSitemapUrls()['urls'];
        }

        /** @var list<string> $urls */
        $urls = array_values(array_map('strval', $decoded['urls']));

        return $urls;
    }

    /**
     * Audit inclusive 1-based URL indexes.
     *
     * @return array{
     *     batch: string,
     *     from: int,
     *     to: int,
     *     audited: int,
     *     excluded: int,
     *     held: int,
     *     lawful_candidates: int,
     *     access_errors: int,
     *     path: string,
     *     records: list<array<string, mixed>>
     * }
     */
    public function auditRange(int $from, int $to, string $batchLabel, bool $fetchAmbiguousPages = true): array
    {
        if ($from < 1 || $to < $from) {
            throw new RuntimeException('Audit range must be 1-based and ascending.');
        }

        $urls = $this->productUrls();
        $total = count($urls);
        if ($from > $total) {
            throw new RuntimeException('Audit start '.$from.' exceeds sitemap count '.$total.'.');
        }

        $to = min($to, $total);
        $records = [];
        $excluded = 0;
        $held = 0;
        $lawful = 0;
        $accessErrors = 0;

        for ($index = $from; $index <= $to; $index++) {
            $url = $urls[$index - 1];
            $record = $this->auditUrl($index, $url, $fetchAmbiguousPages);
            $records[] = $record;
            if (($record['access_error'] ?? null) !== null) {
                $accessErrors++;
            }
            match ($record['classification']) {
                CatalogueAuditClassification::EXCLUDED_RESTRICTED->value => $excluded++,
                CatalogueAuditClassification::COMPLIANCE_HOLD->value => $held++,
                CatalogueAuditClassification::LAWFUL_CANDIDATE_REVIEW->value => $lawful++,
                default => null,
            };
        }

        $payload = [
            'batch' => $batchLabel,
            'audited_at' => gmdate('c'),
            'from' => $from,
            'to' => $to,
            'sitemap_count' => $total,
            'audited' => count($records),
            'excluded' => $excluded,
            'held' => $held,
            'lawful_candidates' => $lawful,
            'access_errors' => $accessErrors,
            'records' => $records,
        ];

        $safeBatch = preg_replace('/[^a-zA-Z0-9\-]+/', '-', $batchLabel) ?: 'batch';
        $path = $this->auditDirectory.DIRECTORY_SEPARATOR.$safeBatch.'.json';
        $this->writeJson($path, $payload);
        $this->refreshCumulativeSummary();

        return [
            'batch' => $batchLabel,
            'from' => $from,
            'to' => $to,
            'audited' => count($records),
            'excluded' => $excluded,
            'held' => $held,
            'lawful_candidates' => $lawful,
            'access_errors' => $accessErrors,
            'path' => $path,
            'records' => $records,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function auditUrl(int $index, string $url, bool $fetchPageWhenAmbiguous = true): array
    {
        $slug = basename((string) (parse_url($url, PHP_URL_PATH) ?: $url));
        $nameFromSlug = ucwords(str_replace('-', ' ', $slug));
        $seed = [
            'name' => $nameFromSlug,
            'source_url' => $url,
            'source_product_reference' => $slug,
            'category' => '',
            'product_type' => '',
            'format' => '',
        ];

        $slugDecision = $this->classifyFromEvidence($seed, pageFetched: false);
        $record = [
            'index' => $index,
            'source_url' => $url,
            'product_name' => $nameFromSlug,
            'brand' => null,
            'category' => null,
            'active_substances' => $this->inferSubstances($slug.' '.$nameFromSlug),
            'source_sku' => null,
            'source_price' => null,
            'source_currency' => null,
            'classification' => $slugDecision['classification']->value,
            'evidence' => $slugDecision['evidence'],
            'page_fetched' => false,
            'access_error' => null,
            'import_readiness' => null,
        ];

        $needsPage = $fetchPageWhenAmbiguous
            && $slugDecision['classification'] !== CatalogueAuditClassification::EXCLUDED_RESTRICTED;

        // Still fetch a sample of excluded pages only when substance evidence is empty,
        // otherwise keep restricted pages closed when the slug already proves exclusion.
        if ($needsPage) {
            try {
                $html = $this->fetcher->get($url);
                $parsed = $this->parseProductPage($html, $url);
                $record['page_fetched'] = true;
                $record['product_name'] = $parsed['name'] ?? $record['product_name'];
                $record['brand'] = $parsed['brand'] ?? null;
                $record['category'] = $parsed['category'] ?? null;
                $record['source_sku'] = $parsed['sku'] ?? null;
                $record['source_price'] = $parsed['price'] ?? null;
                $record['source_currency'] = $parsed['currency'] ?? 'GBP';
                $record['active_substances'] = array_values(array_unique(array_merge(
                    $record['active_substances'],
                    $this->inferSubstances(implode(' ', [
                        (string) $record['product_name'],
                        (string) ($record['brand'] ?? ''),
                        (string) ($record['category'] ?? ''),
                        $slug,
                    ]))
                )));

                $pageDecision = $this->classifyFromEvidence([
                    'name' => (string) $record['product_name'],
                    'source_url' => $url,
                    'category' => (string) ($record['category'] ?? ''),
                    'brand' => (string) ($record['brand'] ?? ''),
                    'product_type' => '',
                    'format' => '',
                ], pageFetched: true, substances: $record['active_substances']);

                $record['classification'] = $pageDecision['classification']->value;
                $record['evidence'] = array_values(array_unique(array_merge(
                    $record['evidence'],
                    $pageDecision['evidence']
                )));
            } catch (RuntimeException $exception) {
                $record['access_error'] = $exception->getMessage();
                $record['classification'] = CatalogueAuditClassification::COMPLIANCE_HOLD->value;
                $record['evidence'][] = 'Page could not be fetched; held pending accessible evidence.';
            }
        }

        if ($record['classification'] === CatalogueAuditClassification::LAWFUL_CANDIDATE_REVIEW->value) {
            $record['import_readiness'] = $this->importReadiness($record);
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  list<string>  $substances
     * @return array{classification: CatalogueAuditClassification, evidence: list<string>}
     */
    protected function classifyFromEvidence(array $candidate, bool $pageFetched, array $substances = []): array
    {
        $evidence = [];
        $exclusion = $this->detector->excludedFromCommerceImport($candidate);
        if ($exclusion['excluded']) {
            foreach ($exclusion['reasons'] as $reason) {
                $evidence[] = $reason;
            }

            return [
                'classification' => CatalogueAuditClassification::EXCLUDED_RESTRICTED,
                'evidence' => $evidence,
            ];
        }

        $haystack = strtolower(implode(' ', array_filter([
            (string) ($candidate['name'] ?? ''),
            (string) ($candidate['source_url'] ?? ''),
            (string) ($candidate['category'] ?? ''),
            implode(' ', $substances),
        ])));

        foreach ($this->hardExclusionFragments() as $fragment => $reason) {
            if (str_contains($haystack, $fragment)) {
                $evidence[] = $reason;

                return [
                    'classification' => CatalogueAuditClassification::EXCLUDED_RESTRICTED,
                    'evidence' => $evidence,
                ];
            }
        }

        foreach ($this->restrictedCategoryFragments() as $fragment => $reason) {
            if (str_contains($haystack, $fragment)) {
                $evidence[] = $reason;

                return [
                    'classification' => CatalogueAuditClassification::EXCLUDED_RESTRICTED,
                    'evidence' => $evidence,
                ];
            }
        }

        foreach ($this->holdFragments() as $fragment => $reason) {
            if (str_contains($haystack, $fragment)) {
                $evidence[] = $reason;

                return [
                    'classification' => CatalogueAuditClassification::COMPLIANCE_HOLD,
                    'evidence' => $evidence,
                ];
            }
        }

        if (! $pageFetched) {
            $evidence[] = 'Slug alone does not establish a clearly lawful consumer product; page inspection required.';

            return [
                'classification' => CatalogueAuditClassification::COMPLIANCE_HOLD,
                'evidence' => $evidence,
            ];
        }

        if ($this->looksLikeConsumerSupplement($haystack)) {
            $evidence[] = 'Page text is consistent with a consumer supplement or wellness product, but publication gates are incomplete.';

            return [
                'classification' => CatalogueAuditClassification::LAWFUL_CANDIDATE_REVIEW,
                'evidence' => $evidence,
            ];
        }

        $evidence[] = 'Identity remains ambiguous after page inspection; held for qualified review.';

        return [
            'classification' => CatalogueAuditClassification::COMPLIANCE_HOLD,
            'evidence' => $evidence,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, bool|string|null>
     */
    protected function importReadiness(array $record): array
    {
        return [
            'source_identity' => filled($record['product_name'] ?? null) && filled($record['source_url'] ?? null),
            'pack_size_documented' => (bool) preg_match('/\b\d+\s?(tabs?|tablets?|caps?|capsules?|ml|iu|mg)\b/i', (string) ($record['product_name'] ?? '')),
            'source_sku_published' => filled($record['source_sku'] ?? null),
            'price_provenance' => filled($record['source_price'] ?? null) && filled($record['source_currency'] ?? null),
            'brand_mapping' => false,
            'category_mapping' => false,
            'image_rights_reviewed' => false,
            'compliance_evidence_complete' => false,
            'ready_to_stage' => false,
            'notes' => 'Candidate remains non-purchasable until brand/category mapping, image rights, and compliance evidence are completed.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseProductPage(string $html, string $url): array
    {
        $parsed = [
            'source_url' => $url,
            'currency' => 'GBP',
        ];

        if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $heading) === 1) {
            $parsed['name'] = trim(html_entity_decode(strip_tags($heading[1]), ENT_QUOTES));
        }

        if (preg_match('#SKU:\s*([A-Za-z0-9\-_/]+)#i', $html, $sku) === 1) {
            $parsed['sku'] = strtoupper($sku[1]);
        }

        if (preg_match_all('#£\s*([0-9]+(?:\.[0-9]{1,2})?)#', $html, $prices) > 0 && isset($prices[1][0])) {
            $parsed['price'] = $prices[1][0];
        }

        if (preg_match('#class="brand[^"]*"[^>]*>\s*([^<]+)#i', $html, $brand) === 1) {
            $parsed['brand'] = trim(html_entity_decode($brand[1], ENT_QUOTES));
        }

        if (preg_match('#product-category/([^"/]+)#i', $html, $category) === 1) {
            $parsed['category'] = ucwords(str_replace('-', ' ', $category[1]));
        }

        return $parsed;
    }

    /**
     * @return list<string>
     */
    protected function inferSubstances(string $text): array
    {
        $haystack = strtolower($text);
        $found = [];
        foreach ([
            'testosterone', 'boldenone', 'trenbolone', 'drostanolone', 'masteron', 'sustanon',
            'nandrolone', 'oxymetholone', 'anadrol', 'stanozolol', 'winstrol', 'oxandrolone',
            'turinabol', 'methenolone', 'primobolan', 'methyldrostanolone', 'superdrol',
            'methyltrienolone', 'trestolone', 'clenbuterol', 'clomiphene', 'clomid',
            'tamoxifen', 'nolvadex', 'sildenafil', 'kamagra', 'semaglutide', 'liothyronine',
            'levothyroxine', 'pitavastatin', 'yohimbine', 'stenabolic', 'yk11', 'ghk-cu',
            'mots-c', 'tb-500', 'thymosin', 'thymalin', 'mod grf', 'pnc-27', 'somatropin', 'hgh',
        ] as $term) {
            if (str_contains($haystack, $term) || str_contains($haystack, str_replace(' ', '-', $term))) {
                $found[] = $term;
            }
        }

        if (preg_match('/\bt3\b/', $haystack) === 1) {
            $found[] = 'liothyronine/t3';
        }
        if (preg_match('/\bt4\b/', $haystack) === 1) {
            $found[] = 'levothyroxine/t4';
        }

        return array_values(array_unique($found));
    }

    /**
     * @return array<string, string>
     */
    protected function hardExclusionFragments(): array
    {
        return [
            'buy-t3' => 'T3/liothyronine thyroid hormone product excluded from retail onboarding.',
            '/t3-' => 'T3/liothyronine thyroid hormone product excluded from retail onboarding.',
            'thyromed' => 'Thyroid hormone product excluded from retail onboarding.',
            'trestolone' => 'Trestolone anabolic steroid excluded from retail onboarding.',
            'methyltrienolone' => 'Methyltrienolone anabolic steroid excluded from retail onboarding.',
            'yk11' => 'YK-11 SARM/myostatin inhibitor excluded from retail onboarding.',
            'stenabolic' => 'SR9009/stenabolic research compound excluded from retail onboarding.',
            'pitavastatin' => 'Pitavastatin is a prescription medicine and is excluded.',
            'ghk-cu' => 'GHK-Cu peptide excluded from retail onboarding.',
            'mots-c' => 'MOTS-c peptide excluded from retail onboarding.',
            'mod-grf' => 'Growth-hormone-releasing peptide excluded from retail onboarding.',
            'pnc-27' => 'PNC-27 peptide excluded from retail onboarding.',
            'thymalin' => 'Thymalin peptide excluded from retail onboarding.',
            'thymosin' => 'Thymosin peptide excluded from retail onboarding.',
            'qomatropin' => 'HGH/somatropin product excluded from retail onboarding.',
            'hemi-pharma-hgh' => 'HGH product excluded from retail onboarding.',
            'tri-tren' => 'Trenbolone blend excluded from retail onboarding.',
            'clenomed' => 'Clenbuterol product excluded from retail onboarding.',
            'test-mix' => 'Testosterone blend excluded from retail onboarding.',
            'tmp-mix' => 'Anabolic steroid blend excluded from retail onboarding.',
            'testex' => 'Testosterone product excluded from retail onboarding.',
            'testo-cyp' => 'Testosterone cypionate excluded from retail onboarding.',
            'methenolone' => 'Methenolone/primobolan product excluded from retail onboarding.',
            'primomed' => 'Methenolone/primobolan product excluded from retail onboarding.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function restrictedCategoryFragments(): array
    {
        return [
            'oral steroids' => 'Source category identifies an oral steroid catalogue listing; excluded from retail onboarding.',
            'injectable steroids' => 'Source category identifies an injectable steroid catalogue listing; excluded from retail onboarding.',
            'oral-steroid' => 'Source category identifies an oral steroid catalogue listing; excluded from retail onboarding.',
            'injectable-steroid' => 'Source category identifies an injectable steroid catalogue listing; excluded from retail onboarding.',
            'sarms' => 'Source category identifies a SARM catalogue listing; excluded from retail onboarding.',
            'peptides' => 'Source category identifies a peptide catalogue listing; excluded from retail onboarding.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function holdFragments(): array
    {
        return [
            'yohimbine' => 'Yohimbine HCl requires qualified review of lawful retail status, dose presentation, and labelling before onboarding.',
            'ett-500' => 'ETT-500 identity is not established from the slug; held for product-page evidence.',
            'xxx-tabs' => '“XXX Tabs” identity is not established from the slug; held for product-page evidence before any retail decision.',
        ];
    }

    protected function looksLikeConsumerSupplement(string $haystack): bool
    {
        foreach (['vitamin', 'supplement', 'mineral', 'omega', 'creatine', 'collagen', 'probiotic', 'liv52', 'liv.52', 'liver care'] as $term) {
            if (str_contains($haystack, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshCumulativeSummary(): array
    {
        $this->ensureAuditDirectory();
        $files = glob($this->auditDirectory.DIRECTORY_SEPARATOR.'batch-*.json') ?: [];
        sort($files);

        $byUrl = [];
        foreach ($files as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (! is_array($decoded) || ! is_array($decoded['records'] ?? null)) {
                continue;
            }
            foreach ($decoded['records'] as $record) {
                if (! is_array($record) || ! isset($record['source_url'])) {
                    continue;
                }
                $byUrl[(string) $record['source_url']] = $record + [
                    'batch_file' => basename($file),
                ];
            }
        }

        $excluded = 0;
        $held = 0;
        $lawful = 0;
        foreach ($byUrl as $record) {
            match ($record['classification'] ?? null) {
                CatalogueAuditClassification::EXCLUDED_RESTRICTED->value => $excluded++,
                CatalogueAuditClassification::COMPLIANCE_HOLD->value => $held++,
                CatalogueAuditClassification::LAWFUL_CANDIDATE_REVIEW->value => $lawful++,
                default => null,
            };
        }

        $urls = $this->productUrls();
        $auditedIndexes = [];
        foreach ($byUrl as $record) {
            if (isset($record['index'])) {
                $auditedIndexes[] = (int) $record['index'];
            }
        }
        $auditedIndexes = array_values(array_unique($auditedIndexes));
        sort($auditedIndexes);
        $maxAudited = $auditedIndexes === [] ? 0 : max($auditedIndexes);

        $summary = [
            'updated_at' => gmdate('c'),
            'sitemap_count' => count($urls),
            'unique_urls_audited' => count($byUrl),
            'max_index_audited' => $maxAudited,
            'urls_remaining' => max(0, count($urls) - count($byUrl)),
            'excluded' => $excluded,
            'compliance_holds' => $held,
            'lawful_candidates' => $lawful,
            'classification_total' => $excluded + $held + $lawful,
            'reconciles' => ($excluded + $held + $lawful) === count($byUrl),
            'batch_files' => array_map('basename', $files),
            'qa_note' => 'Staged catalogue products are tracked separately in database/qa.sqlite and are not duplicated here.',
        ];

        $path = $this->auditDirectory.DIRECTORY_SEPARATOR.'cumulative-summary.json';
        $this->writeJson($path, $summary);

        return $summary + ['path' => $path];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function heldRecords(): array
    {
        $files = glob($this->auditDirectory.DIRECTORY_SEPARATOR.'batch-*.json') ?: [];
        $held = [];
        foreach ($files as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (! is_array($decoded) || ! is_array($decoded['records'] ?? null)) {
                continue;
            }
            foreach ($decoded['records'] as $record) {
                if (! is_array($record)) {
                    continue;
                }
                if (($record['classification'] ?? null) === CatalogueAuditClassification::COMPLIANCE_HOLD->value) {
                    $held[] = $record + ['batch_file' => basename($file)];
                }
            }
        }

        return $held;
    }

    /**
     * Re-evaluate held audit records in place without duplicating URLs.
     *
     * @return array{reviewed: int, reclassified_excluded: int, remain_held: int, path: string}
     */
    public function reviewHeldRecords(bool $fetchPages = true): array
    {
        $held = $this->heldRecords();
        $reclassified = 0;
        $remain = 0;
        $updatedByBatch = [];

        foreach ($held as $record) {
            $url = (string) ($record['source_url'] ?? '');
            $index = (int) ($record['index'] ?? 0);
            if ($url === '' || $index < 1) {
                continue;
            }
            $fresh = $this->auditUrl($index, $url, $fetchPages);
            $batchFile = (string) ($record['batch_file'] ?? '');
            if ($batchFile === '') {
                continue;
            }
            $updatedByBatch[$batchFile][$url] = $fresh;
            if ($fresh['classification'] === CatalogueAuditClassification::EXCLUDED_RESTRICTED->value) {
                $reclassified++;
            } elseif ($fresh['classification'] === CatalogueAuditClassification::COMPLIANCE_HOLD->value) {
                $remain++;
            } else {
                $remain++;
            }
        }

        foreach ($updatedByBatch as $batchFile => $updates) {
            $path = $this->auditDirectory.DIRECTORY_SEPARATOR.$batchFile;
            if (! is_file($path)) {
                continue;
            }
            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded) || ! is_array($decoded['records'] ?? null)) {
                continue;
            }
            foreach ($decoded['records'] as $i => $existing) {
                $url = (string) ($existing['source_url'] ?? '');
                if (isset($updates[$url])) {
                    $decoded['records'][$i] = $updates[$url];
                }
            }
            $decoded['held_review_at'] = gmdate('c');
            $this->recountBatchPayload($decoded);
            $this->writeJson($path, $decoded);
        }

        $reviewPath = $this->auditDirectory.DIRECTORY_SEPARATOR.'held-review-batch-5.json';
        $this->writeJson($reviewPath, [
            'reviewed_at' => gmdate('c'),
            'reviewed' => count($held),
            'reclassified_excluded' => $reclassified,
            'remain_held' => $remain,
            'note' => 'Prior held audit records were re-evaluated from product-level evidence without creating duplicates.',
        ]);
        $this->refreshCumulativeSummary();

        return [
            'reviewed' => count($held),
            'reclassified_excluded' => $reclassified,
            'remain_held' => $remain,
            'path' => $reviewPath,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recountBatchPayload(array &$payload): void
    {
        $excluded = 0;
        $held = 0;
        $lawful = 0;
        foreach ($payload['records'] as $record) {
            match ($record['classification'] ?? null) {
                CatalogueAuditClassification::EXCLUDED_RESTRICTED->value => $excluded++,
                CatalogueAuditClassification::COMPLIANCE_HOLD->value => $held++,
                CatalogueAuditClassification::LAWFUL_CANDIDATE_REVIEW->value => $lawful++,
                default => null,
            };
        }
        $payload['excluded'] = $excluded;
        $payload['held'] = $held;
        $payload['lawful_candidates'] = $lawful;
        $payload['audited'] = count($payload['records']);
    }

    protected function ensureAuditDirectory(): void
    {
        if (! is_dir($this->auditDirectory) && ! mkdir($this->auditDirectory, 0775, true) && ! is_dir($this->auditDirectory)) {
            throw new RuntimeException('Unable to create audit directory: '.$this->auditDirectory);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function writeJson(string $path, array $payload): void
    {
        $this->ensureAuditDirectory();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($path, $json.PHP_EOL) === false) {
            throw new RuntimeException('Unable to write audit file: '.$path);
        }
    }
}
