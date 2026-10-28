<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\ReferencePageFetcher;
use App\Contracts\Catalogue\ResumableCatalogueSourceReader;
use RuntimeException;

/**
 * Resumable public HTML / JSON-LD catalogue source.
 *
 * Walks publicly linked product sitemap URLs. Clearly restricted slugs are
 * emitted without opening the page. Ambiguous and potentially eligible pages
 * are fetched with the paced ReferencePageFetcher. Does not bypass access
 * controls or fabricate missing fields.
 */
class PublicHtmlCatalogueSourceReader implements ResumableCatalogueSourceReader
{
    protected int $offset = 0;

    protected int $discovered = 0;

    protected int $retrieved = 0;

    protected int $pagesFetched = 0;

    protected int $slugOnlyExcluded = 0;

    protected int $retrievalFailures = 0;

    protected bool $exhausted = false;

    protected ?string $lastError = null;

    /** @var list<string> */
    protected array $urls = [];

    protected bool $urlsLoaded = false;

    public function __construct(
        protected ReferencePageFetcher $fetcher,
        protected RestrictedProductDetector $detector,
        protected PublicProductPageParser $parser = new PublicProductPageParser,
        protected string $baseUrl = 'https://supremesteroids.co.uk',
        protected ?string $sitemapUrlCachePath = null,
        protected bool $fetchRestrictedPages = false,
        protected int $maxPageFetches = 100,
    ) {
        if ($this->sitemapUrlCachePath === null) {
            $this->sitemapUrlCachePath = storage_path('app/catalogue-import/audits/product-sitemap-urls.json');
        }
    }

    public function sourceType(): string
    {
        return 'public-html';
    }

    public function sourceName(): string
    {
        return 'public-html';
    }

    public function records(): iterable
    {
        $this->ensureUrls();
        $total = count($this->urls);
        $this->discovered = $total;

        while ($this->offset < $total) {
            if ($this->pagesFetched >= $this->maxPageFetches && $this->wouldNeedFetch($this->urls[$this->offset])) {
                // Pause before opening further pages so the run remains resumable.
                break;
            }

            $url = $this->urls[$this->offset];
            $this->offset++;
            $record = $this->buildRecord($url);
            $this->retrieved++;
            yield $record;
        }

        $this->exhausted = $this->offset >= $total;
    }

    public function checkpoint(): array
    {
        return [
            'offset' => $this->offset,
            'pages_fetched' => $this->pagesFetched,
            'retrieved' => $this->retrieved,
            'discovered' => $this->discovered ?: count($this->urls),
        ];
    }

    public function restore(array $checkpoint): void
    {
        $this->offset = max(0, (int) ($checkpoint['offset'] ?? 0));
        $this->pagesFetched = max(0, (int) ($checkpoint['pages_fetched'] ?? 0));
        $this->retrieved = max(0, (int) ($checkpoint['retrieved'] ?? 0));
        $this->discovered = max(0, (int) ($checkpoint['discovered'] ?? 0));
        $this->exhausted = false;
    }

    /**
     * @return array<string, int|string|bool|null>
     */
    public function retrievalStats(): array
    {
        $this->ensureUrls();

        return [
            'discovered' => $this->discovered ?: count($this->urls),
            'retrieved' => $this->retrieved,
            'pages_fetched' => $this->pagesFetched,
            'slug_only_excluded' => $this->slugOnlyExcluded,
            'retrieval_failures' => $this->retrievalFailures,
            'exhausted' => $this->exhausted,
            'offset' => $this->offset,
            'last_error' => $this->lastError,
            'sitemap_urls' => count($this->urls),
        ];
    }

    /**
     * @return list<string>
     */
    public function urls(): array
    {
        $this->ensureUrls();

        return $this->urls;
    }

