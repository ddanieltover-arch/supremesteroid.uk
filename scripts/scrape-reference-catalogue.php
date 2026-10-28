<?php

declare(strict_types=1);

/**
 * Supreme Steroids Reference Catalogue Scraper
 *
 * Scrapes all 472 products, 34 categories, and 26 brands from supremesteroids.co.uk
 * Outputs structured JSON compatible with JsonCatalogueSourceReader & CatalogueImportService.
 *
 * Usage:
 *   php scripts/scrape-reference-catalogue.php [--max=50] [--no-cache] [--skip-details]
 */

$max = null;
$noCache = false;
$skipDetails = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--max=')) {
        $max = (int) substr($arg, 6);
    }
    if ($arg === '--no-cache') {
        $noCache = true;
    }
    if ($arg === '--skip-details') {
        $skipDetails = true;
    }
}

$cacheDir = __DIR__ . '/../storage/app/catalogue-import/cache';
$outputDir = __DIR__ . '/../storage/app/catalogue-import';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

function fetchUrl(string $url, bool $useCache = true, int $delayMs = 200, int $maxRetries = 3): string
{
    global $cacheDir, $noCache;
    $cacheFile = $cacheDir . '/' . md5($url) . '.html';

    if ($useCache && !$noCache && file_exists($cacheFile)) {
        return (string) file_get_contents($cacheFile);
    }

    $attempt = 0;
    while ($attempt < $maxRetries) {
        $attempt++;

        if ($delayMs > 0 && $attempt === 1) {
            usleep($delayMs * 1000);
        } elseif ($attempt > 1) {
            $backoff = $attempt * 2;
            sleep($backoff);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-GB,en;q=0.9',
            ],
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && is_string($res) && strlen($res) > 200) {
            if ($useCache) {
                file_put_contents($cacheFile, $res);
            }
            return $res;
        }

        if ($code === 404) {
            throw new RuntimeException("Page 404 Not Found: {$url}");
        }
    }

    throw new RuntimeException("Failed fetching {$url} (HTTP {$code})");
}

function fetchUrlsMulti(array $urls, int $concurrency = 5): array
{
    global $cacheDir, $noCache;
    $results = [];
    $toFetch = [];

    foreach ($urls as $url) {
        $file = $cacheDir . '/' . md5($url) . '.html';
        if (!$noCache && file_exists($file)) {
            $results[$url] = (string) file_get_contents($file);
        } else {
            $toFetch[] = $url;
        }
    }

    $cachedCount = count($results);
    $fetchCount = count($toFetch);
    echo "  (Pre-cached: {$cachedCount}, Uncached to fetch: {$fetchCount})\n";

    if ($fetchCount > 0) {
        $chunks = array_chunk($toFetch, $concurrency);
        $chunkTotal = count($chunks);

        foreach ($chunks as $cIdx => $chunk) {
            $num = $cIdx + 1;
            echo "  [Multi-fetch chunk {$num}/{$chunkTotal}] ";
            $mh = curl_multi_init();
            $handles = [];

            foreach ($chunk as $url) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 25,
                    CURLOPT_CONNECTTIMEOUT => 12,
                    CURLOPT_HTTPHEADER => [
                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language: en-GB,en;q=0.9',
                    ],
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$url] = $ch;
            }

            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh, 0.5);
            } while ($running > 0);

            $ok = 0;
            foreach ($handles as $url => $ch) {
                $content = curl_multi_getcontent($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                if ($code === 200 && is_string($content) && strlen($content) > 200) {
                    file_put_contents($cacheDir . '/' . md5($url) . '.html', $content);
                    $results[$url] = $content;
                    $ok++;
                }
            }

            curl_multi_close($mh);
            echo "{$ok}/" . count($chunk) . " OK\n";
            usleep(150000); // 150ms polite pause
        }
    }

    return $results;
}

