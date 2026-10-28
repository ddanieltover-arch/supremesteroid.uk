<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Check product status enum
$product = App\Models\Product::first();
echo "First product: {$product->name}\n";
echo "Status value: {$product->status->value}\n";
echo "Is active: " . ($product->status === App\Enums\ProductStatus::ACTIVE ? 'YES' : 'NO') . "\n";

// Try finding active products
$active = App\Models\Product::where('status', App\Enums\ProductStatus::ACTIVE)->first();
if ($active) {
    echo "\nActive product found: {$active->name}\n";
    echo "Slug: {$active->slug}\n";
    
    $request = Illuminate\Http\Request::create("/product/{$active->slug}", 'GET');
    $response = $kernel->handle($request);
    echo "Product page status: {$response->getStatusCode()}\n";
    echo "Response size: " . strlen($response->getContent()) . " bytes\n";
    
    $content = $response->getContent();
    echo "Has CDN images: " . (str_contains($content, 'supremestd.b-cdn.net') ? 'YES' : 'NO') . "\n";
} else {
    echo "No active products found!\n";
    // Debug
    $statuses = App\Models\Product::selectRaw("status, count(*) as cnt")->groupBy('status')->pluck('cnt', 'status');
    echo "Status dist: " . json_encode($statuses) . "\n";
}

// Test 3 random product pages
echo "\n=== Random Product Pages ===\n";
$products = App\Models\Product::take(5)->get();
foreach ($products as $p) {
    $request = Illuminate\Http\Request::create("/product/{$p->slug}", 'GET');
    try {
        $response = $kernel->handle($request);
        $hasImg = str_contains($response->getContent(), 'supremestd.b-cdn.net');
        echo "✓ [{$response->getStatusCode()}] {$p->name} " . ($hasImg ? '[images: YES]' : '[images: NO]') . "\n";
    } catch (\Throwable $e) {
        echo "✗ {$p->name}: " . substr($e->getMessage(), 0, 100) . "\n";
    }
}
