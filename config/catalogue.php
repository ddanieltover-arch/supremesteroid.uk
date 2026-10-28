<?php

declare(strict_types=1);

return [
    'import' => [
        // Remote HTTP(S) image copy after admin approval. Local/testing/qa always allow fixture paths.
        'allow_remote_image_copy' => (bool) env('CATALOGUE_ALLOW_REMOTE_IMAGE_COPY', false),
        // Recommended onboarding batch size (hard-capped at 100 in the orchestrator).
        'default_batch_limit' => (int) env('CATALOGUE_IMPORT_BATCH_LIMIT', 25),
        'reference_site' => [
            'base_url' => env('CATALOGUE_REFERENCE_BASE_URL', 'https://supremesteroids.co.uk'),
            'delay_ms' => (int) env('CATALOGUE_REFERENCE_DELAY_MS', 2000),
            'timeout_seconds' => 20,
            'max_pages' => 30,
            'max_eligible' => 20,
        ],
        // Public HTML / JSON-LD bulk source (sitemap + paced product pages; no credentials).
        'public_html' => [
            'base_url' => env('CATALOGUE_REFERENCE_BASE_URL', 'https://supremesteroids.co.uk'),
            'delay_ms' => (int) env('CATALOGUE_REFERENCE_DELAY_MS', 2000),
            'timeout_seconds' => 20,
            'max_page_fetches' => (int) env('CATALOGUE_PUBLIC_HTML_MAX_PAGE_FETCHES', 100),
            'fetch_restricted_pages' => (bool) env('CATALOGUE_PUBLIC_HTML_FETCH_RESTRICTED', false),
            'sitemap_cache' => 'catalogue-import/audits/product-sitemap-urls.json',
        ],
        // Authorized WooCommerce REST bulk source (never used without credentials).
        'woocommerce' => [
            'base_url' => env('CATALOGUE_WC_BASE_URL', env('CATALOGUE_REFERENCE_BASE_URL', 'https://supremesteroids.co.uk')),
            'consumer_key' => env('CATALOGUE_WC_CONSUMER_KEY'),
            'consumer_secret' => env('CATALOGUE_WC_CONSUMER_SECRET'),
            'timeout_seconds' => (int) env('CATALOGUE_WC_TIMEOUT_SECONDS', 20),
            'page_size' => (int) env('CATALOGUE_WC_PAGE_SIZE', 25),
        ],
    ],
];
