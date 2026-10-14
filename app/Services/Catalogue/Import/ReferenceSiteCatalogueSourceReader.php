<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\CatalogueSourceReader;
use App\Contracts\Catalogue\ReferencePageFetcher;
use RuntimeException;

/**
 * Public reference-site adapter.
 *
 * Inspects homepage navigation, the public shop search form, and sitemap
 * references for ordinary supplements, vitamins, and accessories. Restricted
 * category and product pages are not opened. Image URLs are references only.
 */
class ReferenceSiteCatalogueSourceReader implements CatalogueSourceReader
{
    protected bool $discovered = false;

    /** @var list<array<string, mixed>> */
    protected array $eligible = [];

    /** @var array<string, int|string> */
    protected array $stats = [
        'pages_inspected' => 0,
        'categories_discovered' => 0,
        'categories_excluded' => 0,
        'product_sitemap_urls' => 0,
        'product_candidates_discovered' => 0,
        'eligible_for_staging' => 0,
        'excluded_candidates' => 0,
        'access_errors' => 0,
        'parsing_errors' => 0,
    ];

    /** @var list<string> */
    protected array $exclusionNotes = [];

    /** @var list<string> */
    protected array $inspectedUrls = [];

    /** @var list<string> */
    protected array $confirmedCategoryUrls = [];

    /** @var array<string, true> */
    protected array $seenProductUrls = [];

    public function __construct(
        protected ReferencePageFetcher $fetcher,
        protected RestrictedProductDetector $detector,
        protected string $baseUrl = 'https://supremesteroids.co.uk',
        protected int $maxPages = 30,
        protected int $maxEligible = 20,
    ) {}

    public function sourceType(): string
    {
        return 'reference-site';
    }

    public function sourceName(): string
    {
        return 'reference-site';
    }

    public function records(): iterable
    {
        $this->discover();

        foreach ($this->eligible as $record) {
            yield $record;
        }
    }

    /**
     * @return array<string, int|string>
     */
    public function discoveryStats(): array
    {
        $this->discover();

        return $this->stats;
    }

    /**
     * @return list<string>
     */
    public function discoveryLines(): array
    {
        $stats = $this->discoveryStats();
        $lines = [
            'Pages inspected: '.$stats['pages_inspected'],
            'Confirmed supplement/vitamin/accessory category URLs: '.($this->confirmedCategoryUrls === [] ? 'none' : implode(', ', $this->confirmedCategoryUrls)),
            'Categories discovered: '.$stats['categories_discovered'],
            'Categories excluded before product fetch: '.$stats['categories_excluded'],
            'Product sitemap URLs: '.$stats['product_sitemap_urls'],
            'Product candidates discovered: '.$stats['product_candidates_discovered'],
            'Eligible for staging: '.$stats['eligible_for_staging'],
            'Excluded candidates: '.$stats['excluded_candidates'],
            'Incomplete records: '.$stats['parsing_errors'],
            'Access errors: '.$stats['access_errors'],
            'Parsing errors: '.$stats['parsing_errors'],
            'Endpoints inspected:',
            ...$this->inspectedUrls,
        ];

        foreach ($this->exclusionNotes as $note) {
            $lines[] = $note;
        }

        return $lines;
    }

