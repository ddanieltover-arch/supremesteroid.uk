<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\ImportWorkflowStatus;
use App\Enums\ProductStatus;
use App\Models\CatalogueBulkIngestRun;
use App\Models\CatalogueImportRecord;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogueReviewDashboardService
{
    /**
     * Grouped review counts from live catalogue_import_records queries.
     *
     * @return array<string, int>
     */
    public function workflowCounts(): array
    {
        $raw = CatalogueImportRecord::query()
            ->select('workflow_status', DB::raw('count(*) as aggregate'))
            ->groupBy('workflow_status')
            ->pluck('aggregate', 'workflow_status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $count = function (ImportWorkflowStatus ...$statuses) use ($raw): int {
            $total = 0;
            foreach ($statuses as $status) {
                $total += (int) ($raw[$status->value] ?? 0);
            }

            return $total;
        };

        return [
            'imported' => $count(
                ImportWorkflowStatus::DISCOVERED,
                ImportWorkflowStatus::IMPORTED,
                ImportWorkflowStatus::STAGED,
                ImportWorkflowStatus::NORMALIZED,
                ImportWorkflowStatus::DUPLICATE,
                ImportWorkflowStatus::REVIEW_REQUIRED,
            ),
            'content_review' => $count(
                ImportWorkflowStatus::CONTENT_REVIEW,
                ImportWorkflowStatus::CONTENT_REWRITE,
            ),
            'compliance_review' => $count(ImportWorkflowStatus::COMPLIANCE_REVIEW),
            'media_review' => $count(ImportWorkflowStatus::MEDIA_REVIEW),
            'pricing_review' => $count(ImportWorkflowStatus::PRICING_REVIEW),
            'ready_for_approval' => $count(ImportWorkflowStatus::APPROVED),
            'published' => $count(ImportWorkflowStatus::PUBLISHED),
            'rejected' => $count(ImportWorkflowStatus::REJECTED),
        ];
    }

    /**
     * Deduplicated bulk-ingest / staging summary for admin review.
     *
     * @return array<string, int|string|bool|null>
     */
    public function bulkIngestSummary(): array
    {
        $workflow = $this->workflowCounts();
        $latestRun = null;
        if (Schema::hasTable('catalogue_bulk_ingest_runs')) {
            $latestRun = CatalogueBulkIngestRun::query()->orderByDesc('updated_at')->first();
        }

        return [
            'import_records' => CatalogueImportRecord::query()->count(),
            'restricted_excluded_records' => $workflow['rejected'],
            'compliance_holds' => $workflow['compliance_review'],
            'awaiting_human_approval' => $workflow['content_review']
                + $workflow['media_review']
                + $workflow['pricing_review']
                + $workflow['imported']
                + $workflow['compliance_review'],
            'ready_for_approval' => $workflow['ready_for_approval'],
            'published_import_records' => $workflow['published'],
            'products_total' => Product::query()->count(),
            'products_active' => Product::query()->where('status', ProductStatus::ACTIVE)->count(),
            'products_imported' => Product::query()->where('status', ProductStatus::IMPORTED)->count(),
            'latest_bulk_run_key' => $latestRun?->run_key,
            'latest_bulk_run_status' => $latestRun?->status?->value,
            'latest_bulk_run_stats' => $latestRun?->stats,
        ];
    }
}
