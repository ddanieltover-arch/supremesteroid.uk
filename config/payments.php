<?php

declare(strict_types=1);

return [
    /*
    | Customer-visible settlement instructions. Values come from environment
    | configuration — never hard-code production account numbers in React.
    |
    | Bank transfer is offered only when the checkout grand total is at or above
    | bank_transfer_minimum_total (GBP). Below that threshold, cryptocurrency only.
    */
    'bank_transfer_minimum_total' => (float) env('BANK_TRANSFER_MINIMUM_TOTAL', 100),

    'methods' => [
        [
            'code' => 'BANK_TRANSFER',
            'name' => 'Manual Bank Transfer',
            'description' => 'Pay by bank transfer using the account details shown after you place the order. Staff verify the payment before the order is marked paid.',
        ],
        [
            'code' => 'CRYPTOCURRENCY',
            'name' => 'Manual Cryptocurrency',
            'description' => 'Pay using a supported cryptocurrency network. Settlement is reviewed before the order is marked paid.',
        ],
    ],
    'bank_transfer' => [
        'account_name' => env('BANK_ACCOUNT_NAME'),
        'sort_code' => env('BANK_SORT_CODE'),
        'account_number' => env('BANK_ACCOUNT_NUMBER'),
        'iban' => env('BANK_IBAN'),
        'bic' => env('BANK_BIC'),
        'bank_name' => env('BANK_NAME'),
        'reference_hint' => env('BANK_REFERENCE_HINT', 'Use your order number as the payment reference.'),
    ],

    'crypto' => [
        'networks' => array_values(array_filter([
            env('CRYPTO_BTC_ADDRESS') ? [
                'code' => 'BTC',
                'label' => 'Bitcoin (BTC)',
                'address' => env('CRYPTO_BTC_ADDRESS'),
            ] : null,
            env('CRYPTO_ETH_ADDRESS') ? [
                'code' => 'ETH',
                'label' => 'Ethereum (ETH)',
                'address' => env('CRYPTO_ETH_ADDRESS'),
            ] : null,
            env('CRYPTO_USDT_TRC20_ADDRESS') ? [
                'code' => 'USDT_TRC20',
                'label' => 'Tether USDT (TRC-20)',
                'address' => env('CRYPTO_USDT_TRC20_ADDRESS'),
            ] : null,
            env('CRYPTO_USDT_ERC20_ADDRESS') ? [
                'code' => 'USDT_ERC20',
                'label' => 'Tether USDT (ERC-20)',
                'address' => env('CRYPTO_USDT_ERC20_ADDRESS'),
            ] : null,
        ])),
        'reference_hint' => env('CRYPTO_REFERENCE_HINT', 'Submit the transaction hash after sending the exact order total.'),
    ],
];
