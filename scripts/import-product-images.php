<?php

/**
 * Import all product images from reference-full-catalogue.json
 * and attach them to the corresponding products.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SeoMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "=== Product Image Importer ===\n\n";

$cataloguePath = storage_path('app/catalogue-import/reference-full-catalogue.json');
if (!file_exists($cataloguePath)) {
    echo "❌ Reference catalogue file not found at: {$cataloguePath}\n";
    exit(1);
}

$catalogue = json_decode(file_get_contents($cataloguePath), true);
$refProducts = $catalogue['products'] ?? [];
echo "Loaded " . count($refProducts) . " products from reference catalogue.\n\n";

$attachedCount = 0;
$imageRecordCount = 0;
$skippedCount = 0;

DB::beginTransaction();

try {
    foreach ($refProducts as $ref) {
        $name = trim($ref['name'] ?? '');
        $sku = trim($ref['sku'] ?? '');
        $slug = trim($ref['slug'] ?? Str::slug($name));
        $images = $ref['images'] ?? [];

        if (empty($images)) {
            $skippedCount++;
            continue;
        }

        // Find the product by SKU, slug, or name
        $product = Product::where('sku', $sku)->first()
            ?? Product::where('slug', $slug)->first()
            ?? Product::where('name', $name)->first();

        if (!$product) {
            echo "  ⚠️ Product not found: [{$sku}] {$name}\n";
            $skippedCount++;
            continue;
        }

        // Remove any old/broken images for this product
        $product->images()->delete();

        $primaryAssigned = false;
        foreach ($images as $idx => $img) {
            $url = trim($img['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $isPrimary = (!$primaryAssigned && ($img['is_primary'] ?? false)) || ($idx === 0 && !$primaryAssigned);
            if ($isPrimary) {
                $primaryAssigned = true;
            }

            ProductImage::create([
                'product_id' => $product->id,
                'url' => $url,
                'alt_text' => $img['alt'] ?? $product->name,
                'display_order' => $idx,
                'is_primary' => $isPrimary,
                'thumbnail_url' => $url,
            ]);

            $imageRecordCount++;
        }

        // Also update the SEO OG image if available
        $primaryImg = $product->images()->where('is_primary', true)->first() ?? $product->images()->first();
        if ($primaryImg && $product->seoMetadata) {
            $product->seoMetadata->update([
                'og_image_url' => $primaryImg->url,
            ]);
        }

        // Product has real images now, so image_not_required can be false
        $product->image_not_required = false;
        $product->save();

        $attachedCount++;

        if ($attachedCount % 50 === 0) {
            echo "  ✓ Processed {$attachedCount} products ({$imageRecordCount} images attached)...\n";
        }
    }

    DB::commit();

    // Invalidate caches
    Cache::forget('catalogue.categories.visible');
    Cache::forget('catalogue.categories.visible.v2');
    Cache::forget('catalogue.brands.active');
    Cache::forget('catalogue.brands.active.v2');
    Cache::forget('catalogue.navigation');
    Cache::forget('catalogue.facets');
    Cache::forget('catalogue.search.facets');

    echo "\n=== Results ===\n";
    echo "Products updated with images: {$attachedCount}\n";
    echo "Total image records created:  {$imageRecordCount}\n";
    echo "Skipped / no images:          {$skippedCount}\n\n";

    // Check count of products with images
    $totalWithImages = Product::has('images')->count();
    $totalProducts = Product::count();
    echo "Total products in DB: {$totalProducts}\n";
    echo "Products with images: {$totalWithImages}\n";

    echo "\n✅ Product images successfully imported!\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n❌ Error: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
