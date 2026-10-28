<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$total = App\Models\Product::count();
$active = App\Models\Product::active()->count();
$purchasable = App\Models\Product::purchasable()->count();
$demo = App\Models\Product::where('is_demo', true)->count();
$categories = App\Models\Category::count();
$brands = App\Models\Brand::count();

echo "=== Catalogue Summary ===\n";
echo "Total products:       $total\n";
echo "Active products:      $active\n";
echo "Purchasable products: $purchasable\n";
echo "Demo products:        $demo\n";
echo "Categories:           $categories\n";
echo "Brands:               $brands\n";

// Sample some products
echo "\n--- Sample Products ---\n";
$samples = App\Models\Product::purchasable()->with(['brand','category'])->take(5)->get();
foreach ($samples as $p) {
    echo "  [{$p->sku}] {$p->name} | £{$p->price_amount} | {$p->brand?->name} | {$p->category?->name}\n";
}
