<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Current Database Pages ===\n";
$pages = App\Models\Page::orderBy('slug')->get();
foreach ($pages as $p) {
    $len = strlen($p->content ?? '');
    echo sprintf("  [%s] /%-20s - %-35s (%d bytes content)\n", $p->is_published ? 'ACTIVE' : 'DRAFT', $p->slug, $p->title, $len);
}
echo "Total: " . $pages->count() . "\n\n";

echo "=== Duplicate Slugs Check (should all be 0) ===\n";
$oldDuplicates = ['about', 'contact', 'shipping', 'returns', 'return-policy', 'terms', 'privacy'];
foreach ($oldDuplicates as $dup) {
    $count = App\Models\Page::where('slug', $dup)->count();
    echo sprintf("  /%-15s : %s\n", $dup, $count === 0 ? "REMOVED (✓)" : "STILL PRESENT (✗)");
}

echo "\n=== ContentPageController Dispatch Check ===\n";
$controller = $app->make(App\Http\Controllers\Customer\ContentPageController::class);
$seo = $app->make(App\Services\Catalogue\SeoService::class);

foreach ($pages as $p) {
    $req = Illuminate\Http\Request::create('/' . $p->slug, 'GET');
    try {
        $res = $controller($req, $p->slug, $seo);
        echo sprintf("  ✓ /%-20s -> %s\n", $p->slug, get_class($res));
    } catch (\Throwable $e) {
        echo sprintf("  ✗ /%-20s -> %s\n", $p->slug, $e->getMessage());
    }
}
