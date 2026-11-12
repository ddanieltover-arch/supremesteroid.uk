<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function getTabs(): array
    {
        $count = static fn (callable $scope): int => $scope(Order::query())->count();

        return [
            'all' => Tab::make('All')
                ->badge($count(fn (Builder $query) => $query)),
            'today' => Tab::make('Today')
                ->badge($count(fn (Builder $query) => $query->whereDate('created_at', now()->toDateString())))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', now()->toDateString())),
            'awaiting_fulfilment' => Tab::make('Awaiting fulfilment')
                ->badge($count(fn (Builder $query) => $query->whereIn('status', [
                    OrderStatus::PAID,
                    OrderStatus::PROCESSING,
                ])))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    OrderStatus::PAID,
                    OrderStatus::PROCESSING,
                ])),
            'pending_payment' => Tab::make('Pending payment')
                ->badge($count(fn (Builder $query) => $query->whereIn('status', [
                    OrderStatus::PENDING_PAYMENT,
                    OrderStatus::PAYMENT_REVIEW,
                ])))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    OrderStatus::PENDING_PAYMENT,
                    OrderStatus::PAYMENT_REVIEW,
                ])),
        ];
    }
}
