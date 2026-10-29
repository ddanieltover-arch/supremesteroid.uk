<?php

declare(strict_types=1);

return [
    'seo' => [
        'title' => env('HOMEPAGE_SEO_TITLE', 'Supreme Steroids: Buy Oral and Injectable Steroids Online'),
        'description' => env(
            'HOMEPAGE_SEO_DESCRIPTION',
            'Buy oral and injectable Supreme Steroids online in the UK. SupremeSteroids offers Testosterone, bulking, cutting, and PCT with fast delivery. Get 5% off now'
        ),
    ],

    'announcement' => [
        'enabled' => true,
        'message' => 'Pay with Bitcoin to get 20% off (discount applies at checkout) | Fast & Discreet Next-Day UK Delivery',
        'use_shipping_threshold' => true,
        'dismissible' => false,
    ],

    'hero' => [
        'eyebrow' => 'Official Supreme Steroids UK Store',
        'headline' => 'Buy Oral & Injectable Steroids Online in the UK',
        'description' => 'Dedicated UK source for authentic, lab-tested anabolic steroids, SARMs, peptides, and PCT products from leading pharmaceutical brands. Fast, discreet next-day delivery.',
        'primary_cta' => ['label' => 'Explore Catalogue', 'url' => '/shop'],
        'secondary_cta' => ['label' => 'Browse Categories', 'url' => '/#categories'],
    ],

    'editorial' => [
        'enabled' => true,
        'label' => 'Verified Quality & Purity',
        'heading' => 'Authentic, Lab-Tested Performance Brands',
        'body' => 'Every compound in our catalogue is sourced directly from recognised pharmaceutical manufacturers including Pharmaqo Labs, Proper Labs, Beligas, and Deus Medical. Fully sealed, batch-verifiable, and compliant.',
        'cta' => ['label' => 'View Full Catalogue', 'url' => '/shop'],
        'image_url' => null,
    ],

    'trust_items' => [
        ['title' => 'Lab-Tested & Certified', 'body' => 'All products are independently lab-tested for purity, potency, and safety from registered manufacturers.'],
        ['title' => 'Discreet Next-Day UK Shipping', 'body' => 'Orders are dispatched in plain, secure packaging with tracking provided for 100% peace of mind.'],
        ['title' => 'Convenient Payment Methods', 'body' => 'Flexible and secure payments including debit/credit cards, bank transfers, and crypto with 20% discount.'],
        ['title' => 'Leading Verified Brands', 'body' => 'Official stockist for Pharmaqo Labs, Proper Labs, IMUSCLE SARMs, Beligas Pharmaceuticals, and more.'],
        ['title' => 'Expert Guidance & Support', 'body' => 'Transparent composition, dosage details, and cycle advice backed by responsive customer support.'],
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
        ['key' => 'featured', 'position' => 20, 'enabled' => true],
        ['key' => 'new', 'position' => 30, 'enabled' => true],
        ['key' => 'offers', 'position' => 40, 'enabled' => true],
        ['key' => 'categories', 'position' => 50, 'enabled' => true],
        ['key' => 'intent', 'position' => 60, 'enabled' => true],
        ['key' => 'brands', 'position' => 70, 'enabled' => true],
        ['key' => 'editorial', 'position' => 80, 'enabled' => true],
        ['key' => 'trust', 'position' => 90, 'enabled' => true],
        ['key' => 'shipping', 'position' => 100, 'enabled' => true],
        ['key' => 'faq', 'position' => 110, 'enabled' => true],
        ['key' => 'newsletter', 'position' => 120, 'enabled' => true],
    ],

    'policy_pages' => [
        ['slug' => 'about-us', 'title' => 'About Us'],
        ['slug' => 'contact-us', 'title' => 'Contact Us'],
        ['slug' => 'delivery-info', 'title' => 'Delivery & Shipping Information'],
        ['slug' => 'returns-refunds', 'title' => 'Returns & Refunds'],
        ['slug' => 'terms-conditions', 'title' => 'Terms & Conditions'],
        ['slug' => 'privacy-policy', 'title' => 'Privacy Policy'],
        ['slug' => 'cookies', 'title' => 'Cookie Policy'],
        ['slug' => 'faq', 'title' => 'FAQ'],
        ['slug' => 'payment-methods', 'title' => 'Payment Methods'],
        ['slug' => 'lab-tests', 'title' => 'Lab Tests & Authenticity'],
        ['slug' => 'reviews', 'title' => 'Customer Reviews'],
        ['slug' => 'bpc-157-uk', 'title' => 'BPC 157 UK Guide'],
        ['slug' => 'thebsl24', 'title' => 'The BSL 24'],
        ['slug' => 'xmas-updates', 'title' => 'Holiday Updates'],
    ],
];
