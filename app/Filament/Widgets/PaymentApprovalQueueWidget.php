<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentStateMachine;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PaymentApprovalQueueWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Payment approval queue';

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Payment::query()
                    ->with(['order', 'latestSubmission.proofFile'])
                    ->whereIn('status', [
                        PaymentStatus::PAYMENT_SUBMITTED,
                        PaymentStatus::UNDER_REVIEW,
                    ])
                    ->latest('updated_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('latestSubmission.created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('order.customer_name_snapshot')
                    ->label('Customer')
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('method')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('GBP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_proof')
                    ->label('Proof')
                    ->boolean()
                    ->getStateUsing(fn (Payment $record): bool => filled($record->latestSubmission?->proof_file_id)),
            ])
            ->actions([
                Action::make('viewProof')
                    ->label('View proof')
                    ->icon('heroicon-o-document')
                    ->authorize(fn (Payment $record) => auth()->user()?->can('review', $record) ?? false)
                    ->visible(fn (Payment $record) => $record->submissions()->whereNotNull('proof_file_id')->exists())
                    ->url(fn (Payment $record) => route('orders.payment.proof.download', [$record->order_id, $record->id]))
                    ->openUrlInNewTab(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record) => in_array($record->status, [PaymentStatus::PAYMENT_SUBMITTED, PaymentStatus::UNDER_REVIEW], true))
                    ->authorize(fn (Payment $record) => auth()->user()?->can('review', $record) ?? false)
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Approval Notes')
                            ->placeholder('e.g. Bank statement verified / blockchain tx confirmed'),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        app(PaymentStateMachine::class)->transition(
                            payment: $record,
                            newStatus: PaymentStatus::PAID,
                            actor: auth()->user(),
                            reason: $data['admin_notes'] ?? 'Approved via Filament dashboard queue'
                        );
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Payment $record) => ! in_array($record->status, [PaymentStatus::PAID, PaymentStatus::REJECTED, PaymentStatus::REFUNDED], true))
                    ->authorize(fn (Payment $record) => auth()->user()?->can('review', $record) ?? false)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->placeholder('e.g. Reference mismatch / funds not received'),
                    ])
                    ->action(function (Payment $record, array $data): void {
                        app(PaymentStateMachine::class)->transition(
                            payment: $record,
                            newStatus: PaymentStatus::REJECTED,
                            actor: auth()->user(),
                            reason: $data['reason']
                        );
                    }),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No payments waiting for approval')
            ->emptyStateDescription('Submitted bank transfer and crypto proofs appear here for staff review.');
    }
}