function slugToTitle(string $slug): string
{
    $parts = explode('-', $slug);
    $acronyms = ['pct', 'hgh', 'bpc', 'hcg', 'bpc-157', 'sarms'];
    $titled = array_map(function ($part) use ($acronyms) {
        if (in_array(strtolower($part), $acronyms, true)) {
            return strtoupper($part);
        }
        return ucfirst(strtolower($part));
    }, $parts);
    return implode(' ', $titled);
}

echo "=== Supreme Steroids Reference Catalogue Scraper ===\n";

// 1. SCRAPE BRANDS
echo "\n[1/4] Scraping Brands from brands-sitemap.xml...\n";
$brandsXml = fetchUrl('https://supremesteroids.co.uk/brands-sitemap.xml');
preg_match_all('~<loc>(https://supremesteroids\.co\.uk/product-brand/([^<]+))</loc>~', $brandsXml, $brandMatches, PREG_SET_ORDER);

$brands = [];
foreach ($brandMatches as $bm) {
    $slug = trim($bm[2]);
    $brands[$slug] = [
        'name' => slugToTitle($slug),
        'slug' => $slug,
        'url' => $bm[1],
    ];
}
echo "Found " . count($brands) . " brands.\n";

// 2. SCRAPE CATEGORIES HIERARCHY
echo "\n[2/4] Scraping Categories from product_cat-sitemap.xml...\n";
$catXml = fetchUrl('https://supremesteroids.co.uk/product_cat-sitemap.xml');
preg_match_all('~<loc>(https://supremesteroids\.co\.uk/product-category/([^<]+))</loc>~', $catXml, $catMatches, PREG_SET_ORDER);

$categories = [];
foreach ($catMatches as $cm) {
    $fullPath = trim($cm[2], '/');
    $segments = explode('/', $fullPath);
    $slug = end($segments);
    $parentSlug = count($segments) > 1 ? $segments[count($segments) - 2] : null;

    $categories[$slug] = [
        'name' => slugToTitle($slug),
        'slug' => $slug,
        'path' => $fullPath,
        'parent_slug' => $parentSlug,
        'level' => count($segments),
        'url' => $cm[1],
    ];
}
echo "Found " . count($categories) . " categories across " . max(array_column($categories, 'level')) . " hierarchy levels.\n";

// 3. SCRAPE PRODUCT CARDS FROM SHOP PAGES (16 pages)
echo "\n[3/4] Scraping Product cards from /shop pagination (1-16)...\n";
$shopProducts = [];
$page = 1;
$totalPages = 16;

