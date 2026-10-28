<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\CatalogueSourceReader;
use App\Contracts\Catalogue\ResumableCatalogueSourceReader;
use App\Enums\BulkIngestRunStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportMatchStatus;
use App\Enums\ImportWorkflowStatus;
use App\Enums\ProductStatus;
use App\Models\CatalogueBulkIngestRun;
use App\Models\CatalogueImportBatch;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Catalogue\CatalogueImportService;
use App\Support\QualityAssuranceGuard;
use RuntimeException;

/**
 * Resumable bulk catalogue ingestion on top of CatalogueImportService.
 * Supports authorized feeds and permitted public HTML/JSON-LD. Never auto-publishes.
 */
class CatalogueBulkIngestService
{
    public function __construct(
        protected CatalogueImportService $importService,
        protected RestrictedProductDetector $detector,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{
     *     run_key: string,
     *     dry_run?: bool,
     *     limit?: ?int,
     *     page_size?: int,
     *     resume?: bool,
     *     batch_number?: ?string
     * }  $options
     */
    public function ingest(CatalogueSourceReader $reader, array $options): CatalogueBulkIngestReport
    {
        $dryRun = (bool) ($options['dry_run'] ?? false);
        if (! $dryRun) {
            QualityAssuranceGuard::assertSafeEnvironment();
        }

        $runKey = trim((string) ($options['run_key'] ?? ''));
        if ($runKey === '') {
            throw new RuntimeException('Bulk ingest requires a run_key.');
        }

        $limit = $options['limit'] ?? null;
        if ($limit !== null) {
            $limit = max(1, min(500, (int) $limit));
        }

        $resume = (bool) ($options['resume'] ?? false);
        $run = CatalogueBulkIngestRun::query()->where('run_key', $runKey)->first();
        $resumed = false;

        if ($run && $resume && is_array($run->cursor) && $reader instanceof ResumableCatalogueSourceReader) {
            $reader->restore($run->cursor);
            $resumed = true;
        } elseif ($run && ! $resume && in_array($run->status, [BulkIngestRunStatus::RUNNING, BulkIngestRunStatus::PAUSED], true)) {
            throw new RuntimeException('Run '.$runKey.' already exists and is resumable. Pass --resume or choose a new --run key.');
        }

        $batchNumber = (string) ($options['batch_number'] ?? ($run?->batch_number ?: $this->nextBatchNumber()));
        $report = new CatalogueBulkIngestReport(
            runKey: $runKey,
            batchNumber: $batchNumber,
            sourceName: $reader->sourceName(),
            sourceType: $reader->sourceType(),
            dryRun: $dryRun,
            resumed: $resumed,
        );

        $batch = null;
        if (! $dryRun) {
            $batch = $run?->importBatch;
            if ($batch === null) {
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
            } else {
                $batch->status = ImportBatchStatus::IMPORTING;
                $batch->save();
            }
            $report->batch = $batch;
        }

        if (! $dryRun) {
            $run ??= new CatalogueBulkIngestRun(['run_key' => $runKey]);
            $run->fill([
                'source_type' => $reader->sourceType(),
                'source_name' => $reader->sourceName(),
                'status' => BulkIngestRunStatus::RUNNING,
                'batch_number' => $batchNumber,
                'import_batch_uuid' => $batch?->id,
                'page_size' => (int) ($options['page_size'] ?? 25),
                'batch_limit' => $limit,
                'is_dry_run' => false,
                'started_at' => $run->started_at ?? now(),
                'last_error' => null,
                'finished_at' => null,
            ]);
            $run->save();
            $report->run = $run;
        }

        $processed = 0;
        $beforeProductCount = Product::query()->count();
        $startingOffset = (int) (($run?->cursor['offset'] ?? $run?->cursor['page'] ?? 0));

        try {
            if ($reader instanceof ResumableJsonCatalogueSourceReader) {
                while ($limit === null || $processed < $limit) {
                    $source = $reader->pull();
                    if ($source === null) {
                        break;
                    }
                    $this->processRecord($source, $reader->sourceName(), $batchNumber, $batch?->id, $dryRun, $report);
                    $processed++;
                    $report->sourceRecordsRetrieved = $processed;
                    if (! $dryRun && $run && ($processed % max(1, (int) ($options['page_size'] ?? 25)) === 0)) {
                        $run->cursor = $reader->checkpoint();
                        $run->stats = $report->totals();
                        $run->save();
                    }
                }
            } else {
                foreach ($reader->records() as $source) {
                    $this->processRecord($source, $reader->sourceName(), $batchNumber, $batch?->id, $dryRun, $report);
                    $processed++;
                    $report->sourceRecordsRetrieved = $processed;

                    if (! $dryRun && $run && $reader instanceof ResumableCatalogueSourceReader && ($processed % max(1, (int) ($options['page_size'] ?? 25)) === 0)) {
                        $run->cursor = $reader->checkpoint();
                        $run->stats = $report->totals();
                        $run->save();
                    }

                    if ($limit !== null && $processed >= $limit) {
                        break;
                    }
                }
            }
        } catch (\Throwable $exception) {
            if (! $dryRun && $run) {
                $run->status = BulkIngestRunStatus::FAILED;
                $run->last_error = $exception->getMessage();
                if ($reader instanceof ResumableCatalogueSourceReader) {
                    $run->cursor = $reader->checkpoint();
                }
                $run->stats = $report->totals();
                $run->save();
            }
            throw $exception;
        }

        $report->sourceRecordsRetrieved = $processed;

        if ($reader instanceof ResumableCatalogueSourceReader) {
            $stats = $reader->retrievalStats();
            $report->sourceProductsDiscovered = (int) ($stats['discovered'] ?? $processed);
            $report->retrievalFailures = (int) ($stats['retrieval_failures'] ?? 0);
            $exhausted = (bool) ($stats['exhausted'] ?? false);
            // Complete only when the authorized source has no further records.
            // Hitting --limit with remaining records leaves the run PAUSED/resumable.
            $report->complete = $exhausted;
            if (! empty($stats['last_error']) && $report->retrievalFailures > 0 && $processed === 0) {
                $report->blocker = (string) $stats['last_error'];
            }
        } else {
            $report->sourceProductsDiscovered = $processed;
            $report->complete = true;
        }

        $report->productsCreated = max(0, Product::query()->count() - $beforeProductCount);
        $report->awaitingHumanApproval = $report->lawfulCandidates + $report->complianceHolds;
        $report->approvedForPublication = \App\Models\CatalogueImportRecord::query()
            ->where('workflow_status', ImportWorkflowStatus::APPROVED)
            ->count();
        $report->actuallyPublished = Product::query()->where('status', ProductStatus::ACTIVE)->count();

        if (! $dryRun && $batch) {
            $batch->fill([
                'source_records' => $report->sourceRecordsRetrieved,
                'new_count' => $report->newStagingRecords,
                'possible_duplicates' => $report->duplicateConflicts,
                'exact_matches' => $report->existingMatched,
                'compliance_review' => $report->complianceHolds,
                'rejected' => $report->restrictedExcluded,
                'needs_review' => $report->awaitingHumanApproval,
                'review_count' => $report->awaitingHumanApproval,
                'rejected_count' => $report->restrictedExcluded,
                'summary' => $report->totals(),
                'finished_at' => $report->complete ? now() : null,
                'status' => $report->complete ? ImportBatchStatus::UNDER_REVIEW : ImportBatchStatus::IMPORTING,
            ])->save();
        }

        if (! $dryRun && $run) {
            if ($reader instanceof ResumableJsonCatalogueSourceReader) {
                $reader->seek($startingOffset + $processed);
                $run->cursor = $reader->checkpoint();
            } elseif ($reader instanceof ResumableCatalogueSourceReader) {
                $run->cursor = $reader->checkpoint();
            } else {
                $run->cursor = ['offset' => $startingOffset + $processed];
            }
            $run->stats = $report->totals();
            $run->status = $report->complete
                ? BulkIngestRunStatus::COMPLETED
                : BulkIngestRunStatus::PAUSED;
            $run->finished_at = $report->complete ? now() : null;
            $run->save();
            $report->run = $run->fresh();
        }

        if ($batch !== null) {
            $this->auditLogger->log(
                eventType: 'catalogue_import.bulk_ingest',
                auditable: $batch,
                newValues: $report->totals(),
                reason: 'Bulk catalogue ingest checkpoint.'
            );
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    protected function processRecord(
        array $source,
        string $sourceName,
        string $batchNumber,
        ?string $batchUuid,
        bool $dryRun,
        CatalogueBulkIngestReport $report
    ): void {
        $normalized = $this->importService->normalize($source, $sourceName);
        $report->recordsNormalized++;

        if (! is_numeric($normalized['price_amount'] ?? null)) {
            $report->missingPrices++;
        }
        if (! filled($normalized['sku'] ?? null)) {
            $report->missingSkus++;
        }
        if (($normalized['image_references'] ?? []) === []) {
            $report->missingImages++;
        }
        if (! filled($normalized['brand'] ?? null) && ! filled($normalized['source_brand'] ?? null)) {
            $report->missingBrands++;
        }
        if (! filled($normalized['category'] ?? null) && ! filled($normalized['source_category'] ?? null)) {
            $report->missingCategories++;
        }

        if (($source['defer_commerce_exclusion'] ?? false) === true) {
            $source['requires_compliance_review'] = true;
            $exclusion = ['excluded' => false, 'reasons' => []];
        } else {
            $exclusion = $this->detector->excludedFromCommerceImport($normalized + [
                'source_url' => (string) ($source['source_url'] ?? $source['url'] ?? ''),
            ]);
        }
        if (($source['bulk_commerce_exclusion'] ?? false) === true || $exclusion['excluded']) {
            $report->restrictedExcluded++;
            $source['requires_compliance_review'] = true;
            $source['bulk_commerce_exclusion'] = true;
            $source['bulk_exclusion_reasons'] = array_values(array_unique(array_merge(
                array_map('strval', (array) ($source['bulk_exclusion_reasons'] ?? [])),
                $exclusion['reasons']
            )));

            $record = $this->importService->stage($source, $batchNumber, $sourceName, [
                'dry_run' => $dryRun,
                'batch_uuid' => $batchUuid,
                'create_product' => false,
                'force_workflow' => ImportWorkflowStatus::REJECTED,
            ]);

            $report->rows[] = [
                'reference' => $record->source_product_reference,
                'name' => $record->displayName(),
                'match_status' => $record->match_status?->value,
                'workflow_status' => ImportWorkflowStatus::REJECTED->value,
                'outcome' => 'restricted_excluded',
            ];

            return;
        }

        if (($source['requires_compliance_review'] ?? false) === true || filled($source['compliance_hold_reason'] ?? null)) {
            $source['requires_compliance_review'] = true;
        }

        $review = $this->detector->evaluate($normalized + [
            'requires_compliance_review' => (bool) ($source['requires_compliance_review'] ?? false),
            'source_url' => (string) ($source['source_url'] ?? $source['url'] ?? ''),
        ]);
        if ($review['requires_review']) {
            $source['requires_compliance_review'] = true;
        }

        $record = $this->importService->stage($source, $batchNumber, $sourceName, [
            'dry_run' => $dryRun,
            'batch_uuid' => $batchUuid,
            'create_product' => true,
        ]);

        if ($record->match_status === ImportMatchStatus::NEW && $record->product_id) {
            $report->newStagingRecords++;
            if ($record->requires_compliance_review || $record->workflow_status === ImportWorkflowStatus::COMPLIANCE_REVIEW) {
                $report->complianceHolds++;
            } else {
                $report->lawfulCandidates++;
            }
        } elseif ($record->match_status === ImportMatchStatus::EXACT_MATCH || $record->workflow_status === ImportWorkflowStatus::DUPLICATE) {
            $report->existingMatched++;
        } elseif ($record->match_status === ImportMatchStatus::POSSIBLE_DUPLICATE) {
            $report->duplicateConflicts++;
            if ($record->requires_compliance_review) {
                $report->complianceHolds++;
            } else {
                $report->lawfulCandidates++;
            }
        } elseif ($record->requires_compliance_review || $record->workflow_status === ImportWorkflowStatus::COMPLIANCE_REVIEW) {
            $report->complianceHolds++;
            $report->newStagingRecords++;
        } else {
            $report->lawfulCandidates++;
            $report->newStagingRecords++;
        }

        $report->rows[] = [
            'reference' => $record->source_product_reference,
            'name' => $record->displayName(),
            'match_status' => $record->match_status?->value,
            'workflow_status' => $record->workflow_status?->value,
            'outcome' => $record->requires_compliance_review ? 'compliance_hold' : 'candidate',
        ];
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
