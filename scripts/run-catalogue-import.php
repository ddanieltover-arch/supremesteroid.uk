<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\CatalogueImportBatch;
use App\Models\CatalogueImportRecord;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalogue\Import\ArrayCatalogueSourceReader;
use App\Services\Catalogue\Import\CatalogueImportOrchestrator;
use App\Support\QualityAssuranceGuard;

$dryRun = false;
$batchSize = 50;
$limit = null;

foreach ($argv as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    }
    if (str_starts_with($arg, '--batch-size=')) {
        $batchSize = max(10, min(100, (int) substr($arg, 13)));
    }
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

echo "========================================================\n";
echo "   Supreme Steroids Catalogue Batch Importer\n";
echo "   Mode: " . ($dryRun ? "DRY RUN (no database changes)" : "LIVE STAGING") . "\n";
echo "   Batch size: {$batchSize}\n";
echo "========================================================\n\n";

if (!$dryRun) {
    QualityAssuranceGuard::assertSafeEnvironment();
}

$jsonPath = base_path('storage/app/catalogue-import/reference-full-catalogue.json');
if (!is_file($jsonPath)) {
    echo "ERROR: Catalogue JSON file not found at: {$jsonPath}\n";
    echo "Please run: php scripts/scrape-reference-catalogue.php first.\n";
    exit(1);
}

$raw = file_get_contents($jsonPath);
$data = json_decode((string) $raw, true);
if (!is_array($data) || empty($data['products'])) {
    echo "ERROR: No products found in JSON file.\n";
    exit(1);
}

$products = $data['products'];
if ($limit !== null) {
    $products = array_slice($products, 0, $limit);
}

$totalProducts = count($products);
$chunks = array_chunk($products, $batchSize);
$totalBatches = count($chunks);

echo "Loaded {$totalProducts} products to import across {$totalBatches} batches.\n\n";

$orchestrator = app(CatalogueImportOrchestrator::class);

$grandTotals = [
    'source_records' => 0,
    'new' => 0,
    'exact_matches' => 0,
    'possible_duplicates' => 0,
    'compliance_review' => 0,
    'needs_review' => 0,
    'rejected' => 0,
];

foreach ($chunks as $idx => $chunk) {
    $batchNum = sprintf('SUPREME-%03d', $idx + 1);
    echo "--- Processing Batch [{$batchNum}] (" . count($chunk) . " records) ---\n";

    $reader = new ArrayCatalogueSourceReader(
        records: $chunk,
        sourceName: 'supremesteroids.co.uk',
        sourceType: 'json-seed'
    );

    $report = $orchestrator->import(
        reader: $reader,
        dryRun: $dryRun,
        batchNumber: $batchNum,
        limit: count($chunk)
    );

    foreach ($report->lines() as $line) {
        echo "  " . $line . "\n";
    }

    $grandTotals['source_records'] += $report->sourceRecords;
    $grandTotals['new'] += $report->new;
    $grandTotals['exact_matches'] += $report->exactMatches;
    $grandTotals['possible_duplicates'] += $report->possibleDuplicates;
    $grandTotals['compliance_review'] += $report->complianceReview;
    $grandTotals['needs_review'] += $report->needsReview;
    $grandTotals['rejected'] += $report->rejected;

    echo "\n";
}

echo "========================================================\n";
echo "   Import Summary\n";
echo "========================================================\n";
echo " Total Batches:       {$totalBatches}\n";
echo " Total Records:       {$grandTotals['source_records']}\n";
echo " New Products:        {$grandTotals['new']}\n";
echo " Exact Matches:       {$grandTotals['exact_matches']}\n";
echo " Possible Duplicates: {$grandTotals['possible_duplicates']}\n";
echo " Compliance Review:   {$grandTotals['compliance_review']}\n";
echo " Content Review:      {$grandTotals['needs_review']}\n";
echo " Rejected:            {$grandTotals['rejected']}\n";
echo "\n";

if (!$dryRun) {
    echo "--- Database Status ---\n";
    echo " Total Products:          " . Product::count() . "\n";
    echo "   - In Compliance Review: " . Product::where('status', ProductStatus::COMPLIANCE_REVIEW)->count() . "\n";
    echo "   - In Imported Status:   " . Product::where('status', ProductStatus::IMPORTED)->count() . "\n";
    echo " Total Categories:        " . Category::count() . "\n";
    echo " Total Brands:            " . Brand::count() . "\n";
    echo " Total Import Records:    " . CatalogueImportRecord::count() . "\n";
    echo " Total Import Batches:    " . CatalogueImportBatch::count() . "\n";
}
echo "========================================================\n";
