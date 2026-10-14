<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\ImportWorkflowStatus;
use App\Models\CatalogueImportRecord;
use Illuminate\Support\Facades\DB;

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
}
