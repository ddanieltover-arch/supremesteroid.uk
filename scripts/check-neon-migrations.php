<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== CHECKING NEON MIGRATIONS ===\n";
try {
    $neonMigrations = Illuminate\Support\Facades\DB::connection('pgsql')->table('migrations')->pluck('migration')->all();
    echo "Neon migrations count: " . count($neonMigrations) . "\n";
    foreach ($neonMigrations as $m) {
        echo "  - $m\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
