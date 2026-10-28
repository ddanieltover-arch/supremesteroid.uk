<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sqliteTables = Illuminate\Support\Facades\DB::connection('sqlite')
    ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");

echo "=== SQLITE TABLES & ROW COUNTS ===\n";
foreach ($sqliteTables as $t) {
    $table = $t->name;
    $count = Illuminate\Support\Facades\DB::connection('sqlite')->table($table)->count();
    echo sprintf("  %-35s : %d rows\n", $table, $count);
}
