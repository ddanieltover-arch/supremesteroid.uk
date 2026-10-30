<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        return 'Payments';
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'needs_approval';
    }

    public function getTabs(): array
    {
        $count = static fn (callable $scope): int => $scope(Payment::query())->count();

        return [
            'all' => Tab::make('All')
                ->badge($count(fn (Builder $query) => $query)),
            'needs_approval' => Tab::make('Needs approval')
                ->badge($count(fn (Builder $query) => $query->whereIn('status', [
                    PaymentStatus::PAYMENT_SUBMITTED,
                    PaymentStatus::UNDER_REVIEW,
                ])))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    PaymentStatus::PAYMENT_SUBMITTED,
                    PaymentStatus::UNDER_REVIEW,
                ])),
            'pending' => Tab::make('Pending')
                ->badge($count(fn (Builder $query) => $query->where('status', PaymentStatus::PENDING)))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::PENDING)),
            'paid' => Tab::make('Paid')
                ->badge($count(fn (Builder $query) => $query->where('status', PaymentStatus::PAID)))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::PAID)),
            'rejected' => Tab::make('Rejected')
                ->badge($count(fn (Builder $query) => $query->where('status', PaymentStatus::REJECTED)))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::REJECTED)),
        ];
    }
}
