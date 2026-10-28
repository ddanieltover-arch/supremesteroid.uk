<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sqlite = Illuminate\Support\Facades\DB::connection('sqlite');
$pgsql = Illuminate\Support\Facades\DB::connection('pgsql');

$insertOrder = [
    'users',
    'brands',
    'categories',
    'shipping_zones',
    'shipping_methods',
    'shipping_rules',
    'pages',
    'blog_posts',
    'homepage_contents',
    'homepage_faqs',
    'products',
    'inventory',
    'product_images',
    'product_category',
    'product_contents',
    'product_revisions',
    'seo_metadata',
];

$deleteOrder = array_reverse($insertOrder);

echo "=== CLEANING EXISTING NEON TABLES ===\n";
foreach ($deleteOrder as $table) {
    try {
        $deleted = $pgsql->table($table)->delete();
        echo "  Cleared $table ($deleted rows deleted)\n";
    } catch (\Throwable $e) {
        echo "  Could not clear $table: " . $e->getMessage() . "\n";
    }
}

echo "\n=== INSERTING DATA FROM SQLITE TO NEON ===\n";
foreach ($insertOrder as $table) {
    echo "Syncing $table... ";

    $columns = $pgsql->select(
        "SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ?",
        [$table]
    );

    $boolColumns = [];
    $jsonColumns = [];
    foreach ($columns as $col) {
        if ($col->data_type === 'boolean') {
            $boolColumns[] = $col->column_name;
        } elseif (in_array($col->data_type, ['json', 'jsonb'])) {
            $jsonColumns[] = $col->column_name;
        }
    }

    $rows = $sqlite->table($table)->get();
    $total = $rows->count();

    if ($total === 0) {
        echo "0 rows (skipped)\n";
        continue;
    }

    $chunks = $rows->chunk(50);
    $inserted = 0;

    foreach ($chunks as $chunk) {
        $data = [];
        foreach ($chunk as $row) {
            $record = (array) $row;

            foreach ($boolColumns as $bCol) {
                if (array_key_exists($bCol, $record)) {
                    $record[$bCol] = $record[$bCol] !== null ? (bool) $record[$bCol] : null;
                }
            }

            foreach ($jsonColumns as $jCol) {
                if (array_key_exists($jCol, $record) && $record[$jCol] !== null) {
                    if (!is_string($record[$jCol])) {
                        $record[$jCol] = json_encode($record[$jCol]);
                    }
                }
            }

            $data[] = $record;
        }

        $pgsql->table($table)->insert($data);
        $inserted += count($data);
    }

    echo "OK ($inserted / $total)\n";
}

echo "\n=== VERIFYING NEON COUNTS ===\n";
foreach ($insertOrder as $table) {
    $count = $pgsql->table($table)->count();
    echo sprintf("  %-25s : %d rows in Neon\n", $table, $count);
}

echo "\nSync to Neon Complete!\n";
