<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$pagesToTest = [
    // Content / Institutional pages
    '/about',
    '/contact',
    '/faq',
    '/shipping',
    '/returns',
    '/return-policy',
    '/terms',
    '/terms-conditions',
    '/privacy',
    '/cookies',
    '/xmas-updates',
    '/bpc-157-uk',
    '/thebsl24',
    // Blog Hub
    '/blog',
    // 9 Educational Articles via /blog/{slug}
    '/blog/primobolan-vs-masteron-which-one-is-better-for-cutting-goals',
    '/blog/is-oatmeal-good-for-weight-loss',
    '/blog/ecdysterone-benefits-what-you-need-to-know',
    '/blog/why-is-dianabol-dying',
    '/blog/what-is-muscle-memory-a-detailed-guide',
    '/blog/what-is-somatic-exercise-a-detailed-guide',
    '/blog/can-you-mix-boldenone-with-testosterone',
    '/blog/how-much-ipamorelin-should-you-take-dosage-timing-cycles-explained',
    '/blog/what-if-i-took-anavar-pre-workout',
    // Direct URL check as on reference site
    '/what-if-i-took-anavar-pre-workout',
];

echo "=== Testing All Pages & Articles ===\n\n";

$passed = 0;
$failed = 0;

foreach ($pagesToTest as $uri) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $response = $httpKernel->handle($request);
    $status = $response->getStatusCode();
    
    // Status 200 or 302 (redirect)
    if ($status === 200 || $status === 302) {
        echo "  ✓ [{$status}] {$uri}\n";
        $passed++;
    } else {
        echo "  ❌ [{$status}] {$uri}\n";
        $failed++;
    }
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
