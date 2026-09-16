<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Shipping Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Initial fallback rates and engine rules. Note that production values are
    | persisted in the PostgreSQL database (shipping_zones, shipping_methods,
    | shipping_rules) to allow live administrative modification without code changes.
    |
    */

    'currency' => 'GBP',

    'free_shipping_threshold_gbp' => 300.00,

    'initial_rates' => [
        'uk_standard' => [
            'name' => 'UK Standard Delivery',
            'code' => 'UK_STANDARD',
            'amount' => 10.00,
            'zone' => 'UK_DOMESTIC',
            'estimated_days' => '2-4 business days',
            'is_eligible_for_free_threshold' => true,
        ],
        'uk_express' => [
            'name' => 'UK Express Delivery',
            'code' => 'UK_EXPRESS',
            'amount' => 15.00,
            'zone' => 'UK_DOMESTIC',
            'estimated_days' => '1-2 business days',
            'is_eligible_for_free_threshold' => false,
        ],
        'uk_discreet' => [
            'name' => 'UK Discreet Delivery',
            'code' => 'UK_DISCREET',
            'amount' => 25.00,
            'zone' => 'UK_DOMESTIC',
            'estimated_days' => '1-2 business days',
            'is_eligible_for_free_threshold' => false,
        ],
        'europe' => [
            'name' => 'Europe Tracked Shipping',
            'code' => 'EUROPE_STANDARD',
            'amount' => 35.00,
            'zone' => 'EUROPE',
            'estimated_days' => '5-9 business days',
            'is_eligible_for_free_threshold' => false,
        ],
        'international' => [
            'name' => 'International Priority Shipping',
            'code' => 'INTL_PRIORITY',
            'amount' => 50.00,
            'zone' => 'INTERNATIONAL',
            'estimated_days' => '7-14 business days',
            'is_eligible_for_free_threshold' => false,
        ],
    ],
];
