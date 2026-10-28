<?php

/**
 * Scrape the content of all reference pages & blog posts from supremesteroids.co.uk
 */

$pagesToScrape = [
    'faq' => 'https://supremesteroids.co.uk/faq',
    'bpc-157-uk' => 'https://supremesteroids.co.uk/bpc-157-uk',
    'thebsl24' => 'https://supremesteroids.co.uk/thebsl24',
    'terms-conditions' => 'https://supremesteroids.co.uk/terms-conditions',
    'return-policy' => 'https://supremesteroids.co.uk/return-policy',
    'xmas-updates' => 'https://supremesteroids.co.uk/xmas-updates',
    // Blog posts
    'primobolan-vs-masteron-which-one-is-better-for-cutting-goals' => 'https://supremesteroids.co.uk/primobolan-vs-masteron-which-one-is-better-for-cutting-goals',
    'is-oatmeal-good-for-weight-loss' => 'https://supremesteroids.co.uk/is-oatmeal-good-for-weight-loss',
    'ecdysterone-benefits-what-you-need-to-know' => 'https://supremesteroids.co.uk/ecdysterone-benefits-what-you-need-to-know',
    'why-is-dianabol-dying' => 'https://supremesteroids.co.uk/why-is-dianabol-dying',
    'what-is-muscle-memory-a-detailed-guide' => 'https://supremesteroids.co.uk/what-is-muscle-memory-a-detailed-guide',
    'what-is-somatic-exercise-a-detailed-guide' => 'https://supremesteroids.co.uk/what-is-somatic-exercise-a-detailed-guide',
    'can-you-mix-boldenone-with-testosterone' => 'https://supremesteroids.co.uk/can-you-mix-boldenone-with-testosterone',
    'how-much-ipamorelin-should-you-take-dosage-timing-cycles-explained' => 'https://supremesteroids.co.uk/how-much-ipamorelin-should-you-take-dosage-timing-cycles-explained',
    'what-if-i-took-anavar-pre-workout' => 'https://supremesteroids.co.uk/what-if-i-took-anavar-pre-workout',
];

$results = [];

foreach ($pagesToScrape as $slug => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || empty($html)) {
        echo "❌ [{$slug}] HTTP {$code}\n";
        continue;
    }

    // Extract title
    $title = '';
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags($m[1])));
        $title = preg_replace('/\s*[-–|].*$/u', '', $title); // strip site title
    }

    // Extract H1
    $h1 = '';
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
        $h1 = trim(html_entity_decode(strip_tags($m[1])));
    }

    // Extract main content / article
    $content = '';
    if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $m)) {
        $content = $m[1];
    } elseif (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $m)) {
        $content = $m[1];
    } elseif (preg_match('/<div[^>]*class="[^"]*entry-content[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m)) {
        $content = $m[1];
    }

    echo "✓ [{$slug}] Title: {$title} | H1: {$h1} | Content len: " . strlen($content) . "\n";

    $results[$slug] = [
        'slug' => $slug,
        'title' => $h1 ?: $title,
        'seo_title' => $title,
        'url' => $url,
        'html' => $html,
    ];
}

file_put_contents('storage/app/catalogue-import/scraped-reference-pages.json', json_encode($results, JSON_PRETTY_PRINT));
echo "\nSaved " . count($results) . " pages to storage/app/catalogue-import/scraped-reference-pages.json\n";
