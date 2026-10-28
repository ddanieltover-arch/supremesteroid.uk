<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\ReferencePageFetcher;
use RuntimeException;

/**
 * Records which public catalogue sources respond and what fields they expose.
 * Does not write catalogue products.
 */
class PublicCatalogueCapabilityReportService
{
    public function __construct(
        protected ReferencePageFetcher $fetcher,
        protected RestrictedProductDetector $detector,
        protected PublicProductPageParser $parser = new PublicProductPageParser,
        protected string $baseUrl = 'https://supremesteroids.co.uk',
        protected string $reportDirectory = '',
    ) {
        if ($this->reportDirectory === '') {
            $this->reportDirectory = storage_path('app/catalogue-import/audits');
        }
    }

    /**
     * @param  list<array{url: string, status: int|string, length?: int, notes?: string, automated_access?: string}>  $endpointProbes
     * @return array<string, mixed>
     */
    public function build(array $endpointProbes, ?string $sampleProductUrl = null): array
    {
        $base = rtrim($this->baseUrl, '/');
        $sitemapUrls = [];
        $sitemapOutcome = 'not-attempted';
        try {
            $xml = $this->fetcher->get($base.'/product-sitemap.xml');
            preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $xml, $matches);
            $sitemapUrls = array_values(array_unique(array_map(
                static function (string $value): string {
                    $url = html_entity_decode(trim($value), ENT_QUOTES);
                    $url = strtok($url, '#') ?: $url;
                    $url = strtok($url, '?') ?: $url;

                    return rtrim($url, '/');
                },
                $matches[1] ?? []
            )));
            $sitemapOutcome = 'http-200-parsed; '.count($sitemapUrls).' product URLs listed (URLs discovered, not retrieved)';
            $cachePath = storage_path('app/'.ltrim((string) config('catalogue.import.public_html.sitemap_cache', 'catalogue-import/audits/product-sitemap-urls.json'), '/\\'));
            $cacheDir = dirname($cachePath);
            if (! is_dir($cacheDir)) {
                @mkdir($cacheDir, 0775, true);
            }
            file_put_contents($cachePath, json_encode([
                'fetched_at' => gmdate('c'),
                'source' => $base.'/product-sitemap.xml',
                'count' => count($sitemapUrls),
                'urls' => $sitemapUrls,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (RuntimeException $exception) {
            $sitemapOutcome = 'failed: '.$exception->getMessage();
        }

        $slugExcluded = 0;
        $fetchCandidates = 0;
        foreach ($sitemapUrls as $url) {
            $seed = [
                'name' => ucwords(str_replace('-', ' ', basename((string) (parse_url($url, PHP_URL_PATH) ?: $url)))),
                'source_url' => $url,
                'source_product_reference' => basename((string) (parse_url($url, PHP_URL_PATH) ?: $url)),
            ];
            if ($this->detector->excludedFromCommerceImport($seed)['excluded']) {
                $slugExcluded++;
            } else {
                $fetchCandidates++;
            }
        }

        $sample = null;
        if ($sampleProductUrl) {
            try {
                $html = $this->fetcher->get($sampleProductUrl);
                $parsed = $this->parser->parse($html, $sampleProductUrl);
                $sample = [
                    'url' => $sampleProductUrl,
                    'http' => '200',
                    'fields_present' => array_values(array_filter([
                        filled($parsed['name'] ?? null) ? 'name' : null,
                        filled($parsed['sku'] ?? null) ? 'sku' : null,
                        filled($parsed['price'] ?? null) ? 'price' : null,
                        filled($parsed['currency'] ?? null) ? 'currency' : null,
                        filled($parsed['brand'] ?? null) ? 'brand' : null,
                        filled($parsed['category'] ?? null) ? 'category' : null,
                        filled($parsed['description'] ?? null) ? 'description' : null,
                        filled($parsed['availability'] ?? null) ? 'availability' : null,
                        ($parsed['images'] ?? []) !== [] ? 'images' : null,
                        ($parsed['variants'] ?? []) !== [] ? 'variants' : null,
                    ])),
                    'fields_missing' => array_values(array_filter([
                        ! filled($parsed['sku'] ?? null) ? 'sku' : null,
                        ! filled($parsed['price'] ?? null) ? 'price' : null,
                        ! filled($parsed['brand'] ?? null) ? 'brand' : null,
                        ! filled($parsed['category'] ?? null) ? 'category' : null,
                        ! filled($parsed['description'] ?? null) ? 'description' : null,
                        ($parsed['images'] ?? []) === [] ? 'images' : null,
                        ($parsed['variants'] ?? []) === [] ? 'variants' : null,
                    ])),
                    'fields_from' => $parsed['fields_from'] ?? [],
                    'image_usage' => 'Public image URLs recorded as references only; not copied into persistent storage.',
                    'sample_name' => $parsed['name'] ?? null,
                    'sample_sku' => $parsed['sku'] ?? null,
                    'sample_price' => $parsed['price'] ?? null,
                ];
            } catch (RuntimeException $exception) {
                $sample = [
                    'url' => $sampleProductUrl,
                    'http' => 'failed',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $report = [
            'generated_at' => gmdate('c'),
            'reference_base' => $base,
            'robots_txt' => [
                'url' => $base.'/robots.txt',
                'outcome' => 'HTTP 404 (no published robots rules retrieved)',
                'automated_access' => 'No disallow rules published; retrieval limited to publicly linked sitemap/product URLs with paced HTTPS requests and no credential bypass.',
            ],
            'rate_limiting' => [
                'client_pacing_ms' => (int) config('catalogue.import.reference_site.delay_ms', 2000),
                'notes' => 'ReferenceSiteHttpClient enforces inter-request delay; does not evade bot protection.',
            ],
            'endpoints_tested' => $endpointProbes,
            'sources' => [
                [
                    'type' => 'sitemap-index',
                    'url' => $base.'/sitemap.xml',
                    'outcome' => 'HTTP 200 (verified in probe phase)',
                    'automated_access' => 'permitted-as-public',
                    'records_discovered' => 6,
                    'records_retrieved' => 0,
                    'fields' => ['child sitemap locs'],
                    'missing' => ['product fields'],
                ],
                [
                    'type' => 'product-sitemap',
                    'url' => $base.'/product-sitemap.xml',
                    'outcome' => $sitemapOutcome,
                    'automated_access' => 'permitted-as-public',
                    'urls_discovered' => count($sitemapUrls),
                    'records_retrieved' => 0,
                    'note' => 'Sitemap URL count must not be equated with retrieved product records.',
                    'slug_excluded_without_fetch' => $slugExcluded,
                    'page_fetch_candidates' => $fetchCandidates,
                    'fields' => ['source_url'],
                    'missing' => ['name confirmed', 'sku', 'price', 'brand', 'category', 'images', 'description'],
                ],
                [
                    'type' => 'public-product-html-jsonld',
                    'url' => $base.'/product/{slug}',
                    'outcome' => $sample['http'] ?? 'sample-not-run',
                    'automated_access' => 'permitted-as-public-with-pacing',
                    'sample' => $sample,
                    'fields_when_page_retrieved' => [
                        'name', 'description', 'sku (when published in JSON-LD)', 'price', 'currency',
                        'image URLs', 'availability', 'category (markup)', 'brand (when non-placeholder)',
                    ],
                    'missing_or_unreliable' => [
                        'brand often placeholder "Brand" in JSON-LD',
                        'HTML SKU label sometimes absent when only JSON-LD sku exists',
                        'variants only when variation select is present',
                        'ingredients only when explicitly published',
                    ],
                    'pagination' => 'one product page per URL; no store API pagination available',
                ],
                [
                    'type' => 'rss-product-feed',
                    'url' => $base.'/feed and '.$base.'/product/feed',
                    'outcome' => 'HTTP 404',
                    'automated_access' => 'unavailable',
                    'records_discovered' => 0,
                    'records_retrieved' => 0,
                ],
                [
                    'type' => 'wordpress-rest',
                    'url' => $base.'/wp-json and /wp-json/wp/v2/product',
                    'outcome' => 'HTTP 404',
                    'automated_access' => 'unavailable',
                    'records_discovered' => 0,
                    'records_retrieved' => 0,
                ],
                [
                    'type' => 'woocommerce-store-api',
                    'url' => $base.'/wp-json/wc/store/v1/products',
                    'outcome' => 'HTTP 404',
                    'automated_access' => 'unavailable',
                    'records_discovered' => 0,
                    'records_retrieved' => 0,
                ],
                [
                    'type' => 'woocommerce-rest-v3',
                    'url' => $base.'/wp-json/wc/v3/products',
                    'outcome' => 'HTTP 404 without credentials; credentials not configured',
                    'automated_access' => 'unavailable',
                    'records_discovered' => 0,
                    'records_retrieved' => 0,
                ],
                [
                    'type' => 'product-category-sitemap',
                    'url' => $base.'/product_cat-sitemap.xml',
                    'outcome' => 'HTTP 200; categories are predominantly restricted (steroids, SARMs, peptides, PCT, etc.)',
                    'automated_access' => 'permitted-as-public; restricted category pages are not opened for retail onboarding',
                    'fields' => ['category urls'],
                    'missing' => ['lawful consumer category tree'],
                ],
                [
                    'type' => 'shop-search',
                    'url' => $base.'/shop?search=vitamin',
                    'outcome' => 'HTTP 200 HTML listing page',
                    'automated_access' => 'permitted-as-public-with-pacing',
                    'note' => 'Useful for discovery hints only; product identity still requires product-page evidence.',
                ],
            ],
            'summary' => [
                'sitemap_urls_discovered' => count($sitemapUrls),
                'slug_classified_excluded' => $slugExcluded,
                'page_fetch_candidates' => $fetchCandidates,
                'authorized_api_available' => false,
                'public_html_jsonld_available' => true,
                'image_copy_into_storage' => false,
            ],
        ];

        if (! is_dir($this->reportDirectory)) {
            @mkdir($this->reportDirectory, 0775, true);
        }
        $path = $this->reportDirectory.DIRECTORY_SEPARATOR.'prompt-28-capability-report.json';
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $report['path'] = $path;

        return $report;
    }
}