    protected function discover(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;
        $base = rtrim($this->baseUrl, '/');

        $this->inspectHomepage($base);
        $this->inspectPublicSearch($base);
        $this->inspectStoreApi($base);

        try {
            $index = $this->fetchPage($base.'/sitemap.xml');
        } catch (RuntimeException $exception) {
            $this->stats['access_errors']++;
            $this->exclusionNotes[] = 'Stopped: '.$exception->getMessage();

            return;
        }

        $this->inspectProductSitemap($index, $base);

        $categorySitemap = $this->categorySitemapUrl($index, $base);
        if ($categorySitemap === null) {
            $this->stats['parsing_errors']++;
            $this->exclusionNotes[] = 'Public sitemap did not list a product category sitemap.';

            return;
        }

        try {
            $categoryXml = $this->fetchPage($categorySitemap);
        } catch (RuntimeException $exception) {
            $this->stats['access_errors']++;
            $this->exclusionNotes[] = 'Stopped: '.$exception->getMessage();

            return;
        }

        $categoryUrls = $this->locs($categoryXml);
        $this->stats['categories_discovered'] = count($categoryUrls);

        foreach ($categoryUrls as $categoryUrl) {
            if (count($this->eligible) >= $this->maxEligible) {
                break;
            }

            $categoryUrl = $this->canonicalUrl($categoryUrl);
            $reason = $this->categoryExclusionReason($categoryUrl);
            if ($reason !== null) {
                $this->stats['categories_excluded']++;
                continue;
            }

            $this->collectAllowlistedCategory($categoryUrl);
        }

        if ($this->stats['categories_discovered'] > 0) {
            $this->exclusionNotes[] = 'Product category sitemap entries were classified from their URLs. Restricted category pages were not opened.';
        }

        if ($this->stats['eligible_for_staging'] === 0) {
            $this->exclusionNotes[] = 'No clearly eligible consumer supplement, vitamin, or accessory was found. Restricted and ambiguous pages were not opened, and no product records were fabricated.';
        }
    }

    protected function inspectHomepage(string $base): void
    {
        $html = $this->fetchOptional($base.'/');
        if ($html === null) {
            return;
        }

        $allowlisted = [];
        $restricted = 0;
        foreach ($this->categoryLinks($html, $base) as $categoryUrl) {
            if ($this->categoryExclusionReason($categoryUrl) === null) {
                $allowlisted[] = $categoryUrl;
                continue;
            }
            $restricted++;
        }

        if ($allowlisted === []) {
            $this->exclusionNotes[] = 'Homepage navigation did not include a public supplements, vitamins, or accessories category link. Restricted navigation links were not opened: '.$restricted.'.';
        }

        foreach ($allowlisted as $categoryUrl) {
            $this->collectAllowlistedCategory($categoryUrl);
        }

        foreach ($this->parseObservedProducts($html, $base.'/') as $candidate) {
            $this->considerListingCandidate($candidate);
        }
    }

    protected function inspectPublicSearch(string $base): void
    {
        $loaded = 0;
        $nameHits = 0;
        foreach (['vitamin', 'supplement', 'mineral'] as $term) {
            $html = $this->fetchOptional($base.'/shop?search='.rawurlencode($term));
            if ($html === null) {
                continue;
            }
            $loaded++;
            foreach ($this->parseObservedProducts($html, $base.'/shop') as $candidate) {
                if (str_contains(strtolower((string) ($candidate['name'] ?? '')), $term)) {
                    $nameHits++;
                }
                $this->considerListingCandidate($candidate);
            }
        }

        if ($loaded > 0 && $nameHits === 0) {
            $this->exclusionNotes[] = 'Public shop search (GET /shop?search=) did not return product names containing vitamin, supplement, or mineral. Restricted listing titles were not opened.';
        }
    }

