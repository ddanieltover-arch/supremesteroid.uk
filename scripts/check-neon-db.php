<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CHECKING NEON POSTGRES DATABASE ===\n";

try {
    // Test pgsql connection
    $productsCount = Illuminate\Support\Facades\DB::connection('pgsql')->table('products')->count();
    $activeCount = Illuminate\Support\Facades\DB::connection('pgsql')->table('products')->where('status', 'active')->count();
    $categoriesCount = Illuminate\Support\Facades\DB::connection('pgsql')->table('categories')->count();
    $brandsCount = Illuminate\Support\Facades\DB::connection('pgsql')->table('brands')->count();

    echo "Neon Postgres connection: SUCCESS\n";
    echo "Total products in Neon: $productsCount\n";
    echo "Active products in Neon: $activeCount\n";
    echo "Categories in Neon: $categoriesCount\n";
    echo "Brands in Neon: $brandsCount\n";
} catch (\Throwable $e) {
    echo "Neon Postgres connection error: " . $e->getMessage() . "\n";
}
