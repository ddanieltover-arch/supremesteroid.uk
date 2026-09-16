<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\PaymentSubmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentPaymentSubmissionsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Recent payment submissions';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PaymentSubmission::query()
                    ->with(['payment.order'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment.order.order_number')
                    ->label('Order')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('submission_type')
                    ->label('Method')
                    ->badge(),
                Tables\Columns\TextColumn::make('submitted_amount')
                    ->label('Amount')
                    ->money('GBP'),
                Tables\Columns\TextColumn::make('review_status')
                    ->label('Status')
                    ->badge(),
            ])
            ->paginated(false)
            ->emptyStateHeading('No payment submissions yet')
            ->emptyStateDescription('Customer bank transfer and crypto proofs will appear here.');
    }
}
