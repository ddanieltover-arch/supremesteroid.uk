<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Models\CatalogueBulkIngestRun;
use App\Models\CatalogueImportBatch;

class CatalogueBulkIngestReport
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $missing
     */
    public function __construct(
        public readonly string $runKey,
        public readonly string $batchNumber,
        public readonly string $sourceName,
        public readonly string $sourceType,
        public readonly bool $dryRun,
        public int $sourceProductsDiscovered = 0,
        public int $sourceRecordsRetrieved = 0,
        public int $retrievalFailures = 0,
        public int $recordsNormalized = 0,
        public int $newStagingRecords = 0,
        public int $existingMatched = 0,
        public int $duplicateConflicts = 0,
        public int $restrictedExcluded = 0,
        public int $complianceHolds = 0,
        public int $lawfulCandidates = 0,
        public int $missingPrices = 0,
        public int $missingSkus = 0,
        public int $missingImages = 0,
        public int $missingBrands = 0,
        public int $missingCategories = 0,
        public int $awaitingHumanApproval = 0,
        public int $approvedForPublication = 0,
        public int $actuallyPublished = 0,
        public int $productsCreated = 0,
        public bool $resumed = false,
        public bool $complete = false,
        public ?string $blocker = null,
        public array $rows = [],
        public array $missing = [],
        public ?CatalogueImportBatch $batch = null,
        public ?CatalogueBulkIngestRun $run = null,
    ) {}

    /**
     * @return array<string, int|string|bool|null>
     */
    public function totals(): array
    {
        return [
            'run_key' => $this->runKey,
            'batch_number' => $this->batchNumber,
            'source_name' => $this->sourceName,
            'source_type' => $this->sourceType,
            'dry_run' => $this->dryRun,
            'resumed' => $this->resumed,
            'complete' => $this->complete,
            'source_products_discovered' => $this->sourceProductsDiscovered,
            'source_records_retrieved' => $this->sourceRecordsRetrieved,
            'retrieval_failures' => $this->retrievalFailures,
            'records_normalized' => $this->recordsNormalized,
            'new_staging_records' => $this->newStagingRecords,
            'existing_matched' => $this->existingMatched,
            'duplicate_conflicts' => $this->duplicateConflicts,
            'restricted_excluded' => $this->restrictedExcluded,
            'compliance_holds' => $this->complianceHolds,
            'lawful_candidates' => $this->lawfulCandidates,
            'missing_prices' => $this->missingPrices,
            'missing_skus' => $this->missingSkus,
            'missing_images' => $this->missingImages,
            'missing_brands' => $this->missingBrands,
            'missing_categories' => $this->missingCategories,
            'awaiting_human_approval' => $this->awaitingHumanApproval,
            'approved_for_publication' => $this->approvedForPublication,
            'actually_published' => $this->actuallyPublished,
            'products_created' => $this->productsCreated,
            'blocker' => $this->blocker,
        ];
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            'Bulk catalogue ingest #'.$this->runKey.' (batch '.$this->batchNumber.')',
            'Source: '.$this->sourceName.' / '.$this->sourceType,
            'Source products discovered: '.$this->sourceProductsDiscovered,
            'Source records retrieved: '.$this->sourceRecordsRetrieved,
            'Retrieval failures: '.$this->retrievalFailures,
            'Records normalized: '.$this->recordsNormalized,
            'New staging records: '.$this->newStagingRecords,
            'Existing matched: '.$this->existingMatched,
            'Duplicate conflicts: '.$this->duplicateConflicts,
            'Restricted excluded: '.$this->restrictedExcluded,
            'Compliance holds: '.$this->complianceHolds,
            'Potentially lawful candidates: '.$this->lawfulCandidates,
            'Missing prices: '.$this->missingPrices,
            'Missing source SKUs: '.$this->missingSkus,
            'Missing images: '.$this->missingImages,
            'Missing brands: '.$this->missingBrands,
            'Missing categories: '.$this->missingCategories,
            'Awaiting human approval: '.$this->awaitingHumanApproval,
            'Approved for publication: '.$this->approvedForPublication,
            'Actually published: '.$this->actuallyPublished,
            'Products created this run: '.$this->productsCreated,
            'Resumed: '.($this->resumed ? 'yes' : 'no'),
            'Complete: '.($this->complete ? 'yes' : 'no'),
            $this->dryRun ? 'Mode: dry-run (no catalogue mutations)' : 'Mode: staging write',
            $this->blocker ? 'Blocker: '.$this->blocker : 'Blocker: none',
        ];
    }
}