    protected function ensureUrls(): void
    {
        if ($this->urlsLoaded) {
            return;
        }

        $this->urlsLoaded = true;
        $cached = $this->loadCachedUrls();
        if ($cached !== []) {
            $this->urls = $cached;
            $this->discovered = count($this->urls);

            return;
        }

        $sitemapUrl = rtrim($this->baseUrl, '/').'/product-sitemap.xml';
        try {
            $xml = $this->fetcher->get($sitemapUrl);
        } catch (RuntimeException $exception) {
            $this->lastError = $exception->getMessage();
            $this->retrievalFailures++;
            $this->urls = [];
            $this->exhausted = true;

            return;
        }

        preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $xml, $matches);
        $urls = [];
        foreach ($matches[1] ?? [] as $value) {
            $url = html_entity_decode(trim($value), ENT_QUOTES);
            $url = strtok($url, '#') ?: $url;
            $url = strtok($url, '?') ?: $url;
            $url = rtrim($url, '/');
            if (str_contains(strtolower($url), '/product/')) {
                $urls[] = $url;
            }
        }
        $this->urls = array_values(array_unique($urls));
        $this->discovered = count($this->urls);
        $this->persistCachedUrls($this->urls, $sitemapUrl);
    }

    /**
     * @return list<string>
     */
    protected function loadCachedUrls(): array
    {
        $path = (string) $this->sitemapUrlCachePath;
        if ($path === '' || ! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ! is_array($decoded['urls'] ?? null)) {
            return [];
        }

        return array_values(array_map('strval', $decoded['urls']));
    }

    /**
     * @param  list<string>  $urls
     */
    protected function persistCachedUrls(array $urls, string $source): void
    {
        $path = (string) $this->sitemapUrlCachePath;
        if ($path === '') {
            return;
        }
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents($path, json_encode([
            'fetched_at' => gmdate('c'),
            'source' => $source,
            'count' => count($urls),
            'urls' => $urls,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function wouldNeedFetch(string $url): bool
    {
        $seed = $this->candidateFromUrl($url);
        $exclusion = $this->detector->excludedFromCommerceImport($seed);
        if ($exclusion['excluded'] && ! $this->fetchRestrictedPages) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRecord(string $url): array
    {
        $seed = $this->candidateFromUrl($url);
        $exclusion = $this->detector->excludedFromCommerceImport($seed);

        if ($exclusion['excluded'] && ! $this->fetchRestrictedPages) {
            $this->slugOnlyExcluded++;
            $seed['page_fetched'] = false;
            $seed['retrieval_mode'] = 'slug-only-excluded';
            $seed['bulk_commerce_exclusion'] = true;
            $seed['bulk_exclusion_reasons'] = $exclusion['reasons'];
            $seed['requires_compliance_review'] = true;
            $seed['source_fields_available'] = ['name', 'source_url', 'source_product_reference'];
            $seed['source_fields_missing'] = ['sku', 'price', 'brand', 'category', 'description', 'images', 'variants'];

            return $seed;
        }

        try {
            $html = $this->fetcher->get($url);
            $this->pagesFetched++;
        } catch (RuntimeException $exception) {
            $this->retrievalFailures++;
            $this->lastError = $exception->getMessage();
            $seed['page_fetched'] = false;
            $seed['retrieval_mode'] = 'access-error';
            $seed['access_error'] = $exception->getMessage();
            $seed['requires_compliance_review'] = true;
            $seed['source_fields_available'] = ['name', 'source_url', 'source_product_reference'];
            $seed['source_fields_missing'] = ['sku', 'price', 'brand', 'category', 'description', 'images', 'variants'];

            return $seed;
        }

        $parsed = $this->parser->parse($html, $url);
        $record = array_merge($seed, array_filter([
            'name' => $parsed['name'] ?? null,
            'description' => $parsed['description'] ?? null,
            'sku' => $parsed['sku'] ?? null,
            'brand' => $parsed['brand'] ?? null,
            'category' => $parsed['category'] ?? null,
            'price' => $parsed['price'] ?? null,
            'compare_at_price' => $parsed['compare_at_price'] ?? null,
            'currency' => $parsed['currency'] ?? 'GBP',
            'availability' => $parsed['availability'] ?? null,
            'images' => $parsed['images'] ?? [],
            'variants' => $parsed['variants'] ?? [],
        ], static fn ($value) => $value !== null && $value !== ''));

        $record['page_fetched'] = true;
        $record['retrieval_mode'] = 'public-html-jsonld';
        $record['fields_from'] = $parsed['fields_from'] ?? [];
        $record['image_usage_rights'] = 'reference-url-only';
        $record['source_fields_available'] = $this->availableFields($record);
        $record['source_fields_missing'] = $this->missingFields($record);

        // Exclusion checks use identity fields only — not marketing description text that may
        // mention the shop name or neighbouring restricted catalogue chrome.
        $identityForExclusion = [
            'name' => (string) ($record['name'] ?? ''),
            'source_url' => $url,
            'source_product_reference' => (string) ($record['source_product_reference'] ?? ''),
            'category' => (string) ($record['category'] ?? ''),
            'brand' => (string) ($record['brand'] ?? ''),
            'product_type' => '',
            'format' => '',
        ];
        $pageExclusion = $this->detector->excludedFromCommerceImport($identityForExclusion);
        $consumerLike = $this->looksLikeConsumerCandidate($record);

        if ($pageExclusion['excluded'] && $consumerLike) {
            $record['requires_compliance_review'] = true;
            $record['defer_commerce_exclusion'] = true;
            $record['compliance_hold_reason'] = 'Consumer-like product identity conflicts with restricted source signals; held for qualified compliance review. Staging does not approve sale.';
            $record['conflict_exclusion_reasons'] = $pageExclusion['reasons'];
            // Neutralize conflicting source category so soft holds are not re-hard-excluded downstream.
            $record['category'] = 'Compliance Hold';
            $record['source_category'] = 'Compliance Hold';
        } elseif ($pageExclusion['excluded']) {
            $record['bulk_commerce_exclusion'] = true;
            $record['bulk_exclusion_reasons'] = $pageExclusion['reasons'];
            $record['requires_compliance_review'] = true;
        } elseif (! $consumerLike) {
            $record['requires_compliance_review'] = true;
            $record['compliance_hold_reason'] = 'Identity remains ambiguous after public page inspection; held for qualified review.';
        }

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    protected function candidateFromUrl(string $url): array
    {
        $slug = basename((string) (parse_url($url, PHP_URL_PATH) ?: $url));

        return [
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'source_url' => $url,
            'source_product_reference' => $slug,
            'currency' => 'GBP',
            'images' => [],
            'variants' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function looksLikeConsumerCandidate(array $record): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            (string) ($record['name'] ?? ''),
            (string) ($record['source_url'] ?? ''),
            (string) ($record['category'] ?? ''),
            (string) ($record['description'] ?? ''),
        ])));

        foreach ([
            'vitamin', 'supplement', 'mineral', 'magnesium', 'zinc', 'creatine', 'omega',
            'whey', 'collagen', 'probiotic', 'liv52', 'liv.52', 'liv-52', 'liver care',
            'herbal', 'botanical', 'tudca',
        ] as $term) {
            if (str_contains($haystack, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    protected function availableFields(array $record): array
    {
        $fields = [];
        foreach (['name', 'source_url', 'source_product_reference', 'sku', 'brand', 'category', 'price', 'currency', 'description', 'availability'] as $key) {
            if (filled($record[$key] ?? null)) {
                $fields[] = $key;
            }
        }
        if (($record['images'] ?? []) !== []) {
            $fields[] = 'images';
        }
        if (($record['variants'] ?? []) !== []) {
            $fields[] = 'variants';
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    protected function missingFields(array $record): array
    {
        $missing = [];
        foreach (['sku', 'brand', 'category', 'price', 'description'] as $key) {
            if (! filled($record[$key] ?? null)) {
                $missing[] = $key;
            }
        }
        if (($record['images'] ?? []) === []) {
            $missing[] = 'images';
        }
        if (($record['variants'] ?? []) === []) {
            $missing[] = 'variants';
        }

        return $missing;
    }
}
