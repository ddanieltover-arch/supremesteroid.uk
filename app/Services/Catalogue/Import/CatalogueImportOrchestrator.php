<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\CatalogueSourceReader;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportWorkflowStatus;
use App\Models\CatalogueImportBatch;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\CatalogueImportService;
use App\Services\Catalogue\CatalogueOnboardingBatchService;
use App\Support\QualityAssuranceGuard;

class CatalogueImportOrchestrator
{
    public function __construct(
        protected CatalogueImportService $importService,
        protected AuditLogger $auditLogger,
        protected CatalogueOnboardingBatchService $onboarding,
    ) {}

    public function import(
        CatalogueSourceReader $reader,
        bool $dryRun = false,
        ?string $batchNumber = null,
        ?int $limit = null
    ): CatalogueImportReport {
        if (! $dryRun) {
            QualityAssuranceGuard::assertSafeEnvironment();
        }

        // Keep initial onboarding batches manageable (10–25 recommended).
        $limit ??= (int) config('catalogue.import.default_batch_limit', 25);
        $limit = max(1, min(100, $limit));

        $batchNumber ??= $this->nextBatchNumber();
        $report = new CatalogueImportReport(
            batchNumber: $batchNumber,
            sourceName: $reader->sourceName(),
            sourceType: $reader->sourceType(),
            dryRun: $dryRun,
        );

        $batch = null;
        if (! $dryRun) {
            $batch = CatalogueImportBatch::query()->create([
                'batch_number' => $batchNumber,
                'status' => ImportBatchStatus::IMPORTING,
                'source_name' => $reader->sourceName(),
                'source_type' => $reader->sourceType(),
                'is_dry_run' => false,
                'max_records' => $limit,
                'started_by' => auth()->id(),
                'started_at' => now(),
            ]);
            $report->batch = $batch;

            $this->auditLogger->log(
                eventType: 'catalogue_import.batch_created',
                auditable: $batch,
                newValues: [
                    'batch_number' => $batchNumber,
                    'status' => ImportBatchStatus::IMPORTING->value,
                    'max_records' => $limit,
                ],
                reason: 'Catalogue onboarding batch created.'
            );
        }

        $chunk = [];
        $chunkSize = min(25, $limit);
        $processed = 0;

        foreach ($reader->records() as $source) {
            if ($processed >= $limit) {
                break;
            }
            $chunk[] = $source;
            $processed++;
            if (count($chunk) >= $chunkSize) {
                $this->processChunk($chunk, $report, $batch?->id, $dryRun);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->processChunk($chunk, $report, $batch?->id, $dryRun);
        }

        if ($batch) {
            $batch->fill([
                'source_records' => $report->sourceRecords,
                'new_count' => $report->new,
                'possible_duplicates' => $report->possibleDuplicates,
                'exact_matches' => $report->exactMatches,
                'compliance_review' => $report->complianceReview,
                'rejected' => $report->rejected,
                'needs_review' => $report->needsReview,
                'review_count' => $report->needsReview + $report->complianceReview,
                'rejected_count' => $report->rejected,
                'approved_count' => 0,
                'summary' => $report->totals(),
                'finished_at' => now(),
                'status' => ImportBatchStatus::UNDER_REVIEW,
            ])->save();

            $this->onboarding->refresh($batch);

            $this->auditLogger->log(
                eventType: 'catalogue_import.batch_completed',
                auditable: $batch,
                newValues: $report->totals() + ['status' => $batch->fresh()->status?->value],
                reason: 'Catalogue import batch finished staging and entered review.'
            );
        }

        return $report;
    }

    /**
     * @param  list<array<string, mixed>>  $chunk
     */
    protected function processChunk(array $chunk, CatalogueImportReport $report, ?string $batchUuid, bool $dryRun): void
    {
        foreach ($chunk as $source) {
            $record = $this->importService->stage(
                $source,
                $report->batchNumber,
                $report->sourceName,
                [
                    'dry_run' => $dryRun,
                    'batch_uuid' => $batchUuid,
                ]
            );

            $report->sourceRecords++;
            if (! $record->hasSourceSku()) {
                $report->missingSourceSkus++;
            }
            $this->tally($report, $record->match_status, $record->workflow_status);
            $report->rows[] = [
                'reference' => $record->source_product_reference,
                'name' => $record->displayName(),
                'source_sku' => $record->sourceSku(),
                'internal_sku' => $record->product?->sku,
                'match_status' => $record->match_status?->value,
                'workflow_status' => $record->workflow_status?->value,
                'issues' => $record->issues ?? [],
            ];
        }
    }

    protected function tally(CatalogueImportReport $report, ?ImportMatchStatus $match, ?ImportWorkflowStatus $workflow): void
    {
        match ($match) {
            ImportMatchStatus::NEW => $report->new++,
            ImportMatchStatus::POSSIBLE_DUPLICATE => $report->possibleDuplicates++,
            ImportMatchStatus::EXACT_MATCH => $report->exactMatches++,
            default => null,
        };

        if ($workflow === ImportWorkflowStatus::COMPLIANCE_REVIEW) {
            $report->complianceReview++;
        }

        if (in_array($workflow, [ImportWorkflowStatus::REJECTED, ImportWorkflowStatus::DUPLICATE], true)) {
            $report->rejected++;
        }

        if (in_array($workflow, [
            ImportWorkflowStatus::REVIEW_REQUIRED,
            ImportWorkflowStatus::CONTENT_REVIEW,
            ImportWorkflowStatus::PRICING_REVIEW,
            ImportWorkflowStatus::MEDIA_REVIEW,
        ], true)) {
            $report->needsReview++;
        }
    }

    protected function nextBatchNumber(): string
    {
        $year = now()->format('Y');
        $latest = CatalogueImportBatch::query()
            ->where('batch_number', 'like', $year.'-%')
            ->orderByDesc('batch_number')
            ->value('batch_number');

        $sequence = 1;
        if (is_string($latest) && preg_match('/^'.$year.'-(\d+)$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%03d', $year, $sequence);
    }
}
