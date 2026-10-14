<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Models\CatalogueImportBatch;

class CatalogueImportReport
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly string $batchNumber,
        public readonly string $sourceName,
        public readonly string $sourceType,
        public readonly bool $dryRun,
        public int $sourceRecords = 0,
        public int $new = 0,
        public int $possibleDuplicates = 0,
        public int $exactMatches = 0,
        public int $complianceReview = 0,
        public int $rejected = 0,
        public int $needsReview = 0,
        public int $missingSourceSkus = 0,
        public array $rows = [],
        public ?CatalogueImportBatch $batch = null,
    ) {}

    /**
     * @return array<string, int|string|bool>
     */
    public function totals(): array
    {
        return [
            'batch_number' => $this->batchNumber,
            'source_name' => $this->sourceName,
            'source_type' => $this->sourceType,
            'dry_run' => $this->dryRun,
            'source_records' => $this->sourceRecords,
            'new' => $this->new,
            'possible_duplicates' => $this->possibleDuplicates,
            'exact_matches' => $this->exactMatches,
            'compliance_review' => $this->complianceReview,
            'rejected' => $this->rejected,
            'needs_review' => $this->needsReview,
            'missing_source_skus' => $this->missingSourceSkus,
        ];
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            'Catalogue Import #'.$this->batchNumber,
            'Source records: '.$this->sourceRecords,
            'New: '.$this->new,
            'Possible duplicates: '.$this->possibleDuplicates,
            'Exact matches: '.$this->exactMatches,
            'Compliance review: '.$this->complianceReview,
            'Rejected: '.$this->rejected,
            'Needs review: '.$this->needsReview,
            'Missing source SKUs (internal identifiers assigned): '.$this->missingSourceSkus,
            $this->dryRun ? 'Mode: dry-run (no catalogue mutations)' : 'Mode: staging write',
        ];
    }
}
