<?php

declare(strict_types=1);

return [
    'default' => env('FILESYSTEM_DISK') ?: 'local',

    'cloud' => env('OBJECT_STORAGE_DISK') ?: (env('FILESYSTEM_DISK') ?: 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'auto'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('R2_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_BUCKET', env('AWS_BUCKET')),
            'url' => env('R2_URL', env('AWS_URL')),
            'endpoint' => env('R2_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'throw' => false,
            'report' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

    'uploads' => [
        'max_kilobytes' => (int) env('UPLOAD_MAX_KILOBYTES', 10240),
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        'payment_proofs_collection' => 'payment_proofs',
        'product_images_collection' => 'product_images',
        'cms_collection' => 'cms',
        'signed_url_minutes' => (int) env('OBJECT_STORAGE_SIGNED_URL_MINUTES', 30),
    ],
];
