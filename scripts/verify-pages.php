<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$routes = [
    '/' => 'Homepage',
    '/shop' => 'Shop/Products',
    '/blog' => 'Blog',
    '/faq' => 'FAQ',
    '/about-us' => 'About Us',
    '/contact-us' => 'Contact Us',
    '/delivery-info' => 'Delivery Info',
    '/lab-tests' => 'Lab Tests',
    '/payment-methods' => 'Payment Methods',
    '/reviews' => 'Customer Reviews',
    '/returns-refunds' => 'Returns & Refunds',
    '/terms-conditions' => 'Terms & Conditions',
    '/privacy-policy' => 'Privacy Policy',
    '/login' => 'Login',
    '/register' => 'Register',
    '/cart' => 'Cart',
    '/search' => 'Search',
    '/category/oral-steroids' => 'Category: Oral Steroids',
    '/category/injectable-steroids' => 'Category: Injectable Steroids',
];

echo "=== Page Verification Report ===\n\n";
$passed = 0;
$failed = 0;

foreach ($routes as $uri => $label) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    try {
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $content = $response->getContent();
        $length = strlen($content);
        
        // Check if response contains product images (for shop/category pages)
        $hasImages = false;
        if (in_array($uri, ['/shop', '/category/oral-steroids', '/category/injectable-steroids'])) {
            $hasImages = str_contains($content, 'supremestd.b-cdn.net') || str_contains($content, 'image_url');
        }
        
        $icon = $status === 200 ? '✓' : '✗';
        echo "$icon [$status] $label ($uri) - {$length} bytes";
        if ($hasImages) {
            echo " [images: YES]";
        }
        echo "\n";
        
        if ($status === 200) {
            $passed++;
        } else {
            $failed++;
        }
    } catch (\Throwable $e) {
        echo "✗ [ERR] $label ($uri) - " . $e->getMessage() . "\n";
        $failed++;
    }
    
    $app->make(Illuminate\Contracts\Http\Kernel::class)->terminate($request, $response ?? new Illuminate\Http\Response());
}

echo "\n=== Summary ===\n";
echo "Passed: $passed / " . count($routes) . "\n";
echo "Failed: $failed / " . count($routes) . "\n";

// Also check a sample product page
echo "\n=== Sample Product Page ===\n";
$product = App\Models\Product::where('status', 'active')->first();
if ($product) {
    $request = Illuminate\Http\Request::create("/product/{$product->slug}", 'GET');
    try {
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $content = $response->getContent();
        $hasProductImage = str_contains($content, 'supremestd.b-cdn.net') || str_contains($content, 'image_url');
        echo "✓ [$status] Product: {$product->name} (/product/{$product->slug}) - " . strlen($content) . " bytes";
        if ($hasProductImage) echo " [images: YES]";
        echo "\n";
    } catch (\Throwable $e) {
        echo "✗ [ERR] Product: {$product->name} - " . $e->getMessage() . "\n";
    }
}
