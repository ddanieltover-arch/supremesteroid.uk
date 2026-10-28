<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Enums\ImportMappingStatus;
use App\Models\Brand;
use App\Models\CatalogueBrandMapping;
use App\Models\CatalogueCategoryMapping;
use App\Models\Category;
use App\Services\Catalogue\Import\CatalogueMappingService;
use Illuminate\Support\Str;

echo "=== Seeding Catalogue Structure (Brands & Categories) ===\n";

$jsonPath = base_path('storage/app/catalogue-import/reference-full-catalogue.json');
if (!is_file($jsonPath)) {
    echo "ERROR: Catalogue JSON file not found: {$jsonPath}\n";
    exit(1);
}

$data = json_decode((string) file_get_contents($jsonPath), true);
if (!is_array($data)) {
    echo "ERROR: Invalid JSON file.\n";
    exit(1);
}

$mappingService = app(CatalogueMappingService::class);

// 1. SEED BRANDS
echo "\n[1/3] Seeding Brands...\n";
$brands = $data['brands'] ?? [];
$brandsCreated = 0;
$brandsUpdated = 0;

foreach ($brands as $b) {
    $slug = $b['slug'];
    $name = $b['name'];

    $brand = Brand::query()->where('slug', $slug)->first();
    if ($brand) {
        $brand->update(['name' => $name, 'is_active' => true]);
        $brandsUpdated++;
    } else {
        $brand = Brand::query()->create([
            'slug' => $slug,
            'name' => $name,
            'is_active' => true,
        ]);
        $brandsCreated++;
    }

    // Auto-approve brand mapping
    $normalized = $mappingService->normalizeLabel($name);
    CatalogueBrandMapping::query()->updateOrCreate(
        ['normalized_source_brand' => $normalized],
        [
            'source_brand' => $name,
            'target_brand_id' => $brand->id,
            'status' => ImportMappingStatus::APPROVED,
            'match_hint' => 'exact',
            'approved_at' => now(),
        ]
    );
}
echo "Brands processed: {$brandsCreated} created, {$brandsUpdated} existing.\n";

// 2. SEED CATEGORIES WITH HIERARCHY
echo "\n[2/3] Seeding Categories (hierarchical)...\n";
$categories = $data['categories'] ?? [];

// Sort by level ascending so parents are created before children
usort($categories, fn($a, $b) => ($a['level'] ?? 1) <=> ($b['level'] ?? 1));

$catsCreated = 0;
$catsUpdated = 0;
$categoryMap = []; // slug => Category model

foreach ($categories as $c) {
    $slug = $c['slug'];
    $name = $c['name'];
    $parentSlug = $c['parent_slug'] ?? null;

    $parentId = null;
    if ($parentSlug && isset($categoryMap[$parentSlug])) {
        $parentId = $categoryMap[$parentSlug]->id;
    } elseif ($parentSlug) {
        $parent = Category::query()->where('slug', $parentSlug)->first();
        $parentId = $parent?->id;
    }

    $category = Category::query()->where('slug', $slug)->first();
    if ($category) {
        $category->update([
            'name' => $name,
            'parent_id' => $parentId,
            'is_visible' => true,
        ]);
        $catsUpdated++;
    } else {
        $category = Category::query()->create([
            'slug' => $slug,
            'name' => $name,
            'parent_id' => $parentId,
            'is_visible' => true,
        ]);
        $catsCreated++;
    }

    $categoryMap[$slug] = $category;

    // Auto-approve category mapping
    $normalized = $mappingService->normalizeLabel($name);
    CatalogueCategoryMapping::query()->updateOrCreate(
        ['normalized_source_category' => $normalized],
        [
            'source_category' => $name,
            'target_category_id' => $category->id,
            'status' => ImportMappingStatus::APPROVED,
            'approved_at' => now(),
        ]
    );

    // Also map path segment variations
    $pathNormalized = $mappingService->normalizeLabel(str_replace('/', ' ', $c['path'] ?? ''));
    if ($pathNormalized !== '' && $pathNormalized !== $normalized) {
        CatalogueCategoryMapping::query()->updateOrCreate(
            ['normalized_source_category' => $pathNormalized],
            [
                'source_category' => $c['path'],
                'target_category_id' => $category->id,
                'status' => ImportMappingStatus::APPROVED,
                'approved_at' => now(),
            ]
        );
    }
}
echo "Categories processed: {$catsCreated} created, {$catsUpdated} existing.\n";

// 3. STATS
echo "\n[3/3] Verification:\n";
echo "Total Brands in database:     " . Brand::count() . "\n";
echo "Total Categories in database: " . Category::count() . "\n";
echo "Root Categories:              " . Category::whereNull('parent_id')->count() . "\n";
echo "Subcategories:                " . Category::whereNotNull('parent_id')->count() . "\n";
echo "Approved Brand Mappings:      " . CatalogueBrandMapping::where('status', ImportMappingStatus::APPROVED)->count() . "\n";
echo "Approved Category Mappings:   " . CatalogueCategoryMapping::where('status', ImportMappingStatus::APPROVED)->count() . "\n";
echo "\nCatalogue Structure Seeding Complete!\n";
