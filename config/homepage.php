<?php

declare(strict_types=1);

return [
    'seo' => [
        'title' => env('HOMEPAGE_SEO_TITLE', 'Supreme Steroids | Premium Product Collection'),
        'description' => env(
            'HOMEPAGE_SEO_DESCRIPTION',
            'Explore a curated UK catalogue of published, compliance-approved products with server-confirmed pricing, availability, and checkout.'
        ),
    ],

    'announcement' => [
        'enabled' => true,
        'message' => null,
        'use_shipping_threshold' => true,
        'dismissible' => true,
    ],

    'hero' => [
        'eyebrow' => 'UK catalogue',
        'headline' => 'Precision in product. Clarity in information.',
        'description' => 'Browse a curated collection of published products. Prices, availability, and eligibility are confirmed on the server before you check out.',
        'primary_cta' => ['label' => 'Explore Collection', 'url' => '/shop'],
        'secondary_cta' => ['label' => 'Shop Categories', 'url' => '/#categories'],
    ],

    'editorial' => [
        'enabled' => true,
        'label' => 'The collection',
        'heading' => 'Discover a curated catalogue',
        'body' => 'Every product shown as purchasable has passed the store publication and compliance gate. Information, documents, and offers appear only when a verified record exists.',
        'cta' => ['label' => 'View the catalogue', 'url' => '/shop'],
        'image_url' => null,
    ],

    'trust_items' => [
        ['title' => 'Transparent product information', 'body' => 'Specifications, documents, and prices come from published catalogue records rather than promotional copy.'],
        ['title' => 'Secure account and checkout', 'body' => 'Basket totals and payment review stay on the server. Browser values cannot change an order.'],
        ['title' => 'Tracked delivery options', 'body' => 'Shipping methods and free-delivery eligibility are calculated from the live configured rates.'],
        ['title' => 'Responsive customer support', 'body' => 'Contact the store for order, documentation, or delivery questions using the published support email.'],
        ['title' => 'Document availability', 'body' => 'Supporting documents appear only when a verified record is attached to the product.'],
    ],

    'newsletter' => [
        'enabled' => true,
        'heading' => 'Catalogue updates',
        'body' => 'Receive occasional emails about published products and store information. Consent is required.',
    ],

    'social' => [
        'instagram' => env('STORE_SOCIAL_INSTAGRAM'),
        'facebook' => env('STORE_SOCIAL_FACEBOOK'),
        'x' => env('STORE_SOCIAL_X'),
        'youtube' => env('STORE_SOCIAL_YOUTUBE'),
    ],

    'sections' => [
        ['key' => 'hero', 'position' => 10, 'enabled' => true],
        ['key' => 'categories', 'position' => 20, 'enabled' => true],
        ['key' => 'featured', 'position' => 30, 'enabled' => true],
        ['key' => 'new', 'position' => 40, 'enabled' => true],
        ['key' => 'offers', 'position' => 50, 'enabled' => true],
        ['key' => 'intent', 'position' => 60, 'enabled' => true],
        ['key' => 'brands', 'position' => 70, 'enabled' => true],
        ['key' => 'editorial', 'position' => 80, 'enabled' => true],
        ['key' => 'trust', 'position' => 90, 'enabled' => true],
        ['key' => 'shipping', 'position' => 100, 'enabled' => true],
        ['key' => 'faq', 'position' => 110, 'enabled' => true],
        ['key' => 'newsletter', 'position' => 120, 'enabled' => true],
    ],

    'policy_pages' => [
        ['slug' => 'about', 'title' => 'About'],
        ['slug' => 'contact', 'title' => 'Contact'],
        ['slug' => 'shipping', 'title' => 'Shipping'],
        ['slug' => 'returns', 'title' => 'Returns'],
        ['slug' => 'privacy', 'title' => 'Privacy'],
        ['slug' => 'cookies', 'title' => 'Cookies'],
        ['slug' => 'terms', 'title' => 'Terms'],
        ['slug' => 'faq', 'title' => 'FAQ'],
    ],
];
