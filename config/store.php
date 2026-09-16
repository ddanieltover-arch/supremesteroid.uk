<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Supreme Steroids Store Identity & Legal Configuration
    |--------------------------------------------------------------------------
    |
    | Defines the store branding, physical address, support email, and the
    | external CDN configuration point for the approved store logo asset.
    |
    */

    'name' => env('STORE_DISPLAY_NAME', 'Supreme Steroids'),
    'legal_name' => env('STORE_LEGAL_NAME', 'Supreme Steroids'),
    'tagline' => null,
    'domain' => env('APP_URL', 'https://supremesteroid.uk'),

    'business_address' => [
        'street' => '8 King Street',
        'locality' => 'Hammersmith',
        'city' => 'London',
        'postal_code' => 'W6 9HW',
        'country' => 'United Kingdom',
        'country_code' => 'GB',
    ],

    'support_email' => env('STORE_SUPPORT_EMAIL', 'info@supremesteroid.uk'),
    'support_phone' => env('STORE_SUPPORT_PHONE', null), // To be added later

    /*
    |--------------------------------------------------------------------------
    | Store Logo Configuration Point
    |--------------------------------------------------------------------------
    |
    | Prevents permanent hotlinking to external reference servers.
    | Points to external object storage (AWS S3 / Cloudflare R2 / CDN).
    |
    */
    'logo_url' => env('STORE_APPROVED_LOGO_URL', '/assets/branding/supreme-steroids-logo.svg'),

    'cron_secret' => env('CRON_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Compliance & Catalogue Safety Enforcement
    |--------------------------------------------------------------------------
    |
    | Strict compliance boundaries: only lawful products approved by compliance
    | review may be exposed to consumers or purchased.
    |
    */
    'compliance' => [
        'enforce_strict_approval' => true,
        'prohibit_medical_dosing' => true,
        'require_co_analysis_for_research' => true,
        'allowed_classifications' => [
            'OTC_CONSUMER',
            'RESEARCH_USE',
            'OTHER_LAWFUL_PRODUCT',
        ],
    ],
];
