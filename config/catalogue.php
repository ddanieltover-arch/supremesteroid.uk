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
    ],
];
