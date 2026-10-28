<?php

/**
 * Fetch and attach images for products that currently don't have images.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Cache;

echo "=== Fetch Missing Product Images ===\n\n";

$catalogue = json_decode(file_get_contents('storage/app/catalogue-import/reference-full-catalogue.json'), true);
$refBySku = [];
foreach ($catalogue['products'] as $p) {
    if (!empty($p['sku'])) {
        $refBySku[$p['sku']] = $p;
    }
}

$missingProducts = Product::doesntHave('images')->get();
echo "Found " . $missingProducts->count() . " products without images.\n\n";

$updated = 0;

foreach ($missingProducts as $product) {
    $ref = $refBySku[$product->sku] ?? null;
    $url = $ref['source_url'] ?? "https://supremesteroids.co.uk/product/{$product->slug}";

    echo "Fetching: [{$product->sku}] {$product->name}\n  URL: {$url}\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || empty($html)) {
        echo "  ❌ Failed to fetch (HTTP {$code})\n";
        continue;
    }

    $foundImages = [];

    // 1. Look for id="mainProductImage"
    if (preg_match('/<img[^>]+id=["\']mainProductImage["\'][^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
        $foundImages[] = $m[1];
    } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]+id=["\']mainProductImage["\']/i', $html, $m)) {
        $foundImages[] = $m[1];
    }

    // 2. Look for thumbnails with alt="Thumb..."
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]+alt=["\']Thumb/i', $html, $m)) {
        foreach ($m[1] as $src) {
            $foundImages[] = $src;
        }
    }

    // 3. Look for og:image meta tag
    if (empty($foundImages)) {
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            if (!str_contains($m[1], 'logo') && !str_contains($m[1], 'LOGO')) {
                $foundImages[] = $m[1];
            }
        }
    }

    $foundImages = array_values(array_unique(array_filter($foundImages)));

    if (empty($foundImages)) {
        echo "  ⚠️ No image found in HTML\n";
        continue;
    }

    echo "  ✓ Found " . count($foundImages) . " image(s): " . $foundImages[0] . "\n";

    foreach ($foundImages as $idx => $imgUrl) {
        ProductImage::create([
            'product_id' => $product->id,
            'url' => $imgUrl,
            'alt_text' => $product->name,
            'display_order' => $idx,
            'is_primary' => $idx === 0,
            'thumbnail_url' => $imgUrl,
        ]);
    }

    if ($product->seoMetadata) {
        $product->seoMetadata->update(['og_image_url' => $foundImages[0]]);
    }

    $product->image_not_required = false;
    $product->save();

    $updated++;
}

Cache::forget('catalogue.categories.visible');
Cache::forget('catalogue.categories.visible.v2');
Cache::forget('catalogue.brands.active');
Cache::forget('catalogue.brands.active.v2');
Cache::forget('catalogue.navigation');
Cache::forget('catalogue.facets');
Cache::forget('catalogue.search.facets');

echo "\n=== Finished ===\n";
echo "Successfully updated: {$updated} products\n";
echo "Total products with images now: " . Product::has('images')->count() . " / " . Product::count() . "\n";