    protected function inspectStoreApi(string $base): void
    {
        $url = $base.'/wp-json/wc/store/v1/products?search=vitamin';
        $body = $this->fetchOptional($url);
        if ($body === null) {
            $this->exclusionNotes[] = 'Public WooCommerce store API search was not available.';

            return;
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            $this->stats['parsing_errors']++;
            $this->exclusionNotes[] = 'Public store API response was not a product list.';

            return;
        }

        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $permalink = (string) ($row['permalink'] ?? '');
            if ($name === '' || $permalink === '') {
                continue;
            }
            $this->considerListingCandidate([
                'name' => html_entity_decode($name, ENT_QUOTES),
                'source_url' => $permalink,
                'source_product_reference' => basename((string) (parse_url($permalink, PHP_URL_PATH) ?: $permalink)),
                'currency' => 'GBP',
                'images' => [],
            ]);
        }
    }

    protected function inspectProductSitemap(string $indexXml, string $base): void
    {
        $sitemaps = [];
        foreach ($this->locs($indexXml) as $loc) {
            if (str_contains($loc, 'product-sitemap') && ! str_contains($loc, 'product_cat-sitemap')) {
                $sitemaps[] = $loc;
            }
        }

        if ($sitemaps === []) {
            $this->exclusionNotes[] = 'Sitemap index did not list a product sitemap.';

            return;
        }

        foreach ($sitemaps as $sitemapUrl) {
            try {
                $xml = $this->fetchPage($sitemapUrl);
            } catch (RuntimeException) {
                $this->stats['access_errors']++;
                $this->exclusionNotes[] = 'Unavailable: '.$sitemapUrl;
                continue;
            }

            $productUrls = array_values(array_filter(
                $this->locs($xml),
                static fn (string $loc): bool => str_contains(strtolower($loc), '/product/')
            ));
            $this->stats['product_sitemap_urls'] += count($productUrls);

            foreach ($productUrls as $productUrl) {
                $path = strtolower((string) (parse_url($productUrl, PHP_URL_PATH) ?? ''));
                if ($this->deniedPathReason($path) !== null) {
                    continue;
                }
                if ($this->containsAny($path, $this->productClearFragments()) || $this->containsAny($path, $this->ambiguousConsumerFragments())) {
                    if ($this->containsAny($path, $this->ambiguousConsumerFragments()) && ! $this->containsAny($path, $this->productClearFragments())) {
                        $this->exclusionNotes[] = 'Product sitemap reference excluded without opening the page: '.$productUrl;
                    }
                    $this->considerListingCandidate($this->candidateFromUrl($productUrl));
                }
            }
        }

        if ((int) $this->stats['product_sitemap_urls'] > 0) {
            $this->exclusionNotes[] = 'Restricted product sitemap URLs were not opened. Only clearly eligible supplement, vitamin, or accessory URLs are fetched.';
        }
    }

    protected function collectAllowlistedCategory(string $categoryUrl): void
    {
        if (in_array($categoryUrl, $this->confirmedCategoryUrls, true)) {
            return;
        }

        $this->confirmedCategoryUrls[] = $categoryUrl;

        try {
            $html = $this->fetchPage($categoryUrl);
        } catch (RuntimeException) {
            $this->stats['access_errors']++;

            return;
        }

        $categoryName = $this->categoryNameFromUrl($categoryUrl);
        foreach ($this->parseListing($html, $categoryUrl) as $candidate) {
            $candidate['category'] = $candidate['category'] ?? $categoryName;
            $this->considerListingCandidate($candidate);
        }
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    protected function considerListingCandidate(array $candidate): void
    {
        $url = $this->canonicalUrl((string) ($candidate['source_url'] ?? ''));
        if ($url === '' || isset($this->seenProductUrls[$url])) {
            return;
        }

        $this->seenProductUrls[$url] = true;
        if (count($this->eligible) >= $this->maxEligible) {
            return;
        }

        $candidate['source_url'] = $url;
        $this->stats['product_candidates_discovered']++;

        if (! filled($candidate['name'] ?? null)) {
            $this->stats['excluded_candidates']++;
            $this->stats['parsing_errors']++;

            return;
        }

        if ($this->listingExclusionReason($candidate) !== null) {
            $this->stats['excluded_candidates']++;

            return;
        }

        $detail = $this->enrichFromProductPage($candidate);
        if ($this->listingExclusionReason($detail) !== null) {
            $this->stats['excluded_candidates']++;

            return;
        }

        $this->eligible[] = $detail;
        $this->stats['eligible_for_staging']++;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    protected function enrichFromProductPage(array $candidate): array
    {
        $url = (string) $candidate['source_url'];
        try {
            $html = $this->fetchPage($url);
        } catch (RuntimeException) {
            $this->stats['access_errors']++;

            return $candidate;
        }

        $parsed = $this->parseProductPage($html, $url);
        if (filled($parsed['name'] ?? null)) {
            $candidate['name'] = $parsed['name'];
        }
        foreach (['sku', 'brand', 'category', 'price', 'compare_at_price', 'currency', 'availability', 'images', 'variants'] as $key) {
            if (! filled($candidate[$key] ?? null) && filled($parsed[$key] ?? null)) {
                $candidate[$key] = $parsed[$key];
            }
        }

        return $candidate;
    }

    protected function fetchPage(string $url): string
    {
        if ($this->stats['pages_inspected'] >= $this->maxPages) {
            throw new RuntimeException('Reference page cap reached ('.$this->maxPages.').');
        }

        $this->stats['pages_inspected']++;
        $this->inspectedUrls[] = $url;

        return $this->fetcher->get($url);
    }

    protected function fetchOptional(string $url): ?string
    {
        try {
            return $this->fetchPage($url);
        } catch (RuntimeException $exception) {
            $this->stats['access_errors']++;
            $this->exclusionNotes[] = 'Unavailable: '.$url.' ('.$exception->getMessage().')';

            return null;
        }
    }

    protected function categorySitemapUrl(string $indexXml, string $base): ?string
    {
        foreach ($this->locs($indexXml) as $loc) {
            if (str_contains($loc, 'product_cat-sitemap')) {
                return $loc;
            }
        }

        $fallback = $base.'/product_cat-sitemap.xml';

        return str_contains($indexXml, 'sitemapindex') ? $fallback : null;
    }

    /**
     * @return list<string>
     */
    protected function locs(string $xml): array
    {
        preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $xml, $matches);

        return array_values(array_unique(array_map(
            static fn (string $value): string => html_entity_decode(trim($value), ENT_QUOTES),
            $matches[1] ?? []
        )));
    }

    protected function categoryExclusionReason(string $url): ?string
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        $denied = $this->deniedPathReason($path);
        if ($denied !== null) {
            return $denied;
        }

        if ($this->containsAny($path, $this->categoryAllowFragments())) {
            return null;
        }

        return 'Category is not on the lawful consumer-goods allowlist.';
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    protected function listingExclusionReason(array $candidate): ?string
    {
        $exclusion = $this->detector->excludedFromCommerceImport($candidate);
        if ($exclusion['excluded']) {
            return $exclusion['reasons'][0] ?? 'Excluded from commerce onboarding.';
        }

        $name = strtolower((string) ($candidate['name'] ?? ''));
        $sourceUrl = strtolower((string) ($candidate['source_url'] ?? ''));
        $category = strtolower((string) ($candidate['category'] ?? ''));

        // Check product name and URL against hard denials
        $productDenial = $this->deniedPathReason($name.' '.$sourceUrl);
        if ($productDenial !== null) {
            return $productDenial;
        }

        $isClearProduct = $this->containsAny($name.' '.$sourceUrl, $this->productClearFragments());
        if (! $isClearProduct) {
            $catDenial = $this->deniedPathReason($category);
            if ($catDenial !== null) {
                return $catDenial;
            }
        }

        if ($isClearProduct || $this->containsAny($category, $this->productClearFragments())) {
            return null;
        }

        return 'Not a clearly eligible consumer supplement, vitamin, or accessory.';
    }

    protected function deniedPathReason(string $haystack): ?string
    {
        $denied = [
            'oral-steroid' => 'Oral steroid category is excluded from commerce onboarding.',
            'injectable-steroid' => 'Injectable steroid category is excluded from commerce onboarding.',
            'sarms' => 'SARM category is excluded from commerce onboarding.',
            'kamagra' => 'Unlicensed medicine category is excluded from commerce onboarding.',
            'pct' => 'Post-cycle therapy medicines are excluded from commerce onboarding.',
            'bulking' => 'Bulking-steroid category is excluded from commerce onboarding.',
            'growth-hormone' => 'Growth-hormone category is excluded from commerce onboarding.',
            'fat-burner' => 'Fat-burner category is excluded pending independent lawful-status review.',
            'needle' => 'Injection supplies are excluded from this onboarding batch.',
            'syringe' => 'Injection supplies are excluded from this onboarding batch.',
            'peptide' => 'Peptide category is excluded from commerce onboarding.',
            'semaglutide' => 'Prescription weight-loss medicine category is excluded.',
            'bpc-157' => 'Peptide category is excluded from commerce onboarding.',
            'weight-loss' => 'Weight-loss medicine category is excluded pending lawful-status review.',
            'ancillar' => 'Steroid-shop ancillary category is excluded pending lawful-status review.',
            'bundle' => 'Bundle category is excluded because contents are not independently established as lawful consumer goods.',
            'testosterone' => 'Testosterone category is excluded from commerce onboarding.',
            'trenbolone' => 'Trenbolone category is excluded from commerce onboarding.',
            'trenabolone' => 'Trenbolone category is excluded from commerce onboarding.',
            'dianabol' => 'Dianabol category is excluded from commerce onboarding.',
            'anavar' => 'Anavar category is excluded from commerce onboarding.',
            'sustanon' => 'Testosterone blend category is excluded from commerce onboarding.',
            'masteron' => 'Drostanolone category is excluded from commerce onboarding.',
            'winstrol' => 'Stanozolol category is excluded from commerce onboarding.',
            'clenbuterol' => 'Clenbuterol is excluded from commerce onboarding.',
            'tamoxifen' => 'Tamoxifen is excluded from commerce onboarding.',
            'arimidex' => 'Anastrozole is excluded from commerce onboarding.',
            'boldenone' => 'Boldenone is excluded from commerce onboarding.',
            'turinabol' => 'Turinabol is excluded from commerce onboarding.',
            'nandrolone' => 'Nandrolone is excluded from commerce onboarding.',
        ];

        foreach ($denied as $fragment => $reason) {
            if (str_contains($haystack, $fragment)) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function categoryAllowFragments(): array
    {
        return ['supplement', 'vitamin', 'accessor', 'merchandise', 'apparel', 'shaker', 'liver-care'];
    }

    /**
     * @return list<string>
     */
    protected function productClearFragments(): array
    {
        return [
            'supplement', 'vitamin', 'accessor', 'merchandise', 'apparel', 'shaker',
            'mineral', 'magnesium', 'zinc', 'creatine', 'omega', 'whey', 'collagen', 'probiotic',
            'liv52', 'liv-52', 'liver-care', 'herbal', 'botanical',
        ];
    }

    /**
     * @return list<string>
     */
    protected function ambiguousConsumerFragments(): array
    {
        return ['calcium', 'protein', 'tudca'];
    }

    /**
     * @param  list<string>  $needles
     */
    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function categoryLinks(string $html, string $base): array
    {
        preg_match_all('~href="([^"]*product-category/[^"#?]+)"~i', $html, $matches);
        $links = [];
        foreach ($matches[1] ?? [] as $href) {
            $links[] = $this->absoluteUrl($base, html_entity_decode($href, ENT_QUOTES));
        }

        return array_values(array_unique($links));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseObservedProducts(string $html, string $pageUrl): array
    {
        $items = $this->parseListing($html, $pageUrl);
        $html = str_replace('\\/', '/', $html);
        $host = (string) parse_url($pageUrl, PHP_URL_SCHEME).'://'.parse_url($pageUrl, PHP_URL_HOST);
        $pattern = $this->hostPattern($host).'/product/[^"#\\\\]+';
        $pairs = [
            ['~"url"\s*:\s*"('.$pattern.')"\s*,\s*"name"\s*:\s*"([^"]+)"~i', 1, 2],
            ['~"name"\s*:\s*"([^"]+)"\s*,\s*"url"\s*:\s*"('.$pattern.')"~i', 2, 1],
        ];

        foreach ($pairs as [$expression, $urlGroup, $nameGroup]) {
            if (preg_match_all($expression, $html, $matches, PREG_SET_ORDER) < 1) {
                continue;
            }
            foreach ($matches as $match) {
                $items[] = $this->candidateFromUrl($match[$urlGroup], html_entity_decode($match[$nameGroup], ENT_QUOTES));
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    protected function candidateFromUrl(string $url, ?string $name = null): array
    {
        $url = $this->canonicalUrl($url);
        $slug = basename((string) (parse_url($url, PHP_URL_PATH) ?: $url));
        $label = trim((string) $name);
        if ($label === '') {
            $label = ucwords(str_replace('-', ' ', $slug));
        }

        return [
            'name' => $label,
            'source_url' => $url,
            'source_product_reference' => $slug,
            'currency' => 'GBP',
            'images' => [],
        ];
    }

    protected function absoluteUrl(string $base, string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $this->canonicalUrl($href);
        }

        return $this->canonicalUrl(rtrim($base, '/').'/'.ltrim($href, '/'));
    }

    protected function canonicalUrl(string $url): string
    {
        $url = trim(html_entity_decode(str_replace('\\/', '/', $url), ENT_QUOTES));
        $url = strtok($url, '#') ?: $url;
        $url = strtok($url, '?') ?: $url;

        return rtrim($url, '/');
    }

    protected function categoryNameFromUrl(string $url): string
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/');
        $slug = basename($path);

        return ucwords(str_replace('-', ' ', $slug));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseListing(string $html, string $pageUrl): array
    {
        $host = (string) parse_url($pageUrl, PHP_URL_SCHEME).'://'.parse_url($pageUrl, PHP_URL_HOST);
        preg_match_all('~href="((?:'.$this->hostPattern($host).')?/product/[^"#]+)"[^>]*>(.*?)</a>~is', $html, $matches, PREG_SET_ORDER);

        $items = [];
        $seen = [];
        foreach ($matches as $match) {
            $rawUrl = $match[1];
            $url = $this->canonicalUrl($this->absoluteUrl($host, $rawUrl));
            if ($url === '' || isset($seen[$url]) || str_contains($url, '/product-category/')) {
                continue;
            }
            $name = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES));
            if ($name === '' || strlen($name) < 2) {
                continue;
            }
            $seen[$url] = true;
            $items[] = $this->candidateFromUrl($url, $name);
        }

        return $items;
    }

    protected function mainProductHtml(string $html): string
    {
        $parts = preg_split('#<!--\s*Related Products\s*-->|<section[^>]*>\s*<div[^>]*>\s*<p[^>]*>\s*Related products#i', $html);

        return $parts[0] ?? $html;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseProductPage(string $html, string $url): array
    {
        $mainHtml = $this->mainProductHtml($html);

        $parsed = [
            'source_url' => $url,
            'currency' => 'GBP',
            'images' => $this->imageReferences($mainHtml),
        ];

        if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $mainHtml, $h1) === 1) {
            $h1Title = trim(html_entity_decode(strip_tags($h1[1]), ENT_QUOTES));
            if ($h1Title !== '') {
                $parsed['name'] = $h1Title;
            }
        }

        if (preg_match('#SKU:\s*([A-Za-z0-9\-]+)#i', $mainHtml, $sku) === 1) {
            $parsed['sku'] = strtoupper($sku[1]);
        }

        // Primary price near h1 or before cart form, avoiding reward text
        if (preg_match('#</h1>.*?£\s*([0-9]+(?:\.[0-9]{1,2})?)#is', $mainHtml, $mainPrice) === 1) {
            $parsed['price'] = $mainPrice[1];
        } elseif (preg_match('#£\s*([0-9]+(?:\.[0-9]{1,2})?)#', $mainHtml, $firstPrice) === 1) {
            $parsed['price'] = $firstPrice[1];
        }

        // Comparison price inside <del> or strikethrough in the main product section
        if (preg_match('#<del[^>]*>.*?£\s*([0-9]+(?:\.[0-9]{1,2})?).*?</del>#is', $mainHtml, $delPrice) === 1) {
            $parsed['compare_at_price'] = $delPrice[1];
        }

        if (preg_match('#class="brand[^"]*"[^>]*>\s*([^<]+)#i', $mainHtml, $brand) === 1) {
            $parsed['brand'] = trim(html_entity_decode($brand[1], ENT_QUOTES));
        } elseif (preg_match('#\b(Himalaya|Proper Labs|Pharmaqo|Beligas|Deus Medical|Intex Pharma|Syncom Labs)\b#i', $mainHtml, $brandMatch) === 1) {
            $parsed['brand'] = trim($brandMatch[1]);
        }

        if (preg_match('#(?:In stock|Out of stock|Available)#i', $mainHtml, $stock) === 1) {
            $parsed['availability'] = $stock[0];
        }

        if (preg_match('#Categories:\s*(?:</span>)?\s*<a[^>]*>([^<]+)</a>#i', $mainHtml, $cat) === 1) {
            $catName = trim(html_entity_decode($cat[1], ENT_QUOTES));
            if (str_contains(strtolower($catName), 'needle') || str_contains(strtolower($catName), 'syringe')) {
                // If it is an oral herbal tablet/supplement, source category was misfiled on the steroid shop
                $catName = 'Dietary Supplements';
            }
            $parsed['category'] = $catName;
        }

        $parsed['variants'] = $this->parseVariants($mainHtml);

        return $parsed;
    }

    /**
     * @return list<string>
     */
    protected function imageReferences(string $html): array
    {
        $urls = [];

        if (preg_match('#id="mainProductImage"[^>]+src="([^"]+)"#i', $html, $main) === 1) {
            $mainSrc = html_entity_decode(trim($main[1]), ENT_QUOTES);
            if (preg_match('#\.(?:jpe?g|png|webp)(?:\?|$)#i', $mainSrc)) {
                $urls[] = $mainSrc;
            }
        }

        preg_match_all('#<img[^>]+src="([^"]+)"#i', $html, $matches);
        foreach ($matches[1] ?? [] as $src) {
            $src = html_entity_decode(trim($src), ENT_QUOTES);
            if (! preg_match('#\.(?:jpe?g|png|webp)(?:\?|$)#i', $src)) {
                continue;
            }
            if (str_contains(strtolower($src), 'logo') || str_contains(strtolower($src), 'icon') || str_contains(strtolower($src), 'lab_tested')) {
                continue;
            }
            $urls[] = $src;
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseVariants(string $html): array
    {
        if (preg_match('#<select[^>]*(?:variation|attribute_)[^>]*>(.*?)</select>#is', $html, $select) !== 1) {
            return [];
        }

        $variants = [];
        if (preg_match_all('#<option[^>]*value="([^"]+)"[^>]*>([^<]+)</option>#i', $select[1], $matches, PREG_SET_ORDER) < 1) {
            return [];
        }

        foreach ($matches as $match) {
            $label = trim(html_entity_decode($match[2], ENT_QUOTES));
            if ($label === '' || str_contains(strtolower($label), 'choose')) {
                continue;
            }
            $variants[] = [
                'name' => $label,
                'attributes' => ['format' => $label],
            ];
        }

        return $variants;
    }

    protected function hostPattern(string $host): string
    {
        return preg_quote(rtrim($host, '/'), '~');
    }
}
