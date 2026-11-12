<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Enums\ProductStatus;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $count = static fn (callable $scope): int => $scope(Product::query())->count();

        return [
            'all' => Tab::make('All')
                ->badge($count(fn (Builder $query) => $query)),
            'compliance_backlog' => Tab::make('Compliance backlog')
                ->badge($count(fn (Builder $query) => $query->where(function (Builder $inner) {
                    $inner->whereNull('compliance_verified_at')
                        ->orWhereIn('status', [
                            ProductStatus::UNDER_REVIEW,
                            ProductStatus::COMPLIANCE_REVIEW,
                        ]);
                })))
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function (Builder $inner) {
                    $inner->whereNull('compliance_verified_at')
                        ->orWhereIn('status', [
                            ProductStatus::UNDER_REVIEW,
                            ProductStatus::COMPLIANCE_REVIEW,
                        ]);
                })),
            'active' => Tab::make('Active')
                ->badge($count(fn (Builder $query) => $query->where('status', ProductStatus::ACTIVE)))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProductStatus::ACTIVE)),
            'draft' => Tab::make('Draft')
                ->badge($count(fn (Builder $query) => $query->where('status', ProductStatus::DRAFT)))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProductStatus::DRAFT)),
        ];
    }
}
