<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use App\Models\Inventory;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInventories extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    public function getTabs(): array
    {
        $count = static fn (callable $scope): int => $scope(Inventory::query())->count();

        return [
            'all' => Tab::make('All')
                ->badge($count(fn (Builder $query) => $query)),
            'low_stock' => Tab::make('Low stock')
                ->badge($count(fn (Builder $query) => $query
                    ->whereRaw('(quantity_on_hand - quantity_reserved) <= safety_stock_threshold')
                    ->whereRaw('(quantity_on_hand - quantity_reserved) > 0')))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereRaw('(quantity_on_hand - quantity_reserved) <= safety_stock_threshold')
                    ->whereRaw('(quantity_on_hand - quantity_reserved) > 0')),
            'out_of_stock' => Tab::make('Out of stock')
                ->badge($count(fn (Builder $query) => $query
                    ->whereRaw('(quantity_on_hand - quantity_reserved) <= 0')))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereRaw('(quantity_on_hand - quantity_reserved) <= 0')),
        ];
    }
}
