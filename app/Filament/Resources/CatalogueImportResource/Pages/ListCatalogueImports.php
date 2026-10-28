<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogueImportResource\Pages;

use App\Enums\ImportWorkflowStatus;
use App\Filament\Resources\CatalogueImportResource;
use App\Services\Catalogue\CatalogueReviewDashboardService;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCatalogueImports extends ListRecords
{
    protected static string $resource = CatalogueImportResource::class;

    public function getTitle(): string
    {
        return 'Catalogue import review';
    }

    public function getHeading(): string
    {
        $summary = app(CatalogueReviewDashboardService::class)->bulkIngestSummary();
        $run = $summary['latest_bulk_run_key']
            ? 'Latest bulk run '.$summary['latest_bulk_run_key'].' ('.$summary['latest_bulk_run_status'].'). '
            : 'No bulk ingest run yet. ';

        return $run
            .'Records '.$summary['import_records']
            .' · Excluded '.$summary['restricted_excluded_records']
            .' · Holds '.$summary['compliance_holds']
            .' · Awaiting review '.$summary['awaiting_human_approval']
            .' · Ready '.$summary['ready_for_approval']
            .' · Published records '.$summary['published_import_records']
            .' · Active products '.$summary['products_active'];
    }

    public function getTabs(): array
    {
        $counts = app(CatalogueReviewDashboardService::class)->workflowCounts();

        return [
            'all' => Tab::make('All'),
            'imported' => Tab::make('Imported')
                ->badge($counts['imported'])
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('workflow_status', [
                    ImportWorkflowStatus::DISCOVERED->value,
                    ImportWorkflowStatus::IMPORTED->value,
                    ImportWorkflowStatus::STAGED->value,
                    ImportWorkflowStatus::NORMALIZED->value,
                    ImportWorkflowStatus::DUPLICATE->value,
                    ImportWorkflowStatus::REVIEW_REQUIRED->value,
                ])),
            'content_review' => Tab::make('Content Review')
                ->badge($counts['content_review'])
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('workflow_status', [
                    ImportWorkflowStatus::CONTENT_REVIEW->value,
                    ImportWorkflowStatus::CONTENT_REWRITE->value,
                ])),
            'compliance_review' => Tab::make('Compliance Review')
                ->badge($counts['compliance_review'])
                ->modifyQueryUsing(fn (Builder $query) => $query->where(
                    'workflow_status',
                    ImportWorkflowStatus::COMPLIANCE_REVIEW->value
                )),
            'media_review' => Tab::make('Media Review')
                ->badge($counts['media_review'])
                ->modifyQueryUsing(fn (Builder $query) => $query->where(
                    'workflow_status',
                    ImportWorkflowStatus::MEDIA_REVIEW->value
                )),
            'pricing_review' => Tab::make('Pricing Review')
                ->badge($counts['pricing_review'])
                ->modifyQueryUsing(fn (Builder $query) => $query->where(
                    'workflow_status',
                    ImportWorkflowStatus::PRICING_REVIEW->value
                )),
            'ready' => Tab::make('Ready for Approval')
                ->badge($counts['ready_for_approval'])
                ->modifyQueryUsing(fn (Builder $query) => $query->where(
                    'workflow_status',
                    ImportWorkflowStatus::APPROVED->value
                )),
            'published' => Tab::make('Published')
                ->badge($counts['published'])
                ->modifyQueryUsing(fn (Builder $query) => $query->where(
                    'workflow_status',
                    ImportWorkflowStatus::PUBLISHED->value
                )),
            'rejected' => Tab::make('Rejected')
                ->badge($counts['rejected'])
                ->modifyQueryUsing(fn (Builder $query) => $query->where(
                    'workflow_status',
                    ImportWorkflowStatus::REJECTED->value
                )),
        ];
    }
}