while ($page <= $totalPages) {
    $url = $page === 1 ? 'https://supremesteroids.co.uk/shop' : "https://supremesteroids.co.uk/shop?page={$page}";
    echo "  Fetching /shop page {$page}/{$totalPages}... ";
    $html = fetchUrl($url, true, 100);

    // Extract product cards
    $cards = explode('<!-- PRODUCT CARD -->', $html);
    array_shift($cards);

    $pageFound = 0;
    foreach ($cards as $cardHtml) {
        $slug = null;
        $name = null;
        if (preg_match('~<h2[^>]*>\s*<a[^>]+href="[^"]*/product/([^"/]+)"[^>]*>(.*?)</a>\s*</h2>~si', $cardHtml, $titleMatch)) {
            $slug = trim($titleMatch[1]);
            $name = trim(html_entity_decode(strip_tags($titleMatch[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        } elseif (preg_match('~href="[^"]*/product/([^"/]+)"~', $cardHtml, $slugMatch)) {
            $slug = trim($slugMatch[1]);
            $name = slugToTitle($slug);
        }

        if (!$slug) {
            continue;
        }

        $productId = null;
        if (preg_match('~name="product_id"[^>]+value="(\d+)"~', $cardHtml, $pidMatch)) {
            $productId = $pidMatch[1];
        }

        $image = null;
        if (preg_match('~<img[^>]+src="([^"]*supremestd\.b-cdn\.net/wp-content/uploads/[^"]+)"~si', $cardHtml, $imgMatch)) {
            $image = $imgMatch[1];
        }

        $price = null;
        $compareAtPrice = null;
        if (preg_match('~<del[^>]*>.*?([0-9]+\.[0-9]{2}).*?</del>~s', $cardHtml, $delMatch)) {
            $compareAtPrice = (float) $delMatch[1];
        }
        if (preg_match('~class="text-blue-600"[^>]*>.*?([0-9]+\.[0-9]{2})~s', $cardHtml, $pMatch)) {
            $price = (float) $pMatch[1];
        }

        if (!isset($shopProducts[$slug])) {
            $shopProducts[$slug] = [
                'source_product_reference' => $productId ?? $slug,
                'slug' => $slug,
                'name' => $name,
                'price' => $price,
                'compare_at_price' => $compareAtPrice,
                'card_image' => $image,
                'url' => "https://supremesteroids.co.uk/product/{$slug}",
            ];
            $pageFound++;
        }

        if ($max !== null && count($shopProducts) >= $max) {
            break 2;
        }
    }

    echo "found {$pageFound} products (total: " . count($shopProducts) . ")\n";

    if ($pageFound === 0) {
        break;
    }
    $page++;
}

echo "Total products identified from shop pages: " . count($shopProducts) . "\n";

// 4. BATCH ENRICH WITH INDIVIDUAL PRODUCT PAGE DETAILS
echo "\n[4/4] Enriching product details from individual product pages...\n";
$fullProducts = [];

$allUrls = array_column($shopProducts, 'url');
$pagesHtml = [];
if (!$skipDetails) {
    echo "  Running concurrent batch fetch for individual product pages...\n";
    $pagesHtml = fetchUrlsMulti($allUrls, concurrency: 5);
}

$total = count($shopProducts);
$current = 0;

foreach ($shopProducts as $slug => $card) {
    $current++;
    $url = $card['url'];

    $sku = null;
    $brand = null;
    $category = null;
    $subcategory = null;
    $description = null;
    $images = [];

    if (isset($pagesHtml[$url])) {
        $pageHtml = $pagesHtml[$url];

        // Parse JSON-LD
        if (preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $pageHtml, $jsonMatch)) {
            $ld = json_decode($jsonMatch[1], true);
            if (is_array($ld)) {
                if (!empty($ld['sku'])) {
                    $sku = (string) $ld['sku'];
                }
                if (!empty($ld['name']) && trim((string) $ld['name']) !== '') {
                    $card['name'] = trim((string) $ld['name']);
                }
                if (!empty($ld['brand']['name'])) {
                    $brand = trim((string) $ld['brand']['name']);
                }
                if (!empty($ld['category'])) {
                    $category = trim((string) $ld['category']);
                }
                if (!empty($ld['description'])) {
                    $description = trim((string) $ld['description']);
                }
                if (!empty($ld['offers']['price']) && is_numeric($ld['offers']['price'])) {
                    $card['price'] = (float) $ld['offers']['price'];
                }
                if (!empty($ld['image']) && is_array($ld['image'])) {
                    foreach ($ld['image'] as $imgUrl) {
                        if (!empty($imgUrl) && is_string($imgUrl)) {
                            $images[] = [
                                'url' => $imgUrl,
                                'alt' => $card['name'],
                                'is_primary' => true,
                            ];
                            break;
                        }
                    }
                }
            }
        }

        // Fallback for name from <h1> or og:title if needed
        if (empty($card['name'])) {
            if (preg_match('~<h1[^>]*>(.*?)</h1>~si', $pageHtml, $h1Match)) {
                $card['name'] = trim(strip_tags($h1Match[1]));
            } elseif (preg_match('~<meta\s+property="og:title"\s+content="([^"]*)"~i', $pageHtml, $ogTMatch)) {
                $card['name'] = trim(str_replace(' - Supreme Steroids', '', $ogTMatch[1]));
            }
        }

        // Fallback for image from og:image
        if (empty($images)) {
            if (preg_match('~<meta\s+property="og:image"\s+content="([^"]*)"~i', $pageHtml, $ogImgMatch)) {
                $images[] = [
                    'url' => $ogImgMatch[1],
                    'alt' => $card['name'],
                    'is_primary' => true,
                ];
            } elseif (!empty($card['card_image'])) {
                $images[] = [
                    'url' => $card['card_image'],
                    'alt' => $card['name'],
                    'is_primary' => true,
                ];
            }
        }

        // Fallback for description from og:description
        if (empty($description)) {
            if (preg_match('~<meta\s+property="og:description"\s+content="([^"]*)"~i', $pageHtml, $ogDescMatch)) {
                $description = trim($ogDescMatch[1]);
            }
        }

        // Category breadcrumbs from page HTML
        if (preg_match_all('~href="https?://[^"]*/product-category/([^"]+)"[^>]*>(.*?)</a>~i', $pageHtml, $catMatches, PREG_SET_ORDER)) {
            $catCandidates = [];
            foreach ($catMatches as $cm) {
                $cText = trim(strip_tags($cm[2]));
                if ($cText !== '' && !in_array($cText, $catCandidates, true)) {
                    $catCandidates[] = $cText;
                }
            }
            if ($catCandidates !== []) {
                $category = $category ?? $catCandidates[0];
                if (count($catCandidates) > 1) {
                    $subcategory = $catCandidates[count($catCandidates) - 1];
                }
            }
        }

        // Certificate / lab test images
        if (preg_match('~src="([^"]*supremestd\.b-cdn\.net/wp-content/uploads/[^"]*certificate[^"]*)"~i', $pageHtml, $certMatch)) {
            $images[] = [
                'url' => $certMatch[1],
                'alt' => $card['name'] . ' Certificate',
                'is_primary' => false,
            ];
        }
    }

    // Fallback brand detection from name if missing
    if ($brand === null || $brand === '') {
        foreach ($brands as $bInfo) {
            if (stripos($card['name'], $bInfo['name']) !== false) {
                $brand = $bInfo['name'];
                break;
            }
        }
    }

    // Default category fallback
    if ($category === null || $category === '') {
        $category = 'General';
    }

    // Primary image fallback
    if (empty($images) && !empty($card['card_image'])) {
        $images[] = [
            'url' => $card['card_image'],
            'alt' => $card['name'],
            'is_primary' => true,
        ];
    }

    $fullProducts[] = [
        'source_product_reference' => (string) $card['source_product_reference'],
        'sku' => $sku ?? ('REF-' . str_pad((string) $current, 4, '0', STR_PAD_LEFT)),
        'name' => $card['name'],
        'slug' => $card['slug'],
        'brand' => $brand,
        'category' => $category,
        'subcategory' => $subcategory,
        'price' => $card['price'] ?? 0.00,
        'compare_at_price' => $card['compare_at_price'],
        'currency' => 'GBP',
        'classification' => 'OTHER_LAWFUL_PRODUCT',
        'source_url' => $card['url'],
        'description' => $description,
        'images' => $images,
    ];
}

// 5. WRITE JSON EXPORT
$exportData = [
    'source' => 'supremesteroids.co.uk',
    'scraped_at' => date('Y-m-d H:i:s'),
    'categories_count' => count($categories),
    'brands_count' => count($brands),
    'products_count' => count($fullProducts),
    'categories' => array_values($categories),
    'brands' => array_values($brands),
    'products' => $fullProducts,
];

$outputFile = $outputDir . '/reference-full-catalogue.json';
file_put_contents($outputFile, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "\n============================================\n";
echo " Scraping Complete!\n";
echo " - Categories: " . count($categories) . "\n";
echo " - Brands:     " . count($brands) . "\n";
echo " - Products:   " . count($fullProducts) . "\n";
echo " Output saved to: storage/app/catalogue-import/reference-full-catalogue.json\n";
echo "============================================\n";
