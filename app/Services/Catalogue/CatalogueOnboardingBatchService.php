<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportWorkflowStatus;
use App\Models\CatalogueImportBatch;
use App\Models\CatalogueImportRecord;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;

class CatalogueOnboardingBatchService
{
    public function __construct(
        protected CataloguePublicationChecklistService $checklist,
        protected CataloguePublicationService $publication,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Refresh batch counters and status from live records.
     * Batch becomes READY only when every non-terminal record is APPROVED (individually).
     * Never auto-publishes products.
     */
    public function refresh(CatalogueImportBatch $batch): CatalogueImportBatch
    {
        $records = $batch->records()->with(['product.images', 'product.brand', 'product.category', 'product.seoMetadata', 'product.content', 'product.variants.inventory', 'product.inventory', 'images'])->get();

        $approved = 0;
        $rejected = 0;
        $review = 0;
        $published = 0;

        foreach ($records as $record) {
            /** @var CatalogueImportRecord $record */
            $status = $record->workflow_status;
            if ($status === ImportWorkflowStatus::PUBLISHED) {
                $published++;
                $approved++;
            } elseif ($status === ImportWorkflowStatus::APPROVED) {
                $approved++;
            } elseif ($status === ImportWorkflowStatus::REJECTED || $status === ImportWorkflowStatus::DUPLICATE) {
                $rejected++;
            } else {
                $review++;
            }
        }

        $batchStatus = $batch->status ?? ImportBatchStatus::UNDER_REVIEW;
        if ($batchStatus !== ImportBatchStatus::CANCELLED && $batchStatus !== ImportBatchStatus::DRAFT) {
            if ($records->isNotEmpty() && $review === 0 && $published === $records->count()) {
                $batchStatus = ImportBatchStatus::COMPLETED;
            } elseif ($records->isNotEmpty() && $review === 0 && ($approved + $rejected) === $records->count()) {
                $batchStatus = ImportBatchStatus::READY;
            } elseif ($batch->finished_at !== null) {
                $batchStatus = ImportBatchStatus::UNDER_REVIEW;
            }
        }

        $batch->fill([
            'approved_count' => $approved,
            'rejected_count' => $rejected,
            'review_count' => $review,
            'rejected' => $rejected,
            'needs_review' => $review,
            'status' => $batchStatus,
        ])->save();

        return $batch->fresh();
    }

    /**
     * @return list<array{
     *   record_id: string,
     *   product: ?string,
     *   sku: ?string,
     *   brand: ?string,
     *   category: ?string,
     *   variants: int,
     *   price: ?string,
     *   images: int,
     *   content: bool,
     *   compliance: string,
     *   seo: string,
     *   status: 'READY'|'BLOCKED',
     *   workflow: string,
     *   reasons: list<string>
     * }>
     */
    public function preview(CatalogueImportBatch $batch): array
    {
        $rows = [];

        foreach ($batch->records()->with(['product.brand', 'product.category', 'product.images', 'product.seoMetadata', 'product.content', 'product.variants.inventory', 'product.inventory', 'images'])->get() as $record) {
            /** @var CatalogueImportRecord $record */
            $product = $record->product;
            $reasons = [];

            if ($record->requires_compliance_review || $record->workflow_status === ImportWorkflowStatus::COMPLIANCE_REVIEW) {
                $reasons[] = 'Compliance review incomplete';
            }

            if ($product === null) {
                $reasons[] = 'No staged product draft';
                $rows[] = $this->row($record, null, 'BLOCKED', $reasons);
                continue;
            }

            if ($product->is_demo) {
                $reasons[] = 'Demo products cannot be published';
            }

            $checklist = $this->checklist->evaluate($product);
            if (! $checklist['can_publish']) {
                $reasons = array_values(array_unique([...$reasons, ...$checklist['failures']]));
            }

            $pendingMedia = $record->images->where('status', 'PENDING')->count();
            if ($pendingMedia > 0) {
                $reasons[] = 'Pending media requires approval';
            }

            $status = $reasons === [] ? 'READY' : 'BLOCKED';

            $rows[] = $this->row($record, $product, $status, $reasons);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $reasons
     * @return array<string, mixed>
     */
    protected function row(CatalogueImportRecord $record, $product, string $status, array $reasons): array
    {
        return [
            'record_id' => $record->id,
            'product' => $product?->name ?? $record->displayName(),
            'sku' => $product?->sku ?? $record->source_product_reference,
            'brand' => $product?->brand?->name ?? $record->source_brand,
            'category' => $product?->category?->name ?? $record->source_category,
            'variants' => $product?->variants?->count() ?? 0,
            'price' => $product?->price_amount !== null
                ? (string) $product->price_amount
                : ($record->source_price_amount !== null ? (string) $record->source_price_amount : null),
            'images' => $record->images->count(),
            'content' => filled($product?->description) || filled($product?->content?->overview),
            'compliance' => $record->requires_compliance_review ? 'HELD' : 'CLEAR',
            'seo' => filled($product?->seoMetadata?->meta_title) ? 'SET' : 'PENDING',
            'status' => $status,
            'workflow' => $record->workflow_status?->value ?? 'UNKNOWN',
            'reasons' => $reasons,
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public function reportLines(CatalogueImportBatch $batch): Collection
    {
        $batch = $this->refresh($batch);
        $lines = collect($batch->reportLines());
        $lines->push('');
        $lines->push('Batch preview:');

        foreach ($this->preview($batch) as $row) {
            $reasonText = $row['reasons'] !== [] ? ' — '.implode('; ', $row['reasons']) : '';
            $lines->push(sprintf(
                '%s | %s | %s | %s%s',
                $row['sku'] ?? '—',
                $row['product'] ?? '—',
                $row['status'],
                $row['workflow'],
                $reasonText
            ));
        }

        return $lines;
    }
}
