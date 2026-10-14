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
