<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CatalogueImportBatch;
use App\Services\Catalogue\CatalogueOnboardingBatchService;
use Illuminate\Console\Command;

class CatalogueBatchPreviewCommand extends Command
{
    protected $signature = 'catalogue:batch-preview
        {batch : Batch number or UUID}';

    protected $description = 'Preview onboarding batch readiness (READY/BLOCKED) without publishing.';

    public function handle(CatalogueOnboardingBatchService $onboarding): int
    {
        $key = (string) $this->argument('batch');
        $batch = CatalogueImportBatch::query()
            ->where('id', $key)
            ->orWhere('batch_number', $key)
            ->first();

        if (! $batch) {
            $this->error('Batch not found.');

            return self::FAILURE;
        }

        foreach ($onboarding->reportLines($batch) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
