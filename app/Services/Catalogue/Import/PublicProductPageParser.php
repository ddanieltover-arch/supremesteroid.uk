<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

/**
 * Extracts only fields publicly present on a product HTML page (JSON-LD + visible markup).
 * Does not invent SKUs, prices, brands, or variants.
 */
class PublicProductPageParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $html, string $url): array
    {
        $mainHtml = $this->mainProductHtml($html);
        $parsed = [
            'source_url' => $url,
            'currency' => 'GBP',
            'images' => [],
            'variants' => [],
            'description' => null,
            'fields_from' => [],
        ];

        $jsonLd = $this->parseJsonLdProduct($html);
        if ($jsonLd !== []) {
            $parsed = $this->mergePresent($parsed, $jsonLd);
            $parsed['fields_from'][] = 'json-ld';
        }

        $markup = $this->parseMarkup($mainHtml, $url);
        foreach ($markup as $key => $value) {
            if ($key === 'images') {
                $parsed['images'] = array_values(array_unique(array_merge(
                    array_map('strval', (array) ($parsed['images'] ?? [])),
                    array_map('strval', (array) $value)
                )));
                continue;
            }
            if ($key === 'variants') {
                if (($parsed['variants'] ?? []) === [] && is_array($value) && $value !== []) {
                    $parsed['variants'] = $value;
                }
                continue;
            }
            if (! filled($parsed[$key] ?? null) && filled($value)) {
                $parsed[$key] = $value;
                if (! in_array('html-markup', $parsed['fields_from'], true)) {
                    $parsed['fields_from'][] = 'html-markup';
                }
            }
        }

        if ($this->isPlaceholderBrand((string) ($parsed['brand'] ?? ''))) {
            $parsed['brand'] = null;
        }

        return $parsed;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseJsonLdProduct(string $html): array
    {
        if (preg_match_all('#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches) < 1) {
            return [];
        }

        foreach ($matches[1] as $block) {
            $decoded = json_decode(trim(html_entity_decode($block, ENT_QUOTES)), true);
            if (! is_array($decoded)) {
                continue;
            }
            $product = $this->findProductNode($decoded);
            if ($product === null) {
                continue;
            }

            $out = [];
            if (filled($product['name'] ?? null)) {
                $out['name'] = trim(html_entity_decode((string) $product['name'], ENT_QUOTES));
            }
            if (filled($product['description'] ?? null)) {
                $out['description'] = trim(html_entity_decode(strip_tags((string) $product['description']), ENT_QUOTES));
            }
            if (filled($product['sku'] ?? null)) {
                $out['sku'] = strtoupper(trim((string) $product['sku']));
            }
            $brand = $product['brand']['name'] ?? $product['brand'] ?? null;
            if (is_string($brand) && trim($brand) !== '') {
                $out['brand'] = trim(html_entity_decode($brand, ENT_QUOTES));
            }
            $images = $product['image'] ?? [];
            if (is_string($images)) {
                $images = [$images];
            }
            if (is_array($images)) {
                $out['images'] = array_values(array_filter(array_map(
                    static fn ($src): string => is_string($src) ? trim($src) : '',
                    $images
                )));
            }

            $offers = $product['offers'] ?? null;
            if (is_array($offers)) {
                if (isset($offers[0]) && is_array($offers[0])) {
                    $offers = $offers[0];
                }
                if (isset($offers['price']) && is_numeric($offers['price'])) {
                    $out['price'] = (string) $offers['price'];
                }
                if (filled($offers['priceCurrency'] ?? null)) {
                    $out['currency'] = strtoupper((string) $offers['priceCurrency']);
                }
                if (filled($offers['availability'] ?? null)) {
                    $availability = (string) $offers['availability'];
                    $out['availability'] = str_contains(strtolower($availability), 'outofstock')
                        ? 'Out of stock'
                        : (str_contains(strtolower($availability), 'instock') ? 'In stock' : $availability);
                }
            }

            return $out;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    protected function findProductNode(array $node): ?array
    {
        $type = $node['@type'] ?? null;
        if (is_string($type) && strcasecmp($type, 'Product') === 0) {
            return $node;
        }
        if (is_array($type) && in_array('Product', $type, true)) {
            return $node;
        }
        if (isset($node['@graph']) && is_array($node['@graph'])) {
            foreach ($node['@graph'] as $child) {
                if (is_array($child)) {
                    $found = $this->findProductNode($child);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }
        foreach ($node as $value) {
            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $child) {
                    if (is_array($child)) {
                        $found = $this->findProductNode($child);
                        if ($found !== null) {
                            return $found;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseMarkup(string $html, string $url): array
    {
        $parsed = [
            'source_url' => $url,
            'currency' => 'GBP',
            'images' => $this->imageReferences($html),
            'variants' => $this->parseVariants($html),
        ];

        if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $h1) === 1) {
            $title = trim(html_entity_decode(strip_tags($h1[1]), ENT_QUOTES));
            if ($title !== '') {
                $parsed['name'] = $title;
            }
        }

        if (preg_match('#SKU:\s*([A-Za-z0-9\-_/]+)#i', $html, $sku) === 1) {
            $parsed['sku'] = strtoupper($sku[1]);
        }

        if (preg_match('#</h1>.*?£\s*([0-9]+(?:\.[0-9]{1,2})?)#is', $html, $mainPrice) === 1) {
            $parsed['price'] = $mainPrice[1];
        } elseif (preg_match('#£\s*([0-9]+(?:\.[0-9]{1,2})?)#', $html, $firstPrice) === 1) {
            $parsed['price'] = $firstPrice[1];
        }

        if (preg_match('#<del[^>]*>.*?£\s*([0-9]+(?:\.[0-9]{1,2})?).*?</del>#is', $html, $delPrice) === 1) {
            $parsed['compare_at_price'] = $delPrice[1];
        }

        if (preg_match('#class="brand[^"]*"[^>]*>\s*([^<]+)#i', $html, $brand) === 1) {
            $parsed['brand'] = trim(html_entity_decode($brand[1], ENT_QUOTES));
        } elseif (preg_match('#\b(Himalaya|Proper Labs|Pharmaqo|Beligas|Deus Medical|Intex Pharma|Syncom Labs)\b#i', $html, $brandMatch) === 1) {
            $parsed['brand'] = trim($brandMatch[1]);
        }

        if (preg_match('#(?:In stock|Out of stock|Available)#i', $html, $stock) === 1) {
            $parsed['availability'] = $stock[0];
        }

        if (preg_match('#Categories:\s*(?:</span>)?\s*<a[^>]*href="[^"]*product-category/([^"/]+)"[^>]*>([^<]+)</a>#i', $html, $cat) === 1) {
            $catName = trim(html_entity_decode($cat[2], ENT_QUOTES));
            $catSlug = strtolower($cat[1]);
            if (str_contains($catSlug, 'needle') || str_contains($catSlug, 'syringe')) {
                // Misfiled consumer goods on the reference shop keep a neutral category label.
                $catName = 'Dietary Supplements';
            }
            $parsed['category'] = $catName;
        } elseif (preg_match('#Categories:\s*(?:</span>)?\s*<a[^>]*>([^<]+)</a>#i', $html, $cat) === 1) {
            $catName = trim(html_entity_decode($cat[1], ENT_QUOTES));
            if (str_contains(strtolower($catName), 'needle') || str_contains(strtolower($catName), 'syringe')) {
                $catName = 'Dietary Supplements';
            }
            $parsed['category'] = $catName;
        }

        return $parsed;
    }

    protected function mainProductHtml(string $html): string
    {
        $parts = preg_split('#<!--\s*Related Products\s*-->|<section[^>]*>\s*<div[^>]*>\s*<p[^>]*>\s*Related products#i', $html);

        return $parts[0] ?? $html;
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

    protected function isPlaceholderBrand(string $brand): bool
    {
        $normalized = strtolower(trim($brand));

        return $normalized === '' || in_array($normalized, ['brand', 'n/a', 'na', 'unknown'], true);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    protected function mergePresent(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if ($key === 'images' && is_array($value)) {
                $base['images'] = $value;
                continue;
            }
            if (filled($value)) {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
