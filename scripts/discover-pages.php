<?php

/**
 * Discover all pages on https://supremesteroids.co.uk
 */

$baseUrl = 'https://supremesteroids.co.uk';

function fetchUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $res];
}

echo "=== Reference Site Page Discovery ===\n\n";

// Check common sitemaps
$sitemaps = [
    '/sitemap.xml',
    '/sitemap_index.xml',
    '/page-sitemap.xml',
    '/post-sitemap.xml',
    '/robots.txt'
];

$discoveredPages = [];

foreach ($sitemaps as $sm) {
    $res = fetchUrl($baseUrl . $sm);
    echo "Checking {$sm} -> HTTP {$res['code']}\n";
    if ($res['code'] === 200 && !empty($res['body'])) {
        if ($sm === '/robots.txt') {
            echo "Robots.txt contents:\n" . substr($res['body'], 0, 500) . "\n---\n";
        } else {
            preg_match_all('/<loc>(https?:\/\/[^<]+)<\/loc>/i', $res['body'], $matches);
            if (!empty($matches[1])) {
                echo "  Found " . count($matches[1]) . " URLs in {$sm}\n";
                foreach ($matches[1] as $loc) {
                    if (str_contains($loc, 'sitemap')) {
                        echo "    Nested sitemap: {$loc}\n";
                        $nested = fetchUrl($loc);
                        if ($nested['code'] === 200) {
                            preg_match_all('/<loc>(https?:\/\/[^<]+)<\/loc>/i', $nested['body'], $nestedMatches);
                            foreach ($nestedMatches[1] as $nloc) {
                                $discoveredPages[] = $nloc;
                            }
                        }
                    } else {
                        $discoveredPages[] = $loc;
                    }
                }
            }
        }
    }
}

// Also fetch homepage and extract all navigation & footer links
$homeRes = fetchUrl($baseUrl);
if ($homeRes['code'] === 200) {
    preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $homeRes['body'], $links);
    foreach ($links[1] as $idx => $href) {
        $text = trim(strip_tags($links[2][$idx]));
        if (str_starts_with($href, '/') || str_starts_with($href, $baseUrl)) {
            $fullUrl = str_starts_with($href, '/') ? $baseUrl . $href : $href;
            $discoveredPages[] = $fullUrl;
        }
    }
}

$discoveredPages = array_unique(array_filter($discoveredPages));
echo "\nTotal unique URLs found: " . count($discoveredPages) . "\n";

// Categorize URLs: Pages vs Categories vs Products vs Brands
$pages = [];
$categories = [];
$products = [];
$brands = [];
$others = [];

foreach ($discoveredPages as $url) {
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    $path = trim($path, '/');

    if ($path === '' || $path === '/') {
        continue;
    }

    if (str_starts_with($path, 'product/')) {
        $products[] = $url;
    } elseif (str_starts_with($path, 'product-category/')) {
        $categories[] = $url;
    } elseif (str_starts_with($path, 'product-brand/')) {
        $brands[] = $url;
    } elseif (str_starts_with($path, 'wp-content') || str_starts_with($path, 'wp-json') || str_starts_with($path, 'feed')) {
        continue;
    } else {
        $pages[] = [
            'url' => $url,
            'path' => $path,
        ];
    }
}

echo "\n--- Discovered Static / Content Pages (" . count($pages) . ") ---\n";
foreach ($pages as $p) {
    echo "  /{$p['path']} -> {$p['url']}\n";
}

echo "\nSummary:\n";
echo "Content / Info Pages: " . count($pages) . "\n";
echo "Categories:           " . count($categories) . "\n";
echo "Brands:               " . count($brands) . "\n";
echo "Products:             " . count($products) . "\n";

file_put_contents('storage/app/catalogue-import/discovered-reference-pages.json', json_encode($pages, JSON_PRETTY_PRINT));
